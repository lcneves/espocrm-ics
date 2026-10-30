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

/**
 * One meeting ready to be written as a VEVENT.
 */
final class EventData
{
    /**
     * @param list<Participant> $participants
     */
    public function __construct(
        public readonly string $id,
        public readonly string $summary,
        public readonly string $description,
        public readonly ?string $location,
        public readonly string $url,
        public readonly bool $allDay,
        public readonly ?DateTimeImmutable $start,
        public readonly ?DateTimeImmutable $end,
        public readonly ?string $startDate,
        public readonly ?string $endDateExclusive,
        public readonly DateTimeImmutable $stamp,
        public readonly int $sequence,
        public readonly array $participants,
    ) {}
}
