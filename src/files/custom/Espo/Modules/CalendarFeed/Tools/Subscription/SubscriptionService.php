<?php
/************************************************************************
 * This file is part of the Calendar Feed extension for EspoCRM.
 *
 * Copyright (c) 2026 Lucas Neves
 *
 * This extension is licensed under the MIT License.
 * EspoCRM itself is licensed under the GNU Affero General Public License v3.
 ************************************************************************/

declare(strict_types=1);

namespace Espo\Modules\CalendarFeed\Tools\Subscription;

use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\CalendarFeed\Entities\CalendarFeedSubscription;
use Espo\Modules\CalendarFeed\Tools\Feed\FeedNotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Creates, revokes, and resolves subscription tokens.
 *
 * Only a SHA-256 hash is stored. The raw token is returned to the caller
 * and is not written to the database or to logs.
 */
class SubscriptionService
{
    public function __construct(
        private EntityManager $entityManager,
        private TokenGenerator $tokens,
        private SubscriptionUrl $urls,
    ) {}

    /**
     * Attach a new token to a subscription that is about to be saved.
     */
    public function assignNewToken(Entity $entity): string
    {
        $token = $this->tokens->generate();

        $entity->set('tokenHash', $this->tokens->hash($token));
        $entity->set('isRevoked', false);
        $entity->set('revokedAt', null);
        $entity->set('subscriptionUrl', $this->urls->forToken($token));

        return $token;
    }

    /**
     * @throws NotFound
     */
    public function revoke(string $id): void
    {
        $entity = $this->requireSubscription($id);

        $entity->set('isRevoked', true);
        $entity->set('revokedAt', gmdate('Y-m-d H:i:s'));

        $this->entityManager->saveEntity($entity);
    }

    /**
     * @throws NotFound
     */
    public function regenerate(string $id): string
    {
        $entity = $this->requireSubscription($id);
        $token = $this->assignNewToken($entity);

        $this->entityManager->saveEntity($entity);

        return $this->urls->forToken($token);
    }

    /**
     * @throws FeedNotFound
     */
    public function resolveUser(string $token): User
    {
        if ($token === '' || strlen($token) > 512) {
            throw new FeedNotFound();
        }

        $subscription = $this->entityManager
            ->getRDBRepository(CalendarFeedSubscription::ENTITY_TYPE)
            ->where([
                'tokenHash' => $this->tokens->hash($token),
                'isRevoked' => false,
            ])
            ->findOne();

        if ($subscription === null) {
            throw new FeedNotFound();
        }

        $userId = $subscription->get('userId');

        if (!is_string($userId) || $userId === '') {
            throw new FeedNotFound();
        }

        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if (!$user instanceof User || !$user->isActive()) {
            throw new FeedNotFound();
        }

        return $user;
    }

    /**
     * @throws NotFound
     */
    private function requireSubscription(string $id): Entity
    {
        $entity = $this->entityManager->getEntityById(CalendarFeedSubscription::ENTITY_TYPE, $id);

        if ($entity === null) {
            throw new NotFound();
        }

        return $entity;
    }
}
