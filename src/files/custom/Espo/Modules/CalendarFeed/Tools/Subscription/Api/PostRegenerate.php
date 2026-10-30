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

namespace Espo\Modules\CalendarFeed\Tools\Subscription\Api;

use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\CalendarFeed\Entities\CalendarFeedSubscription;
use Espo\Modules\CalendarFeed\Tools\Subscription\SubscriptionService;
use Espo\ORM\EntityManager;

class PostRegenerate implements Action
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private EntityManager $entityManager,
        private AclManager $acl,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id') ?? '';

        if ($id === '') {
            throw new BadRequest();
        }

        $this->guard($id);

        return ResponseComposer::json([
            'subscriptionUrl' => $this->subscriptions->regenerate($id),
        ]);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    private function guard(string $id): void
    {
        $entity = $this->entityManager->getEntityById(CalendarFeedSubscription::ENTITY_TYPE, $id);

        if ($entity === null) {
            throw new NotFound();
        }

        if (!$this->acl->check($this->user, $entity, Table::ACTION_EDIT)) {
            throw new Forbidden();
        }
    }
}
