<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\CalendarFeed;

use Espo\Modules\CalendarFeed\Tools\Ics\CalendarWriter;
use Espo\Modules\CalendarFeed\Tools\Ics\DescriptionBuilder;
use Espo\Modules\CalendarFeed\Tools\Ics\EventData;
use Espo\Modules\CalendarFeed\Tools\Ics\HtmlToText;
use Espo\Modules\CalendarFeed\Tools\Ics\Participant;
use ICal\ICal;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use DateTimeZone;

class CalendarWriterTest extends TestCase
{
    private CalendarWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new CalendarWriter(new DescriptionBuilder(new HtmlToText()));
    }

    public function testEmptyCalendarIsPublishAndHasNoEvents(): void
    {
        $body = $this->writer->write([])->body;
        $calendar = $this->parse($body);

        $this->assertSame([], $calendar->events());
        $this->assertStringContainsString("METHOD:PUBLISH", $body);
        $this->assertStringContainsString("PRODID:-//EspoCRM//Calendar Feed//EN", $body);
        $this->assertStringNotContainsString('ORGANIZER', $body);
        $this->assertStringNotContainsString("METHOD:REQUEST", $body);
        $this->assertLineEndingsAndLength($body);
    }

    public function testMultipleEventsKeepStableUidsAndStamps(): void
    {
        $stamp = new DateTimeImmutable('2026-01-15 10:00:00', new DateTimeZone('UTC'));
        $body = $this->writer->write([
            $this->event('aaa', 'First', $stamp, 10),
            $this->event('bbb', 'Second', $stamp->modify('+1 hour'), 11),
        ])->body;

        $events = $this->parse($body)->events();

        $this->assertCount(2, $events);
        $this->assertSame('aaa@calendar-feed.espocrm', $events[0]->uid);
        $this->assertSame('bbb@calendar-feed.espocrm', $events[1]->uid);
        $this->assertSame('First', $events[0]->summary);
        $this->assertSame('Second', $events[1]->summary);
        $this->assertStringContainsString('DTSTAMP:20260115T100000Z', $body);
        $this->assertStringContainsString('LAST-MODIFIED:20260115T100000Z', $body);
        $this->assertStringContainsString('SEQUENCE:10', $body);
        $this->assertStringNotContainsString('ORGANIZER', $body);
    }

    public function testSpecialCharactersAndFolding(): void
    {
        $summary = 'Plan, review; next ' . str_repeat('x', 120);
        $description = "Line one\nLine two, with; punctuation";
        $body = $this->writer->write([
            $this->event('fold', $summary, new DateTimeImmutable('2026-02-01 12:00:00', new DateTimeZone('UTC')), 1, $description),
        ])->body;

        $event = $this->parse($body)->events()[0];

        $this->assertSame($summary, $event->summary);
        $this->assertStringContainsString('Line one', (string) $event->description);
        $this->assertStringContainsString('Line two, with; punctuation', (string) $event->description);
        $this->assertLineEndingsAndLength($body);
    }

    public function testHtmlAndUnicodeDescription(): void
    {
        $description = '<p>Hello &amp; welcome</p><div>café 日本語</div><script>alert(1)</script>';
        $body = $this->writer->write([
            $this->event(
                'html',
                'Ünïcöde, summary; ok',
                new DateTimeImmutable('2026-04-01 08:00:00', new DateTimeZone('UTC')),
                3,
                $description,
                [
                    new Participant('User', '1', 'João Santos', null, 'Declined', false),
                ],
            ),
        ])->body;

        $event = $this->parse($body)->events()[0];

        $this->assertSame('Ünïcöde, summary; ok', $event->summary);
        $this->assertStringContainsString('Hello & welcome', (string) $event->description);
        $this->assertStringContainsString('café 日本語', (string) $event->description);
        $this->assertStringNotContainsString('alert', (string) $event->description);
        $this->assertStringContainsString('João Santos (User, Declined)', (string) $event->description);
        $this->assertStringNotContainsString('ATTENDEE', $body);
    }

    public function testTimedUtcAndDstSample(): void
    {
        $body = $this->writer->write([
            $this->event(
                'dst',
                'Spring forward',
                new DateTimeImmutable('2026-03-08 06:30:00', new DateTimeZone('UTC')),
                4,
                null,
                [],
                new DateTimeImmutable('2026-03-08 06:30:00', new DateTimeZone('UTC')),
                new DateTimeImmutable('2026-03-08 07:30:00', new DateTimeZone('UTC')),
            ),
        ])->body;

        $this->assertStringContainsString('DTSTART:20260308T063000Z', $body);
        $this->assertStringContainsString('DTEND:20260308T073000Z', $body);
        $this->assertStringNotContainsString('TZID', $body);

        $event = $this->parse($body)->events()[0];
        $this->assertStringContainsString('20260308', $event->dtstart);
        $this->assertStringContainsString('073000', $event->dtend);
    }

    public function testAllDayExclusiveEnd(): void
    {
        $stamp = new DateTimeImmutable('2026-06-01 00:00:00', new DateTimeZone('UTC'));
        $body = $this->writer->write([
            new EventData(
                'allday',
                'Offsite',
                'Bring notes',
                'Room 4',
                'https://crm.example/#Meeting/view/allday',
                true,
                null,
                null,
                '2026-06-01',
                '2026-06-04',
                $stamp,
                2,
                [],
            ),
        ])->body;

        $this->assertMatchesRegularExpression('/DTSTART;VALUE=DATE:20260601/', $body);
        $this->assertMatchesRegularExpression('/DTEND;VALUE=DATE:20260604/', $body);
        $this->assertStringContainsString('LOCATION:Room 4', $body);

        $event = $this->parse($body)->events()[0];
        $this->assertStringStartsWith('20260601', $event->dtstart);
        $this->assertStringStartsWith('20260604', $event->dtend);
    }

    public function testAttendeePartstatAndNoOrganizer(): void
    {
        $body = $this->writer->write([
            $this->event(
                'people',
                'Standup',
                new DateTimeImmutable('2026-05-01 15:00:00', new DateTimeZone('UTC')),
                8,
                'Agenda',
                [
                    new Participant('User', 'a', 'Ana Silva', 'ana@example.com', null, true),
                    new Participant('User', 'b', 'Pedro Costa', 'pedro@example.com', 'Accepted', false),
                    new Participant('Contact', 'c', 'Mia Costa', 'mia@example.com', 'Tentative', false),
                    new Participant('Lead', 'd', 'João Santos', 'joao@example.com', 'Declined', false),
                    new Participant('Lead', 'e', 'No Mail', null, 'None', false),
                    new Participant('Contact', 'f', 'Unknown', 'unknown@example.com', 'Maybe', false),
                ],
            ),
        ])->body;

        $this->assertStringNotContainsString('ORGANIZER', $body);
        $this->assertStringContainsString('METHOD:PUBLISH', $body);
        $this->assertStringContainsString('mailto:ana@example.com', $body);
        $this->assertStringContainsString('RSVP=FALSE', $body);
        $this->assertStringContainsString('PARTSTAT=ACCEPTED', $body);
        $this->assertStringContainsString('PARTSTAT=TENTATIVE', $body);
        $this->assertStringContainsString('PARTSTAT=DECLINED', $body);
        $this->assertDoesNotMatchRegularExpression('/mailto:ana@example.com[^\\r\\n]*PARTSTAT/', $body);
        $this->assertDoesNotMatchRegularExpression('/mailto:unknown@example.com[^\\r\\n]*PARTSTAT/', $body);
        $this->assertStringNotContainsString('mailto:dummy', $body);

        $unfolded = preg_replace("/\r\n[ \t]/", '', $body) ?? $body;
        $parsed = (string) $this->parse($body)->events()[0]->description;

        $this->assertStringContainsString('Ana Silva (Assigned User) — ana@example.com', $unfolded);
        $this->assertStringContainsString('Pedro Costa (User, Accepted)', $parsed);
        $this->assertStringContainsString('João Santos (Lead, Declined)', $parsed);
        $this->assertStringContainsString('No Mail (Lead)', $unfolded);
        $this->assertStringNotContainsString('No Mail (Lead) —', $unfolded);
        $this->assertStringContainsString('Unknown (Contact) — unknown@example.com', $unfolded);
        $this->assertStringNotContainsString('(Contact, Maybe)', $parsed);
        $this->assertStringNotContainsString('(Contact, Maybe)', $unfolded);
    }

    /**
     * @param list<Participant> $participants
     */
    private function event(
        string $id,
        string $summary,
        DateTimeImmutable $stamp,
        int $sequence,
        ?string $description = null,
        array $participants = [],
        ?DateTimeImmutable $start = null,
        ?DateTimeImmutable $end = null,
    ): EventData {
        $start ??= new DateTimeImmutable('2026-05-04 13:00:00', new DateTimeZone('UTC'));
        $end ??= $start->modify('+1 hour');

        return new EventData(
            $id,
            $summary,
            $description ?? '',
            null,
            'https://crm.example/#Meeting/view/' . $id,
            false,
            $start,
            $end,
            null,
            null,
            $stamp,
            $sequence,
            $participants,
        );
    }

    private function parse(string $body): ICal
    {
        $calendar = new ICal();
        $calendar->initString($body);

        return $calendar;
    }

    private function assertLineEndingsAndLength(string $body): void
    {
        $this->assertStringContainsString("\r\n", $body);
        $this->assertDoesNotMatchRegularExpression("/(?<!\r)\n/", $body);

        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), $line);
        }
    }
}
