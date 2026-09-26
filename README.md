# Shortwave

A link shortener with an analytics spine, built as a REST API.

Two stores, deliberately: PostgreSQL holds the links and accounts, MongoDB holds the
click log, and Redis sits in front of both so a redirect costs one round trip instead of
a database query. The interesting part of this project is not the shortening — it is what
it takes to keep a redirect fast while still counting every click and reporting on it
honestly.

```
┌──────────┐   GET /xk4pq2a         ┌─────────────┐
│ browser  │ ─────────────────────► │   nginx     │
└──────────┘                        └──────┬──────┘
      ▲                                    │
      │ 302                         ┌──────▼──────┐        ┌────────────────┐
      └──────────────────────────── │   php-fpm   │ ◄─────► │  Redis         │
                                    └──┬───────┬──┘         │  cache         │
                                       │       │            │  click counter │
                        cache miss     │       │  buffered  │  click buffer  │
                                       ▼       ▼  clicks    └───────┬────────┘
                              ┌────────────┐  ┌──────────┐          │
                              │ PostgreSQL │  │  worker  │ ◄────────┘
                              │ links      │  └────┬─────┘  drains batches
                              │ accounts   │       │
                              │ tokens     │       ▼
                              └────────────┘  ┌──────────┐
                                      ▲       │ MongoDB  │
                                      │       │ clicks   │
                            reconcile │       └──────────┘
                            counters  │            │
                              ┌───────┴────────────▼──┐
                              │      scheduler        │
                              └───────────────────────┘
```

## Running it

The host needs Docker and nothing else — no PHP, no Composer, no databases.

```bash
make install
```

That copies `.env.example`, builds the images, installs dependencies, generates the app
key and starts the stack. It finishes in a minute or two and prints the URL.

```bash
make seed          # a demo account, an API token, and links in every state
open http://localhost:8080/docs
```

`make` on its own lists everything else.

### First requests

```bash
# Register and keep the token
TOKEN=$(curl -s -X POST localhost:8080/api/v1/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","name":"You","password":"correct-horse-battery-99"}' \
  | jq -r .token.value)

# Shorten something
curl -s -X POST localhost:8080/api/v1/links \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"destination_url":"https://laravel.com/docs","title":"Docs"}' | jq

# Follow it a few times, then look at the numbers
curl -si localhost:8080/demo-docs | head -1
curl -s -H "Authorization: Bearer $TOKEN" localhost:8080/api/v1/overview | jq .data.totals
```

Clicks reach the analytics store within a minute, or immediately with `make flush`.

## What it does

| | |
|---|---|
| **Short links** | Generated or vanity slugs, retargetable, with optional expiry dates and click caps |
| **Redirects** | One Redis round trip on the hot path; `302` by design, never `301` |
| **Analytics** | Time series, unique visitors, and breakdowns by country, referrer, browser, platform and device |
| **Bulk import** | Up to 200 links per request, with per-row results and a `207` for mixed batches |
| **Auth** | Bearer tokens with per-endpoint abilities, plan-scoped rate limits |
| **Idempotency** | `Idempotency-Key` on every write, so a client that timed out can safely retry |
| **Errors** | RFC 9457 `application/problem+json` everywhere, with a request id to trace |
| **Docs** | Hand-written OpenAPI 3.1 spec at `/docs`, with a test that fails if it drifts from the router |

## Why two databases

Links and clicks are different problems and they want different storage.

**Links** are a few rows per account, read constantly, and need guarantees. A slug must be
unique under concurrent creates, and a plan's link allowance must be checked and enforced
without two simultaneous requests both slipping under the limit. That is a unique index
and a transaction — Postgres.

**Clicks** are millions of rows, written once, never updated, and read only as aggregates
over a date range. Relational storage can do it, but keeping up means partitioning and
rollup tables to answer what `$dateTrunc` and `$facet` answer natively. MongoDB gets the
append-only log; nothing in it is ever corrected in place, and a compensating document is
written instead.

Nothing above the infrastructure layer knows which is which. Both sit behind repository
interfaces that the domain defines and the application layer consumes.

## Why Redis is not just a cache

Three distinct jobs, and only one of them is caching:

1. **Resolution cache.** Slug → destination, an hour TTL. Misses are cached too, briefly:
   a shortener is an open endpoint that scanners walk with slugs that do not exist, and
   without a negative entry every probe becomes a database query.

2. **Click counter.** A redirect would otherwise be `UPDATE ... SET click_count =
   click_count + 1`, which serialises all traffic for a viral link behind one row lock.
   Counting in Redis makes it a single atomic `INCR`, and a scheduled job folds the totals
   back into Postgres each minute. The cost is a bounded lag, which the API documents
   rather than hides — every read path adds the buffered count on top of the stored one.

3. **Click buffer.** A job per click would make the queue as busy as the traffic. Clicks
   accumulate in a list and are handed to a worker a hundred at a time, so one
   `insertMany` does the work of a hundred inserts.

