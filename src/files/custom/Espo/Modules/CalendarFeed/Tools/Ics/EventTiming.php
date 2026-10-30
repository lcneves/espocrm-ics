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

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Maps EspoCRM meeting date fields onto iCalendar start and end values.
 *
 * All-day meetings use the date-only fields. Espo stores dateEndDate as the
 * last inclusive day, while iCalendar DTEND is exclusive.
 */
final class EventTiming
{
    public function __construct(private string $timeZone = 'UTC') {}

    /**
     * @return array{
     *   allDay: bool,
     *   start: ?DateTimeImmutable,
     *   end: ?DateTimeImmutable,
     *   startDate: ?string,
     *   endDateExclusive: ?string,
     * }|null
     */
    public function map(
        bool $isAllDay,
        ?string $dateStartDate,
        ?string $dateEndDate,
        ?string $dateStart,
        ?string $dateEnd,
    ): ?array {
        if ($isAllDay) {
            $startDate = $this->dateOnly($dateStartDate);

            if ($startDate === null) {
                $startDate = $this->dateInTimeZone($dateStart);
            }

            if ($startDate === null) {
                return null;
            }

            $inclusiveEnd = $this->dateOnly($dateEndDate);
            $exclusive = $inclusiveEnd ?? $this->dateInTimeZone($dateEnd);

            if ($exclusive === null) {
                $exclusive = $this->addDay($startDate);
            } elseif ($inclusiveEnd !== null) {
                $exclusive = $this->addDay($inclusiveEnd);
            }

            return [
                'allDay' => true,
                'start' => null,
                'end' => null,
                'startDate' => $startDate,
                'endDateExclusive' => $exclusive,
            ];
        }

        $start = $this->utc($dateStart);
        $end = $this->utc($dateEnd);

        if ($start === null) {
            return null;
        }

        if ($end === null) {
            $end = $start;
        }

        return [
            'allDay' => false,
            'start' => $start,
            'end' => $end,
            'startDate' => null,
            'endDateExclusive' => null,
        ];
    }

    private function dateOnly(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function addDay(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if (!$parsed instanceof DateTimeImmutable) {
            return $date;
        }

        return $parsed->add(new DateInterval('P1D'))->format('Y-m-d');
    }

    private function utc(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $parsed instanceof DateTimeImmutable ? $parsed : null;
    }

    private function dateInTimeZone(?string $value): ?string
    {
        $utc = $this->utc($value);

        if ($utc === null) {
            return null;
        }

        try {
            $zone = new DateTimeZone($this->timeZone);
        } catch (Exception) {
            $zone = new DateTimeZone('UTC');
        }

        return $utc->setTimezone($zone)->format('Y-m-d');
    }
}
