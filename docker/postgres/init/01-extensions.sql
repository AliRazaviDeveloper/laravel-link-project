-- Runs once, on a fresh data directory.
--
-- pg_stat_statements is what makes "which query is slow" answerable in production
-- without adding instrumentation to the application. citext is not used: slugs are
-- normalised to lower case in the domain, so case-insensitive comparison is settled
-- before a value reaches the database.
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
