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

namespace Espo\Modules\CalendarFeed\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterMassRelate;
use Espo\Core\Hook\Hook\AfterRelate;
use Espo\Core\Hook\Hook\AfterUnrelate;
use Espo\Modules\CalendarFeed\Tools\Participant\AttendeeLinkCatalog;
use Espo\Modules\CalendarFeed\Tools\Revision\RevisionService;
use Espo\ORM\Entity;
use Espo\ORM\Query\Select;
use Espo\ORM\Repository\Option\MassRelateOptions;
use Espo\ORM\Repository\Option\RelateOptions;
use Espo\ORM\Repository\Option\UnrelateOptions;

/**
 * Bumps the calendar revision when attendees change without a meeting save.
 *
 * @implements AfterRelate<Entity>
 * @implements AfterUnrelate<Entity>
 * @implements AfterMassRelate<Entity>
 */
class ParticipantRevision implements AfterRelate, AfterUnrelate, AfterMassRelate
{
    public static int $order = 20;

    public function __construct(
        private AttendeeLinkCatalog $catalog,
        private RevisionService $revisions,
    ) {}

    public function afterRelate(
        Entity $entity,
        string $relationName,
        Entity $relatedEntity,
        array $columnData,
        RelateOptions $options,
    ): void {
        $this->bump($entity, $relationName);
    }

    public function afterUnrelate(
        Entity $entity,
        string $relationName,
        Entity $relatedEntity,
        UnrelateOptions $options,
    ): void {
        $this->bump($entity, $relationName);
    }

    public function afterMassRelate(
        Entity $entity,
        string $relationName,
        Select $query,
        array $columnData,
        MassRelateOptions $options,
    ): void {
        $this->bump($entity, $relationName);
    }

    /**
     * Acceptance changes go through the meeting service and do not save the meeting.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $hookData
     */
    public function afterConfirmation(Entity $entity, array $options, array $hookData): void
    {
        $link = $hookData['link'] ?? null;

        if (!is_string($link)) {
            return;
        }

        $this->bump($entity, $link);
    }

    private function bump(Entity $entity, string $relationName): void
    {
        if ($entity->getEntityType() !== 'Meeting' || !$this->catalog->isAttendeeLink($relationName)) {
            return;
        }

        $modifiedAt = $entity->get('modifiedAt');

        $this->revisions->bump(
            $entity->getId(),
            is_string($modifiedAt) ? $modifiedAt : null,
        );
    }
}
