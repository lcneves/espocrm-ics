<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\CalendarFeed;

use Espo\Modules\CalendarFeed\Tools\Ics\DescriptionBuilder;
use Espo\Modules\CalendarFeed\Tools\Ics\EventTiming;
use Espo\Modules\CalendarFeed\Tools\Ics\HtmlToText;
use Espo\Modules\CalendarFeed\Tools\Ics\Participant;
use Espo\Modules\CalendarFeed\Tools\Ics\RevisionStamp;
use Espo\Modules\CalendarFeed\Tools\Participant\AttendeeLinkCatalog;
use Espo\Modules\CalendarFeed\Tools\Subscription\TokenGenerator;
use PHPUnit\Framework\TestCase;

class FeedPartsTest extends TestCase
{
    public function testTokenIs256BitsAndOnlyTheHashIsComparable(): void
    {
        $tokens = new TokenGenerator();
        $first = $tokens->generate();
        $second = $tokens->generate();

        $this->assertSame(43, strlen($first));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $first);
        $this->assertNotSame($first, $second);
        $this->assertSame(64, strlen($tokens->hash($first)));
        $this->assertNotSame($first, $tokens->hash($first));
        $this->assertSame($tokens->hash($first), $tokens->hash($first));
    }

    public function testHtmlToTextLeavesPlainTextAlone(): void
    {
        $converter = new HtmlToText();

        $this->assertSame('Tom &amp; Jerry', $converter->convert('Tom &amp; Jerry'));
        $this->assertSame("Hello\nWorld", $converter->convert('<p>Hello</p><div>World</div>'));
        $this->assertSame('A & B', $converter->convert('<p>A &amp; B</p>'));
        $this->assertSame('Safe', $converter->convert('<script>alert(1)</script>Safe'));
    }

    public function testDescriptionOrdersParticipants(): void
    {
        $text = (new DescriptionBuilder(new HtmlToText()))->build('Notes', [
            new Participant('Lead', '3', 'João Santos', null, 'Declined', false),
            new Participant('User', '1', 'Ana Silva', 'ana@example.com', 'Accepted', true),
            new Participant('User', '2', 'Pedro Costa', 'pedro@example.com', 'None', false),
        ]);

        $this->assertStringStartsWith("Notes\n\nParticipants:\n", $text);
        $this->assertStringContainsString('- Ana Silva (Assigned User, Accepted) — ana@example.com', $text);
        $this->assertStringContainsString('- Pedro Costa (User) — pedro@example.com', $text);
        $this->assertStringContainsString('- João Santos (Lead, Declined)', $text);
        $this->assertStringNotContainsString('dummy@', $text);
    }

    public function testEventTimingUsesUtcAndExclusiveAllDayEnd(): void
    {
        $timing = new EventTiming('America/New_York');
        $timed = $timing->map(false, null, null, '2026-03-08 06:30:00', '2026-03-08 07:30:00');

        $this->assertNotNull($timed);
        $this->assertFalse($timed['allDay']);
        $this->assertSame('2026-03-08 06:30:00', $timed['start']?->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $timed['start']?->getTimezone()->getName());
        $this->assertSame('2026-03-08 07:30:00', $timed['end']?->format('Y-m-d H:i:s'));

        $fall = $timing->map(false, null, null, '2026-11-01 05:30:00', '2026-11-01 06:30:00');
        $this->assertSame('2026-11-01 05:30:00', $fall['start']?->format('Y-m-d H:i:s'));

        $allDay = $timing->map(true, '2026-06-01', '2026-06-03', '2026-06-01 04:00:00', '2026-06-04 04:00:00');
        $this->assertSame('2026-06-01', $allDay['startDate']);
        $this->assertSame('2026-06-04', $allDay['endDateExclusive']);

        $derived = $timing->map(true, null, null, '2026-06-01 04:00:00', '2026-06-04 04:00:00');
        $this->assertSame('2026-06-01', $derived['startDate']);
        $this->assertSame('2026-06-04', $derived['endDateExclusive']);
    }

    public function testRevisionStampUsesTheLaterValueAndDoesNotMoveOnRead(): void
    {
        $first = RevisionStamp::resolve('2026-01-01 00:00:00', null, null);
        $second = RevisionStamp::resolve('2026-01-01 00:00:00', null, null);

        $this->assertEquals($first['stamp'], $second['stamp']);
        $this->assertSame($first['sequence'], $second['sequence']);

        $bumped = RevisionStamp::resolve('2026-01-01 00:00:00', '2026-02-01 00:00:00', $first['sequence'] + 5);

        $this->assertGreaterThan($first['stamp'], $bumped['stamp']);
        $this->assertGreaterThan($first['sequence'], $bumped['sequence']);
    }

    public function testAttendeeCatalogIncludesCustomStatusLinksAndSkipsParents(): void
    {
        $links = AttendeeLinkCatalog::collect([
            'users' => ['type' => 'hasMany', 'entity' => 'User'],
            'contacts' => ['type' => 'hasMany', 'entity' => 'Contact'],
            'leads' => ['type' => 'hasMany', 'entity' => 'Lead'],
            'account' => ['type' => 'belongsTo', 'entity' => 'Account'],
            'parent' => ['type' => 'belongsToParent'],
            'teams' => ['type' => 'hasMany', 'entity' => 'Team'],
            'assignedUser' => ['type' => 'belongsTo', 'entity' => 'User'],
            'guests' => [
                'type' => 'hasMany',
                'entity' => 'CalendarFeedGuest',
                'additionalColumns' => ['status' => ['type' => 'varchar']],
                'columnAttributeMap' => ['status' => 'acceptanceStatus'],
            ],
            'patients' => [
                'type' => 'hasMany',
                'entity' => 'Patient',
            ],
        ], [
            'User' => 'users',
            'Contact' => 'contacts',
            'Lead' => 'leads',
        ]);

        $this->assertSame(['users', 'contacts', 'leads', 'guests'], $links);
    }
}
