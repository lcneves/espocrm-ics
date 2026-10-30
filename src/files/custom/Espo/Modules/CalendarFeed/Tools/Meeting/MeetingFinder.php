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

namespace Espo\Modules\CalendarFeed\Tools\Meeting;

use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Select\Where\Item as WhereItem;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Meetings that belong on one user's feed.
 *
 * The owner is the assigned user or a users-link attendee. Record ACL for that
 * same user is applied as well, so the feed cannot see meetings the user cannot.
 */
class MeetingFinder
{
    private const PAGE_SIZE = 100;

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private MeetingWindow $window,
    ) {}

    /**
     * @return list<Entity>
     */
    public function find(User $owner): array
    {
        $window = $this->window->current();
        $statuses = [Meeting::STATUS_PLANNED, Meeting::STATUS_HELD];

        if ($window['includeNotHeld']) {
            $statuses[] = Meeting::STATUS_NOT_HELD;
        }

        $where = WhereItem::fromRaw([
            'type' => 'and',
            'value' => [
                [
                    'type' => 'or',
                    'value' => [
                        [
                            'type' => 'equals',
                            'attribute' => 'assignedUserId',
                            'value' => $owner->getId(),
                        ],
                        [
                            'type' => 'linkedWith',
                            'attribute' => 'users',
                            'value' => $owner->getId(),
                        ],
                    ],
                ],
                [
                    'type' => 'before',
                    'attribute' => 'dateStart',
                    'value' => $window['end'],
                    'dateTime' => true,
                    'timeZone' => 'UTC',
                ],
                [
                    'type' => 'after',
                    'attribute' => 'dateEnd',
                    'value' => $window['start'],
                    'dateTime' => true,
                    'timeZone' => 'UTC',
                ],
                [
                    'type' => 'in',
                    'attribute' => 'status',
                    'value' => $statuses,
                ],
            ],
        ]);

        $query = $this->selectBuilderFactory
            ->create()
            ->from(Meeting::ENTITY_TYPE)
            ->forUser($owner)
            ->withStrictAccessControl()
            ->withWhere($where)
            ->build();

        $meetings = [];
        $lastId = null;

        while (true) {
            $builder = $this->entityManager
                ->getRDBRepository(Meeting::ENTITY_TYPE)
                ->clone($query)
                ->distinct()
                ->order('id', 'ASC')
                ->limit(0, self::PAGE_SIZE);

            if ($lastId !== null) {
                $builder->where(['id>' => $lastId]);
            }

            $page = iterator_to_array($builder->find());

            if ($page === []) {
                break;
            }

            foreach ($page as $meeting) {
                if (!$meeting instanceof Entity) {
                    continue;
                }

                $meetings[$meeting->getId()] = $meeting;
                $lastId = $meeting->getId();
            }

            if (count($page) < self::PAGE_SIZE) {
                break;
            }
        }

        return array_values($meetings);
    }
}
