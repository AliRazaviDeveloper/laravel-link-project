<?php

declare(strict_types=1);

use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\Enum\LinkStatus;
use Shortwave\Domain\Link\Enum\UnresolvableReason;
use Shortwave\Domain\Link\Event\LinkWasArchived;
use Shortwave\Domain\Link\Event\LinkWasCreated;
use Shortwave\Domain\Link\Event\LinkWasRetargeted;
use Shortwave\Domain\Link\Exception\LinkNotResolvable;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\ExpiryPolicy;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

/*
|--------------------------------------------------------------------------
| Link aggregate
|--------------------------------------------------------------------------
|
| No container, no database, no test doubles: the aggregate takes value objects and
| a timestamp and returns decisions. That is the payoff of keeping the rules out of
| the models, and these tests run in milliseconds because of it.
|
*/

describe('creation', function (): void {
    it('starts active and records a creation event', function (): void {
        $link = makeLink();

        expect($link->status())->toBe(LinkStatus::Active)
            ->and($link->clickCount())->toBe(0)
            ->and($link->isResolvableAt(testNow()))->toBeTrue();

        $events = $link->releaseEvents();

        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(LinkWasCreated::class)
            ->and($events[0]->name())->toBe('link.created');
    });

    it('hands over each event only once', function (): void {
        $link = makeLink();

        expect($link->releaseEvents())->toHaveCount(1)
            // Otherwise a handler that flushes twice would publish the same event
            // twice, and every downstream listener would run again.
            ->and($link->releaseEvents())->toBe([]);
    });

    it('trims a title and treats a blank one as absent', function (): void {
        expect(makeLink(title: '  Spring campaign  ')->title())->toBe('Spring campaign')
            ->and(makeLink(title: '   ')->title())->toBeNull();
    });

    it('rejects a title longer than the limit', function (): void {
        expect(fn (): Link => makeLink(title: str_repeat('a', 161)))
            ->toThrow(InvariantViolation::class);
    });
});

describe('resolution', function (): void {
    it('returns the destination while active', function (): void {
        expect(makeLink()->resolve(testNow())->value)->toBe('https://example.com/landing');
    });

    it('refuses an archived link and says why', function (): void {
        $link = makeLink();
        $link->archive(testNow());

        try {
            $link->resolve(testNow());
            $this->fail('An archived link should not resolve.');
        } catch (LinkNotResolvable $exception) {
            expect($exception->reason)->toBe(UnresolvableReason::Archived);
        }
    });

    // The expiry boundary is inclusive: at the stated instant the link is already gone.
    it('refuses a link past its expiry', function (): void {
        $link = makeLink(ExpiryPolicy::of(at('2026-03-02 12:00:00'), null, testNow()));

        expect($link->isResolvableAt(at('2026-03-02 11:59:59')))->toBeTrue();

        try {
            $link->resolve(at('2026-03-02 12:00:00'));
            $this->fail('An expired link should not resolve.');
        } catch (LinkNotResolvable $exception) {
            expect($exception->reason)->toBe(UnresolvableReason::Expired);
        }
    });

    it('refuses a link that has reached its click cap', function (): void {
        $link = makeLink(ExpiryPolicy::of(null, 2, testNow()));

        $link->registerClick();
        expect($link->isResolvableAt(testNow()))->toBeTrue();

        $link->registerClick();

        try {
            $link->resolve(testNow());
            $this->fail('A capped link should stop resolving.');
        } catch (LinkNotResolvable $exception) {
            expect($exception->reason)->toBe(UnresolvableReason::ClickLimitReached);
        }
    });

    it('reports archival before expiry when both apply', function (): void {
        $link = makeLink(ExpiryPolicy::of(at('2026-03-02 12:00:00'), null, testNow()));
        $link->archive(testNow());

        // Archival is the actionable reason: the owner can undo it, whereas the date
        // has simply passed.
        expect(fn () => $link->resolve(at('2026-04-01 00:00:00')))
            ->toThrow(LinkNotResolvable::class, 'This link has been archived.');
    });
});

describe('mutation', function (): void {
    it('records a retarget and moves the updated timestamp', function (): void {
        $link = makeLink();
        $link->releaseEvents();

        $link->retargetTo(Destination::fromString('https://example.com/new'), at('2026-03-05 09:00:00'));

        $events = $link->releaseEvents();

        expect($link->destination()->value)->toBe('https://example.com/new')
            ->and($link->updatedAt())->toEqual(at('2026-03-05 09:00:00'))
            ->and($events[0])->toBeInstanceOf(LinkWasRetargeted::class)
            ->and($events[0]->previousDestination->value)->toBe('https://example.com/landing');
    });

    it('ignores a retarget to the same destination', function (): void {
        $link = makeLink();
        $link->releaseEvents();

        $link->retargetTo(Destination::fromString('https://example.com/landing'), at('2026-03-05 09:00:00'));

        // No event and no touched timestamp: a no-op PATCH should not appear in an
        // audit trail or invalidate a cache entry.
        expect($link->releaseEvents())->toBe([])
            ->and($link->updatedAt())->toEqual(testNow());
    });

    it('is idempotent when archiving twice', function (): void {
        $link = makeLink();
        $link->releaseEvents();

        $link->archive(testNow());
        $link->archive(testNow());

        expect($link->releaseEvents())->toHaveCount(1)
            ->and($link->releaseEvents())->toBe([]);
    });

    it('restores an archived link without emitting an archive event', function (): void {
        $link = makeLink();
        $link->archive(testNow());
        $link->releaseEvents();

        $link->restore(at('2026-03-06 10:00:00'));

        expect($link->status())->toBe(LinkStatus::Active)
            ->and($link->releaseEvents())->toBe([]);
    });

    it('emits an archive event carrying the slug', function (): void {
        $link = makeLink(slug: 'launch-day');
        $link->releaseEvents();
        $link->archive(testNow());

        $event = $link->releaseEvents()[0];

        expect($event)->toBeInstanceOf(LinkWasArchived::class)
            ->and($event->payload()['slug'])->toBe('launch-day');
    });
});

describe('ownership', function (): void {
    it('recognises its own account and no other', function (): void {
        $link = makeLink();

        expect($link->belongsTo(AccountId::fromString('01HQ8V3M9XKPWT7CZR4NFGD2CD')))->toBeTrue()
            ->and($link->belongsTo(AccountId::fromString('01HQ8V3M9XKPWT7CZR4NFGD2EF')))->toBeFalse();
    });
});

describe('reconstitution', function (): void {
    it('loads a row whose expiry has already passed', function (): void {
        // A stored link must load even when its values would be rejected as fresh
        // input, or every expired row becomes unreadable.
        $link = Link::reconstitute(
            id: LinkId::fromString('01HQ8V3M9XKPWT7CZR4NFGD2AB'),
            accountId: AccountId::fromString('01HQ8V3M9XKPWT7CZR4NFGD2CD'),
            slug: Slug::fromString('old-news'),
            destination: Destination::fromString('https://example.com'),
            title: null,
            status: LinkStatus::Active,
            expiry: ExpiryPolicy::reconstitute(at('2020-01-01 00:00:00'), null),
            clickCount: 41,
            createdAt: at('2019-01-01 00:00:00'),
            updatedAt: at('2019-01-01 00:00:00'),
        );

        expect($link->clickCount())->toBe(41)
            ->and($link->isResolvableAt(testNow()))->toBeFalse()
            ->and($link->releaseEvents())->toBe([]);
    });
});
