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

namespace Espo\Modules\CalendarFeed\Classes\Record\CalendarFeedSubscription;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\CalendarFeed\Tools\Subscription\SubscriptionService;
use Espo\ORM\Entity;

/**
 * @implements SaveHook<Entity>
 */
class BeforeCreate implements SaveHook
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function process(Entity $entity): void
    {
        $userId = $entity->get('userId');

        if (!is_string($userId) || $userId === '') {
            throw new BadRequest('User is required.');
        }

        $name = trim((string) $entity->get('name'));

        if ($name === '') {
            throw new BadRequest('Name is required.');
        }

        $entity->set('name', $name);
        $this->subscriptions->assignNewToken($entity);
    }
}
