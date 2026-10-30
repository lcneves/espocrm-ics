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

use Espo\Core\Utils\Metadata;

/**
 * Discovers which Meeting links represent attendees.
 *
 * Stock EspoCRM lists User, Contact, and Lead in scopes.Meeting.attendeeLinkMap.
 * Custom attendee entities follow the same shape: a hasMany link whose middle
 * table stores acceptance in a status column.
 */
final class AttendeeLinkCatalog
{
    /** @var list<string> */
    private const SKIP = [
        'assignedUser',
        'teams',
        'account',
        'parent',
        'createdBy',
        'modifiedBy',
        'sourceEmail',
    ];

    public function __construct(private Metadata $metadata) {}

    /**
     * @return list<string>
     */
    public function getLinkNames(): array
    {
        /** @var array<string, array<string, mixed>> $links */
        $links = $this->metadata->get('entityDefs.Meeting.links') ?? [];
        /** @var array<string, mixed> $map */
        $map = $this->metadata->get('scopes.Meeting.attendeeLinkMap') ?? [];

        return self::collect($links, $map);
    }

    /**
     * @param array<string, array<string, mixed>> $links
     * @param array<string, mixed> $attendeeLinkMap
     * @return list<string>
     */
    public static function collect(array $links, array $attendeeLinkMap): array
    {
        $mapped = array_values(array_filter($attendeeLinkMap, 'is_string'));
        $names = [];

        foreach ($links as $name => $defs) {
            if (!is_string($name) || in_array($name, self::SKIP, true)) {
                continue;
            }

            if (($defs['type'] ?? null) !== 'hasMany') {
                continue;
            }

            if (in_array($name, $mapped, true) || self::hasAcceptanceColumn($defs)) {
                $names[] = $name;
            }
        }

        usort($names, function (string $left, string $right): int {
            $rank = ['users' => 0, 'contacts' => 1, 'leads' => 2];
            $leftRank = $rank[$left] ?? 10;
            $rightRank = $rank[$right] ?? 10;

            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            return $left <=> $right;
        });

        return $names;
    }

    public function isAttendeeLink(string $link): bool
    {
        return in_array($link, $this->getLinkNames(), true);
    }

    public function entityType(string $link): ?string
    {
        $entityType = $this->metadata->get("entityDefs.Meeting.links.$link.entity");

        return is_string($entityType) && $entityType !== '' ? $entityType : null;
    }

    /**
     * @param array<string, mixed> $defs
     */
    private static function hasAcceptanceColumn(array $defs): bool
    {
        $columns = $defs['additionalColumns'] ?? null;
        $map = $defs['columnAttributeMap'] ?? null;

        if (!is_array($columns) || !is_array($map)) {
            return false;
        }

        return isset($columns['status']) && ($map['status'] ?? null) === 'acceptanceStatus';
    }
}
