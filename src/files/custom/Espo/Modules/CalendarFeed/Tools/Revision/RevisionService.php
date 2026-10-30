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

namespace Espo\Modules\CalendarFeed\Tools\Revision;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Modules\CalendarFeed\Entities\CalendarFeedRevision;
use Espo\ORM\EntityManager;

/**
 * Remembers participant-only changes that do not update a meeting's modifiedAt.
 */
class RevisionService
{
    public function __construct(private EntityManager $entityManager) {}

    public function bump(string $meetingId, ?string $modifiedAt): void
    {
        $repository = $this->entityManager->getRDBRepository(CalendarFeedRevision::ENTITY_TYPE);
        $existing = $repository->where(['meetingId' => $meetingId])->findOne();

        $modifiedUnix = $this->unix($modifiedAt);
        $current = $existing ? (int) $existing->get('sequence') : 0;
        $sequence = max($current, $modifiedUnix, time()) + 1;

        if ($existing === null) {
            $existing = $this->entityManager->getNewEntity(CalendarFeedRevision::ENTITY_TYPE);
            $existing->set('meetingId', $meetingId);
        }

        $existing->set('sequence', $sequence);
        $existing->set('revisedAt', gmdate('Y-m-d H:i:s'));

        $this->entityManager->saveEntity($existing);
    }

    /**
     * @param list<string> $meetingIds
     * @return array<string, array{revisedAt: ?string, sequence: int}>
     */
    public function findByMeetingIds(array $meetingIds): array
    {
        if ($meetingIds === []) {
            return [];
        }

        $collection = $this->entityManager
            ->getRDBRepository(CalendarFeedRevision::ENTITY_TYPE)
            ->where(['meetingId' => $meetingIds])
            ->find();

        $map = [];

        foreach ($collection as $revision) {
            $meetingId = $revision->get('meetingId');

            if (!is_string($meetingId) || $meetingId === '') {
                continue;
            }

            $revisedAt = $revision->get('revisedAt');

            $map[$meetingId] = [
                'revisedAt' => is_string($revisedAt) ? $revisedAt : null,
                'sequence' => (int) $revision->get('sequence'),
            ];
        }

        return $map;
    }

    private function unix(?string $modifiedAt): int
    {
        if ($modifiedAt === null || $modifiedAt === '') {
            return 0;
        }

        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $modifiedAt,
            new DateTimeZone('UTC'),
        );

        if (!$parsed instanceof DateTimeImmutable) {
            return 0;
        }

        $unix = $parsed->getTimestamp();

        return $unix > 0 ? $unix : 0;
    }
}
