<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\CalendarFeed;

use tests\integration\Core\BaseTestCase;

class FeedSelectionTest extends BaseTestCase
{
    use FeedTestSupport;

    public function testSelectionWindowStatusAndOwner(): void
    {
        $ana = $this->makeUser('ana', 'Ana', 'Silva', 'ana@example.com', $this->meetingAcl());
        $bob = $this->makeUser('bob', 'Bob', 'Other', 'bob@example.com', $this->meetingAcl());

        $assigned = $this->makeMeeting('Assigned', $ana->getId(), $this->at('+2 days'), $this->at('+2 days +1 hour'));
        $attended = $this->makeMeeting('Attended only', $bob->getId(), $this->at('+3 days'), $this->at('+3 days +1 hour'));
        $this->getEntityManager()->getRelation($attended, 'users')->relate($ana, ['status' => 'Accepted']);

        $both = $this->makeMeeting('Both roles', $ana->getId(), $this->at('+4 days'), $this->at('+4 days +1 hour'));
        $this->getEntityManager()->getRelation($both, 'users')->relate($ana, ['status' => 'Tentative']);

        $other = $this->makeMeeting('Someone else', $bob->getId(), $this->at('+5 days'), $this->at('+5 days +1 hour'));
        $old = $this->makeMeeting('Too old', $ana->getId(), $this->at('-14 months'), $this->at('-14 months +1 hour'));
        $future = $this->makeMeeting('Too far', $ana->getId(), $this->at('+25 months'), $this->at('+25 months +1 hour'));
        $spansIn = $this->makeMeeting('Spans into window', $ana->getId(), $this->at('-13 months'), $this->at('+1 day'));
        $spansOut = $this->makeMeeting('Spans out of window', $ana->getId(), $this->at('+1 day'), $this->at('+25 months'));
        $notHeld = $this->makeMeeting(
            'Cancelled',
            $ana->getId(),
            $this->at('+6 days'),
            $this->at('+6 days +1 hour'),
            ['status' => 'Not Held'],
        );
        $deleted = $this->makeMeeting('Deleted', $ana->getId(), $this->at('+7 days'), $this->at('+7 days +1 hour'));
        $this->getEntityManager()->removeEntity($deleted);

        $account = $this->getEntityManager()->getNewEntity('Account');
        $account->set('name', 'Acme Parent Co');
        $this->getEntityManager()->saveEntity($account);
        $assigned->set('accountId', $account->getId());
        $assigned->set('parentType', 'Account');
        $assigned->set('parentId', $account->getId());
        $this->getEntityManager()->saveEntity($assigned);

        $token = $this->subscribe($ana->getId());
        $ics = $this->calendar($token);
        $names = array_map(fn ($event) => $event->summary, $this->parse($ics)->events());

        $this->assertContains('Assigned', $names);
        $this->assertContains('Attended only', $names);
        $this->assertContains('Both roles', $names);
        $this->assertContains('Spans into window', $names);
        $this->assertContains('Spans out of window', $names);
        $this->assertNotContains('Someone else', $names);
        $this->assertNotContains('Too old', $names);
        $this->assertNotContains('Too far', $names);
        $this->assertNotContains('Cancelled', $names);
        $this->assertNotContains('Deleted', $names);
        $plain = $this->unfold($ics);
        $this->assertStringNotContainsString('Acme Parent Co', $plain);

        foreach (['Assigned', 'Both roles', 'Spans into window', 'Spans out of window'] as $summary) {
            $block = $this->eventBlock($plain, $summary);
            $this->assertSame(1, substr_count($block, 'Ana Silva (Assigned User'), $summary . "\n" . $block);
            $this->assertSame(0, substr_count($block, 'Ana Silva (User'), $summary . "\n" . $block);
        }

        $attended = $this->eventBlock($plain, 'Attended only');
        $this->assertSame(1, substr_count($attended, 'Ana Silva (User'), $attended);
        $this->assertStringNotContainsString('Ana Silva (Assigned User', $attended);

        $this->setIncludeNotHeld(true);

        $withNotHeld = array_map(
            fn ($event) => $event->summary,
            $this->parse($this->calendar($token))->events(),
        );

        $this->assertContains('Cancelled', $withNotHeld);

        $admin = $this->createUser([
            'userName' => 'feed-admin',
            'firstName' => 'Ada',
            'lastName' => 'Admin',
            'emailAddress' => 'ada.admin@example.com',
            'type' => 'admin',
        ]);

        $adminMeeting = $this->makeMeeting('Admin only', $admin->getId(), $this->at('+8 days'), $this->at('+8 days +1 hour'));
        $adminToken = $this->subscribe($admin->getId(), 'Admin phone');
        $adminNames = array_map(
            fn ($event) => $event->summary,
            $this->parse($this->calendar($adminToken))->events(),
        );

        $this->assertContains('Admin only', $adminNames);
        $this->assertNotContains('Assigned', $adminNames);
        $this->assertNotContains('Attended only', $adminNames);
        $this->assertSame($adminMeeting->get('name'), 'Admin only');

        $this->setIncludeNotHeld(false);
    }

    public function testAllDayEndIsExclusiveAndTimedEventsStayUtc(): void
    {
        $ana = $this->makeUser('ana-days', 'Ana', 'Silva', 'ana.days@example.com', $this->meetingAcl());

        $this->makeMeeting(
            'Offsite',
            $ana->getId(),
            '2026-06-01 04:00:00',
            '2026-06-04 04:00:00',
            [
                'isAllDay' => true,
                'dateStartDate' => '2026-06-01',
                'dateEndDate' => '2026-06-03',
            ],
        );

        $this->makeMeeting(
            'Spring forward',
            $ana->getId(),
            '2026-03-08 06:30:00',
            '2026-03-08 07:30:00',
        );

        $ics = $this->calendar($this->subscribe($ana->getId()));

        $this->assertMatchesRegularExpression('/DTSTART;VALUE=DATE:20260601/', $ics);
        $this->assertMatchesRegularExpression('/DTEND;VALUE=DATE:20260604/', $ics);
        $this->assertStringContainsString('DTSTART:20260308T063000Z', $ics);
        $this->assertStringContainsString('DTEND:20260308T073000Z', $ics);
        $this->assertStringNotContainsString('LOCATION:', $ics);
    }

    public function testJoinUrlIsUsedAsTheEventUrl(): void
    {
        $ana = $this->makeUser('ana-join', 'Ana', 'Silva', 'ana.join@example.com', $this->meetingAcl());

        $this->makeMeeting('In the office', $ana->getId(), $this->at('+2 days'), $this->at('+2 days +1 hour'));
        $this->makeMeeting(
            'Video call',
            $ana->getId(),
            $this->at('+3 days'),
            $this->at('+3 days +1 hour'),
            ['joinUrl' => 'https://meet.example/abc'],
        );

        $ics = $this->unfold($this->calendar($this->subscribe($ana->getId())));

        $office = $this->eventBlock($ics, 'In the office');
        $this->assertMatchesRegularExpression('~URL;VALUE=URI:http://localhost/#Meeting/view/~', $office);
        $this->assertStringNotContainsString('meet.example', $office);

        $video = $this->eventBlock($ics, 'Video call');
        $this->assertStringContainsString('URL;VALUE=URI:https://meet.example/abc', $video);
        $this->assertStringNotContainsString('#Meeting/view/', $video);
    }
}
