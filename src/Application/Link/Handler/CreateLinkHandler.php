<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Handler;

use Shortwave\Application\Link\Command\CreateLink;
use Shortwave\Application\Link\DTO\LinkView;
use Shortwave\Application\Shared\Contract\EventPublisher;
use Shortwave\Application\Shared\Contract\IdentityGenerator;
use Shortwave\Application\Shared\Contract\SlugFactory;
use Shortwave\Application\Shared\Contract\TransactionManager;
use Shortwave\Application\Shared\Support\Timestamp;
use Shortwave\Domain\Account\Exception\AccountNotFound;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\Exception\SlugAlreadyTaken;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\ExpiryPolicy;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Contract\Clock;

final readonly class CreateLinkHandler
{
    public function __construct(
        private LinkRepository $links,
        private AccountRepository $accounts,
        private SlugFactory $slugs,
        private IdentityGenerator $identities,
        private TransactionManager $transactions,
        private EventPublisher $events,
        private Clock $clock,
        private string $shortDomain,
    ) {}

    public function handle(CreateLink $command): LinkView
    {
        $accountId = AccountId::fromString($command->accountId);
        $account = $this->accounts->findById($accountId);

        if ($account === null) {
            throw AccountNotFound::withId($accountId);
        }

        $now = $this->clock->now();

        $link = Link::create(
            id: LinkId::fromString($this->identities->next()),
            accountId: $accountId,
            slug: $this->resolveSlug($command->slug),
            destination: Destination::fromString($command->destinationUrl),
            title: $command->title,
            expiry: ExpiryPolicy::of(
                Timestamp::parseOptional($command->expiresAt, 'expires_at'),
                $command->maxClicks,
                $now,
            ),
            now: $now,
        );

        // The quota is checked inside the transaction so two concurrent creates
        // cannot both read a count one below the limit and both succeed.
        $this->transactions->transactional(function () use ($account, $accountId, $link): void {
            $account->assertCanCreateLinks($this->links->countForAccount($accountId));

            $this->links->add($link);
        });

        $this->events->publish($link->releaseEvents());

        return LinkView::fromEntity($link, $this->shortDomain, $now);
    }

    /**
     * @throws SlugAlreadyTaken
     */
    private function resolveSlug(?string $requested): Slug
    {
        if ($requested === null || trim($requested) === '') {
            return $this->slugs->generate();
        }

        $slug = Slug::fromString($requested);

        // A cheap pre-check so the common case returns a clean 409 rather than
        // surfacing a constraint violation from the driver.
        if ($this->links->slugExists($slug)) {
            throw SlugAlreadyTaken::for($slug);
        }

        return $slug;
    }
}