The counter is the one thing in Redis that cannot be recomputed from another store, which
is why persistence is on and why the config keeps it on a separately configurable
connection.

## Architecture

Four layers, and dependencies point in one direction only: `Presentation → Application →
Domain`, with `Infrastructure` implementing the interfaces the inner layers declare.

```
src/
├── Domain/                     no framework imports at all
│   ├── Link/                   the aggregate: slugs, destinations, expiry, resolution
│   ├── Account/                accounts and plan limits
│   ├── Analytics/              click events and the shapes they aggregate into
│   └── Shared/                 identifiers, date ranges, base exceptions
├── Application/                use cases, one class per operation
│   ├── Link/                   commands, queries, handlers, the caches they drive
│   ├── Analytics/              reporting handlers
│   ├── Account/                registration and authentication
│   └── Shared/                 ports: CacheStore, TransactionManager, Clock, …
├── Infrastructure/             the only layer that knows a framework exists
│   ├── Persistence/Eloquent/   Postgres repositories, models, mappers
│   ├── Persistence/Mongo/      aggregation pipelines
│   ├── Cache/                  Redis cache store and click ledger
│   ├── Queue/                  buffered recorder and the batch job
│   └── Support/                clock, ULIDs, slug generation, UA parsing, hashing
└── Presentation/               HTTP and console entry points
    ├── Http/                   controllers, requests, resources, middleware, problems
    └── Console/                index sync, counter flush, retention pruning
```

Three consequences worth knowing about:

- **The `Link` aggregate decides whether a link resolves.** Expiry dates, click caps and
  archival are interpreted in exactly one place, so the redirect endpoint, the list
  endpoint and a console command cannot disagree about it.
- **Eloquent models hold no behaviour.** They are row mappings. `UserModel` exists because
  Sanctum needs an `Authenticatable`, and that requirement stops at the infrastructure
  boundary — the `Account` entity has no framework base class.
- **Handlers take value objects, not arrays.** A `Slug` cannot be constructed wrong, so
  validation lives with the type rather than being re-checked at every call site.

## Testing

```bash
make test         # everything
make test-unit    # domain and application only, no databases, ~0.3s
make check        # lint, static analysis, tests — what CI runs
```

**196 tests.** The split matters:

The **unit suite** boots no framework and touches no I/O. Value objects, the aggregate's
resolution rules, bucket arithmetic, the cache's tri-state lookup, User-Agent
classification and visitor hashing are all pure functions of their input, and they run in
under a second.

The **feature suite** uses all three real databases. Mocking them would leave the
interesting parts untested: whether the unique slug index actually rejects a concurrent
duplicate, whether `$dateTrunc` buckets align with the zero-filling that follows it,
whether the Redis drain is really atomic. It runs against separate databases — see
`.env.testing`, which exists because the containers supply `.env` as real environment
variables and PHPUnit cannot override those.

Static analysis is **PHPStan level 9** over `src`, with no baseline.

## Operational notes

- `GET /api/v1/health` probes all three stores and answers `503` if any is down, so an
  orchestrator pulls the instance rather than sending it traffic it cannot serve.
- Every response carries `X-Request-Id`. Supply your own to trace across services; it is
  echoed back and attached to every log line for that request.
- Logs are JSON on stderr. Containers should not be writing log files.
- The scheduler is load-bearing, not housekeeping: `shortwave:flush-clicks` and
  `shortwave:flush-buffer` move state that only they move. If it stops, click totals
  freeze.
- MongoDB indexes are created by `shortwave:mongo-sync`, which runs on boot and is
  idempotent. A TTL index handles click retention; `shortwave:prune-clicks` is the
  backstop for when the window is shortened.

### Privacy

Raw IP addresses and User-Agent strings never reach storage. At the edge of the request
they are reduced to:

- a **daily rotating hash** — HMAC of the truncated IP prefix, the User-Agent and the
  calendar date, under a server secret. Stable for one visitor for one day, so unique
  counts work; useless as a cross-day identifier, so the click log cannot be mined for a
  person's history even with the secret in hand.
- a **coarse device profile** — type, browser family, platform. Nothing finer.

Referrers are reduced to a host, because full referring URLs routinely carry session
tokens and search terms in their query strings.

### Security

- Destinations are validated on write: `http`/`https` only, and loopback, RFC 1918,
  link-local and cloud-metadata hosts are refused. A shortener is an open redirector by
  design, and that is exactly what makes it a useful tool for reaching hosts the caller
  could not reach directly.
- A link owned by another account answers `404`, not `403`. Confirming that an id exists
  is itself a leak.
- Login answers identically for a wrong password and an unknown address, and does the
  same hashing work in both cases, so it cannot be used to enumerate accounts.
- `500` responses carry no detail. Driver errors and stack traces routinely contain
  credentials and internal hostnames; the request id is the way back to the log.

## Stack

PHP 8.4 · Laravel 12 · PostgreSQL 18 · MongoDB 8 · Redis 8 · nginx · Pest 4 · PHPStan 9

## License

MIT.
