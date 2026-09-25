<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use Shortwave\Application\Link\Command\CreateLink;
use Shortwave\Application\Link\Command\ImportLinks;
use Shortwave\Application\Link\DTO\ImportOutcome;
use Shortwave\Application\Link\DTO\ImportReport;
use Shortwave\Domain\Account\Exception\AccountNotFound;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Shared\Exception\DomainException;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

/**
 * Bulk creation.
 *
 * Partial success is the contract: each row is created in its own transaction and
 * reported on individually, so one bad URL in a CSV of two hundred does not
 * discard the other hundred and ninety-nine. Callers who want all-or-nothing ask
 * for `stopOnFirstError`, which aborts the run but still reports what already
 * committed — honest about the fact that earlier rows are not rolled back.
 *
 * The whole-batch quota check happens up front so a plan limit fails the request
 * rather than silently truncating it halfway through.
 */
final readonly class ImportLinksHandler
{
    public function __construct(
        private CreateLinkHandler $createLink,
        private AccountRepository $accounts,
        private LinkRepository $links,
    ) {}

    public function handle(ImportLinks $command): ImportReport
    {
        $accountId = AccountId::fromString($command->accountId);
        $account = $this->accounts->findById($accountId);

        if ($account === null) {
            throw AccountNotFound::withId($accountId);
        }

        if ($command->links === []) {
            throw InvariantViolation::for('links', 'Provide at least one link to import.');
        }

        if (count($command->links) > ImportLinks::MAX_BATCH) {
            throw InvariantViolation::for('links', sprintf(
                'An import may contain at most %d links.',
                ImportLinks::MAX_BATCH,
            ));
        }

        if (! $account->plan()->allowsBulkImport()) {
            throw InvariantViolation::for(
                'links',
                'Bulk import is not available on the free plan.',
            );
        }

        $account->assertCanCreateLinks(
            $this->links->countForAccount($accountId),
            count($command->links),
        );

        return $this->runBatch($command);
    }

    private function runBatch(ImportLinks $command): ImportReport
    {
        $outcomes = [];

        foreach ($command->links as $index => $row) {
            $outcome = $this->attempt($index, $row, $command->accountId);
            $outcomes[] = $outcome;

            if ($outcome->failed() && $command->stopOnFirstError) {
                break;
            }
        }

        return new ImportReport($outcomes);
    }

    private function attempt(int $index, CreateLink $row, string $accountId): ImportOutcome
    {
        // Each row carries the batch's account id rather than whatever it was
        // constructed with, so a crafted payload cannot write into another account.
        $scoped = new CreateLink(
            accountId: $accountId,
            destinationUrl: $row->destinationUrl,
            slug: $row->slug,
            title: $row->title,
            expiresAt: $row->expiresAt,
            maxClicks: $row->maxClicks,
        );

        try {
            return ImportOutcome::created($index, $this->createLink->handle($scoped));
        } catch (DomainException $exception) {
            // Only domain failures are per-row. An infrastructure error means the
            // store is unhealthy, and continuing would produce a report full of
            // misleading "invalid" rows, so it propagates.
            return ImportOutcome::rejected($index, $exception);
        }
    }
}
