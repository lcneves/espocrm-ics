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

namespace Espo\Modules\CalendarFeed\Tools\Ics;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Stable DTSTAMP, LAST-MODIFIED, and SEQUENCE values.
 *
 * SEQUENCE is the later of the meeting modification time and a stored counter
 * that increases when only participant relationships change.
 */
final class RevisionStamp
{
    /**
     * @return array{stamp: DateTimeImmutable, sequence: int}
     */
    public static function resolve(?string $modifiedAt, ?string $revisedAt, ?int $sequence): array
    {
        $modified = self::parse($modifiedAt) ??
            new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC'));
        $revised = self::parse($revisedAt);

        $stamp = ($revised !== null && $revised > $modified) ? $revised : $modified;
        $seq = max($modified->getTimestamp(), $sequence ?? 0);

        if ($seq < 0) {
            $seq = 0;
        }

        return [
            'stamp' => $stamp,
            'sequence' => $seq,
        ];
    }

    private static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $utc = new DateTimeZone('UTC');
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $utc);

        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }

        try {
            return new DateTimeImmutable($value, $utc);
        } catch (\Exception) {
            return null;
        }
    }
}
