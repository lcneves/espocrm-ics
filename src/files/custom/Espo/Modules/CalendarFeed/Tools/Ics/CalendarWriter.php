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
use Sabre\VObject\Component\VCalendar;

/**
 * Serializes meetings to an RFC 5545 calendar.
 *
 * METHOD is PUBLISH and ORGANIZER is omitted so clients treat the feed as a
 * read-only snapshot rather than an invitation.
 */
final class CalendarWriter
{
    private const PRODID = '-//EspoCRM//Calendar Feed//EN';

    public function __construct(private DescriptionBuilder $descriptions) {}

    /**
     * @param list<EventData> $events
     */
    public function write(array $events): CalendarDocument
    {
        $calendar = new VCalendar([
            'PRODID' => self::PRODID,
            'VERSION' => '2.0',
            'CALSCALE' => 'GREGORIAN',
            'METHOD' => 'PUBLISH',
        ]);

        $latest = null;

        foreach ($events as $eventData) {
            $this->addEvent($calendar, $eventData);

            if ($latest === null || $eventData->stamp > $latest) {
                $latest = $eventData->stamp;
            }
        }

        $latest ??= new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC'));

        return new CalendarDocument($calendar->serialize(), $latest);
    }

    private function addEvent(VCalendar $calendar, EventData $eventData): void
    {
        $event = $calendar->add('VEVENT');
        $event->UID = $eventData->id . '@calendar-feed.espocrm';
        $event->SUMMARY = $eventData->summary;
        $event->DTSTAMP = $eventData->stamp;
        $event->add('LAST-MODIFIED', $eventData->stamp);
        $event->SEQUENCE = $eventData->sequence;
        $event->DESCRIPTION = $this->descriptions->build($eventData->description, $eventData->participants);

        if ($eventData->url !== '') {
            $event->URL = $eventData->url;
        }

        if ($eventData->location !== null && $eventData->location !== '') {
            $event->LOCATION = $eventData->location;
        }

        if ($eventData->allDay && $eventData->startDate !== null && $eventData->endDateExclusive !== null) {
            $event->add('DTSTART', $this->compactDate($eventData->startDate), ['VALUE' => 'DATE']);
            $event->add('DTEND', $this->compactDate($eventData->endDateExclusive), ['VALUE' => 'DATE']);
        } elseif ($eventData->start !== null && $eventData->end !== null) {
            $event->add('DTSTART', $eventData->start);
            $event->add('DTEND', $eventData->end);
        }

        foreach ($eventData->participants as $participant) {
            if ($participant->email === null || $participant->email === '') {
                continue;
            }

            $parameters = [
                'CN' => $participant->name,
                'RSVP' => 'FALSE',
            ];

            $partstat = $this->partstat($participant->status);

            if ($partstat !== null) {
                $parameters['PARTSTAT'] = $partstat;
            }

            $event->add('ATTENDEE', 'mailto:' . $participant->email, $parameters);
        }
    }

    private function compactDate(string $date): string
    {
        return str_replace('-', '', $date);
    }

    private function partstat(?string $status): ?string
    {
        return match ($status) {
            'Accepted' => 'ACCEPTED',
            'Tentative' => 'TENTATIVE',
            'Declined' => 'DECLINED',
            default => null,
        };
    }
}
