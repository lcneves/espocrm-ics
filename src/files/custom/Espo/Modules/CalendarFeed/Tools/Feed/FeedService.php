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

namespace Espo\Modules\CalendarFeed\Tools\Feed;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Modules\CalendarFeed\Tools\Ics\CalendarDocument;
use Espo\Modules\CalendarFeed\Tools\Ics\CalendarWriter;
use Espo\Modules\CalendarFeed\Tools\Ics\EventData;
use Espo\Modules\CalendarFeed\Tools\Ics\EventTiming;
use Espo\Modules\CalendarFeed\Tools\Ics\RevisionStamp;
use Espo\Modules\CalendarFeed\Tools\Meeting\MeetingFinder;
use Espo\Modules\CalendarFeed\Tools\Participant\ParticipantResolver;
use Espo\Modules\CalendarFeed\Tools\Revision\RevisionService;
use Espo\Modules\CalendarFeed\Tools\Subscription\SubscriptionService;
use Espo\Modules\CalendarFeed\Tools\Subscription\SubscriptionUrl;
use Espo\ORM\Entity;

/**
 * Builds a calendar for a subscription token.
 *
 * Reading the feed does not write anything.
 */
class FeedService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private MeetingFinder $finder,
        private ParticipantResolver $participants,
        private RevisionService $revisions,
        private CalendarWriter $writer,
        private Metadata $metadata,
        private Config $config,
        private SubscriptionUrl $urls,
    ) {}

    /**
     * @throws FeedNotFound
     */
    public function render(string $token): CalendarDocument
    {
        $owner = $this->subscriptions->resolveUser($token);
        $meetings = $this->finder->find($owner);
        $participants = $this->participants->resolve($owner, $meetings);

        $ids = [];

        foreach ($meetings as $meeting) {
            $ids[] = $meeting->getId();
        }

        $revisions = $this->revisions->findByMeetingIds($ids);
        $timing = new EventTiming((string) $this->config->get('timeZone', 'UTC'));
        $hasLocation = (bool) $this->metadata->get(['entityDefs', 'Meeting', 'fields', 'location']);
        $events = [];

        foreach ($meetings as $meeting) {
            $event = $this->toEvent(
                $meeting,
                $participants[$meeting->getId()] ?? [],
                $revisions[$meeting->getId()] ?? null,
                $timing,
                $hasLocation,
            );

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $this->writer->write($events);
    }

    /**
     * @param list<\Espo\Modules\CalendarFeed\Tools\Ics\Participant> $participants
     * @param array{revisedAt: ?string, sequence: int}|null $revision
     */
    private function toEvent(
        Entity $meeting,
        array $participants,
        ?array $revision,
        EventTiming $timing,
        bool $hasLocation,
    ): ?EventData {
        $isAllDay = (bool) $meeting->get('isAllDay');
        $dateStart = $meeting->get('dateStart');
        $dateEnd = $meeting->get('dateEnd');
        $mapped = $timing->map(
            $isAllDay,
            $this->stringOrNull($meeting->get('dateStartDate')),
            $this->stringOrNull($meeting->get('dateEndDate')),
            is_string($dateStart) ? $dateStart : null,
            is_string($dateEnd) ? $dateEnd : null,
        );

        if ($mapped === null) {
            return null;
        }

        $modifiedAt = $this->stringOrNull($meeting->get('modifiedAt'));
        $stamp = RevisionStamp::resolve(
            $modifiedAt,
            $revision['revisedAt'] ?? null,
            $revision['sequence'] ?? null,
        );

        $location = null;

        if ($hasLocation) {
            $value = $meeting->get('location');

            if (is_string($value) && $value !== '') {
                $location = $value;
            }
        }

        $summary = trim((string) $meeting->get('name'));

        if ($summary === '') {
            $summary = 'Meeting';
        }

        return new EventData(
            $meeting->getId(),
            $summary,
            (string) $meeting->get('description'),
            $location,
            $this->eventUrl($meeting),
            $mapped['allDay'],
            $mapped['start'],
            $mapped['end'],
            $mapped['startDate'],
            $mapped['endDateExclusive'],
            $stamp['stamp'],
            $stamp['sequence'],
            $participants,
        );
    }

    private function eventUrl(Entity $meeting): string
    {
        $joinUrl = $meeting->get('joinUrl');

        if (is_string($joinUrl)) {
            $joinUrl = trim($joinUrl);

            if ($joinUrl !== '' && !preg_match('/[\r\n]/', $joinUrl)) {
                return $joinUrl;
            }
        }

        return $this->urls->meeting($meeting->getId());
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
