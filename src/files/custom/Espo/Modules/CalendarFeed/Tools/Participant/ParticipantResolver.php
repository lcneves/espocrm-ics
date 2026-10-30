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

namespace Espo\Modules\CalendarFeed\Tools\Participant;

use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\CalendarFeed\Tools\Ics\Participant;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PDO;

/**
 * Loads attendees for a page of meetings.
 *
 * One query reads the relationship rows for a link, and a second query loads
 * the people. An email address is included only when the feed owner may read
 * that record and its email field.
 */
class ParticipantResolver
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private AclManager $acl,
        private AttendeeLinkCatalog $catalog,
    ) {}

    /**
     * @param list<Entity> $meetings
     * @return array<string, list<Participant>>
     */
    public function resolve(User $owner, array $meetings): array
    {
        if ($meetings === []) {
            return [];
        }

        /** @var array<string, array<string, array{participant: Participant, group: int}>> $buckets */
        $buckets = [];
        $meetingIds = [];

        foreach ($meetings as $meeting) {
            $meetingIds[] = $meeting->getId();
            $buckets[$meeting->getId()] = [];
        }

        foreach ($this->catalog->getLinkNames() as $index => $link) {
            if (!$this->acl->checkLink($owner, 'Meeting', $link, Table::ACTION_READ)) {
                continue;
            }

            $entityType = $this->catalog->entityType($link);

            if ($entityType === null) {
                continue;
            }

            $this->addLink($owner, $buckets, $meetingIds, $link, $entityType, $index + 1);
        }

        $this->addAssignedUsers($owner, $meetings, $buckets);

        $result = [];

        foreach ($buckets as $meetingId => $rows) {
            $list = array_values($rows);

            usort($list, function (array $left, array $right): int {
                if ($left['group'] !== $right['group']) {
                    return $left['group'] <=> $right['group'];
                }

                return strcasecmp($left['participant']->name, $right['participant']->name);
            });

            $result[$meetingId] = array_map(
                fn (array $row): Participant => $row['participant'],
                $list,
            );
        }

        return $result;
    }

    /**
     * @param array<string, array<string, array{participant: Participant, group: int}>> $buckets
     * @param list<string> $meetingIds
     */
    private function addLink(
        User $owner,
        array &$buckets,
        array $meetingIds,
        string $link,
        string $entityType,
        int $group,
    ): void {
        $pairs = $this->relationshipRows($link, $meetingIds);

        if ($pairs === []) {
            return;
        }

        $personIds = [];

        foreach ($pairs as $pair) {
            $personIds[$pair['personId']] = true;
        }

        $people = $this->loadPeople($entityType, array_keys($personIds));

        foreach ($pairs as $pair) {
            if (!isset($buckets[$pair['meetingId']])) {
                continue;
            }

            $person = $people[$pair['personId']] ?? null;

            if ($person === null) {
                continue;
            }

            $key = $entityType . ':' . $pair['personId'];
            $buckets[$pair['meetingId']][$key] = [
                'group' => $group,
                'participant' => new Participant(
                    $entityType,
                    $pair['personId'],
                    $this->displayName($person, $entityType),
                    $this->emailFor($owner, $person),
                    $pair['status'],
                    false,
                ),
            ];
        }
    }

    /**
     * @param list<Entity> $meetings
     * @param array<string, array<string, array{participant: Participant, group: int}>> $buckets
     */
    private function addAssignedUsers(User $owner, array $meetings, array &$buckets): void
    {
        $ids = [];

        foreach ($meetings as $meeting) {
            $userId = $meeting->get('assignedUserId');

            if (is_string($userId) && $userId !== '') {
                $ids[$userId] = true;
            }
        }

        if ($ids === []) {
            return;
        }

        $users = $this->loadPeople(User::ENTITY_TYPE, array_keys($ids));

        foreach ($meetings as $meeting) {
            $userId = $meeting->get('assignedUserId');

            if (!is_string($userId) || $userId === '') {
                continue;
            }

            $user = $users[$userId] ?? null;

            if ($user === null) {
                continue;
            }

            $key = User::ENTITY_TYPE . ':' . $userId;
            $existing = $buckets[$meeting->getId()][$key]['participant'] ?? null;
            $status = $existing?->status;

            $buckets[$meeting->getId()][$key] = [
                'group' => 0,
                'participant' => new Participant(
                    User::ENTITY_TYPE,
                    $userId,
                    $this->displayName($user, User::ENTITY_TYPE),
                    $this->emailFor($owner, $user),
                    $status,
                    true,
                ),
            ];
        }
    }

    /**
     * @param list<string> $meetingIds
     * @return list<array{meetingId: string, personId: string, status: ?string}>
     */
    private function relationshipRows(string $link, array $meetingIds): array
    {
        $query = $this->entityManager
            ->getQueryBuilder()
            ->select('id', 'meetingId')
            ->select($link . 'Middle.status', 'acceptanceStatus')
            ->select($link . '.id', 'personId')
            ->from('Meeting')
            ->join($link)
            ->where(['id' => $meetingIds])
            ->build();

        $statement = $this->entityManager->getQueryExecutor()->execute($query);
        $rows = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }

            $meetingId = $this->column($row, 'meetingId');
            $personId = $this->column($row, 'personId');

            if (!is_string($meetingId) || !is_string($personId) || $meetingId === '' || $personId === '') {
                continue;
            }

            $status = $this->column($row, 'acceptanceStatus');

            $rows[] = [
                'meetingId' => $meetingId,
                'personId' => $personId,
                'status' => is_string($status) && $status !== '' ? $status : null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $ids
     * @return array<string, Entity>
     */
    private function loadPeople(string $entityType, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $select = ['id'];
        $defs = $this->entityManager->getDefs()->getEntity($entityType);

        foreach (
            ['name', 'firstName', 'lastName', 'isActive', 'type', 'assignedUserId', 'createdById'] as $attribute
        ) {
            if ($defs->hasAttribute($attribute)) {
                $select[] = $attribute;
            }
        }

        $emailField = $this->emailAttribute($entityType);

        if ($emailField !== null && $defs->hasAttribute($emailField)) {
            $select[] = $emailField;
        }

        $collection = $this->entityManager
            ->getRDBRepository($entityType)
            ->select($select)
            ->where(['id' => $ids])
            ->find();

        $people = [];

        foreach ($collection as $entity) {
            if ($entity instanceof Entity) {
                $people[$entity->getId()] = $entity;
            }
        }

        $this->fillPrimaryEmails($entityType, $people, $emailField);

        return $people;
    }

    /**
     * The email field is not a column. A partial select does not always populate it,
     * so the primary address is loaded in one extra query when it is still empty.
     *
     * @param array<string, Entity> $people
     */
    private function fillPrimaryEmails(string $entityType, array $people, ?string $emailField): void
    {
        if ($emailField !== 'emailAddress' || $people === []) {
            return;
        }

        $missing = [];

        foreach ($people as $id => $person) {
            $value = $person->get('emailAddress');

            if (!is_string($value) || $value === '') {
                $missing[] = $id;
            }
        }

        if ($missing === []) {
            return;
        }

        $rows = $this->entityManager
            ->getRDBRepository('EmailAddress')
            ->select(['name', ['ee.entityId', 'entityId']])
            ->join(
                'EntityEmailAddress',
                'ee',
                [
                    'ee.emailAddressId:' => 'id',
                    'ee.primary' => true,
                    'ee.deleted' => false,
                ],
            )
            ->where([
                'ee.entityType' => $entityType,
                'ee.entityId' => $missing,
            ])
            ->find();

        foreach ($rows as $row) {
            if (!$row instanceof Entity) {
                continue;
            }

            $id = $row->get('entityId');
            $address = $row->get('name');

            if (!is_string($id) || !is_string($address) || !isset($people[$id])) {
                continue;
            }

            $people[$id]->set('emailAddress', $address);
        }
    }

    private function displayName(Entity $entity, string $entityType): string
    {
        $name = trim((string) $entity->get('name'));

        if ($name !== '') {
            return $name;
        }

        $combined = trim(trim((string) $entity->get('firstName')) . ' ' . trim((string) $entity->get('lastName')));

        if ($combined !== '') {
            return $combined;
        }

        return $entityType;
    }

    private function emailFor(User $owner, Entity $entity): ?string
    {
        $field = $this->emailAttribute($entity->getEntityType());

        if ($field === null) {
            return null;
        }

        if (!$this->acl->tryCheck($owner, $entity, Table::ACTION_READ)) {
            return null;
        }

        if (!$this->acl->checkField($owner, $entity->getEntityType(), $field)) {
            return null;
        }

        $value = $entity->get($field);

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $value;
    }

    private function emailAttribute(string $entityType): ?string
    {
        $emailAddress = $this->metadata->get(['entityDefs', $entityType, 'fields', 'emailAddress', 'type']);

        if ($emailAddress === 'email') {
            return 'emailAddress';
        }

        $plain = $this->metadata->get(['entityDefs', $entityType, 'fields', 'email', 'type']);

        if ($plain === 'varchar' || $plain === 'email') {
            return 'email';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function column(array $row, string $name): mixed
    {
        if (array_key_exists($name, $row)) {
            return $row[$name];
        }

        foreach ($row as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
