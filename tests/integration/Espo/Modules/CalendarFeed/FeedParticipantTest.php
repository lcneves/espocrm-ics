<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\CalendarFeed;

use Espo\Core\HookManager;
use tests\integration\Core\BaseTestCase;

class FeedParticipantTest extends BaseTestCase
{
    use FeedTestSupport;

    public function testParticipantsEmailsAndAcceptance(): void
    {
        $ana = $this->makeUser('ana-people', 'Ana', 'Silva', 'ana@example.com', $this->meetingAcl());
        $pedro = $this->makeUser('pedro', 'Pedro', 'Costa', 'pedro@example.com', $this->meetingAcl());
        $restricted = $this->makeUser('restricted', 'Rita', 'Limited', 'rita@example.com', [
            'Meeting' => ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no'],
            'Contact' => ['create' => 'no', 'read' => 'no', 'edit' => 'no', 'delete' => 'no'],
            'Lead' => ['create' => 'no', 'read' => 'no', 'edit' => 'no', 'delete' => 'no'],
        ]);

        $meeting = $this->makeMeeting(
            'Plan, review; next',
            $ana->getId(),
            $this->at('+2 days'),
            $this->at('+2 days +1 hour'),
            ['description' => '<p>Hello &amp; welcome</p><div>café 日本語</div>'],
        );

        $this->getEntityManager()->getRelation($meeting, 'users')->relate($pedro, ['status' => 'Accepted']);

        $contact = $this->getEntityManager()->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'João',
            'lastName' => 'Santos',
            'emailAddress' => 'joao@example.com',
        ]);
        $this->getEntityManager()->saveEntity($contact);
        $this->getEntityManager()->getRelation($meeting, 'contacts')->relate($contact, ['status' => 'Declined']);

        $lead = $this->getEntityManager()->getNewEntity('Lead');
        $lead->set([
            'firstName' => 'No',
            'lastName' => 'Mail',
        ]);
        $this->getEntityManager()->saveEntity($lead);
        $this->getEntityManager()->getRelation($meeting, 'leads')->relate($lead, ['status' => 'None']);

        $secret = $this->getEntityManager()->getNewEntity('Contact');
        $secret->set([
            'firstName' => 'Secret',
            'lastName' => 'Contact',
            'emailAddress' => 'secret@example.com',
        ]);
        $this->getEntityManager()->saveEntity($secret);

        $hidden = $this->makeMeeting(
            'Hidden email',
            $restricted->getId(),
            $this->at('+3 days'),
            $this->at('+3 days +1 hour'),
        );
        $this->getEntityManager()->getRelation($hidden, 'contacts')->relate($secret, ['status' => 'Accepted']);

        $unknown = $this->makeMeeting(
            'Unknown status',
            $ana->getId(),
            $this->at('+4 days'),
            $this->at('+4 days +1 hour'),
        );
        $this->getEntityManager()->getRelation($unknown, 'users')->relate($pedro, ['status' => 'Maybe']);

        $ics = $this->unfold($this->calendar($this->subscribe($ana->getId())));
        $plain = str_replace(['\\,', '\\;'], [',', ';'], $ics);
        $event = $this->eventBySummary($ics, 'Plan, review; next');

        $this->assertStringContainsString('Hello & welcome', (string) $event->description);
        $this->assertStringContainsString('café 日本語', (string) $event->description);
        $this->assertStringContainsString('Ana Silva (Assigned User)', $plain);
        $this->assertSame(1, substr_count((string) $event->description, 'Ana Silva'));
        $this->assertStringContainsString('Pedro Costa (User, Accepted) — pedro@example.com', $plain);
        $this->assertStringContainsString('João Santos (Contact, Declined) — joao@example.com', $plain);
        $this->assertStringContainsString('No Mail (Lead)', $plain);
        $this->assertStringNotContainsString('No Mail (Lead) —', $plain);
        $this->assertStringContainsString('PARTSTAT=ACCEPTED', $ics);
        $this->assertStringContainsString('PARTSTAT=DECLINED', $ics);
        $this->assertStringNotContainsString('ORGANIZER', $ics);
        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        $this->assertStringContainsString('RSVP=FALSE', $ics);
        $this->assertDoesNotMatchRegularExpression('/mailto:pedro@example.com[^\r\n]*PARTSTAT=ACCEPTED.*PARTSTAT/', $ics);

        $unknownIcs = $this->unfold($this->calendar($this->subscribe($ana->getId(), 'Second')));
        $this->assertStringContainsString('Unknown status', $unknownIcs);
        $this->assertDoesNotMatchRegularExpression('/Unknown status[\s\S]*PARTSTAT/', $this->eventBlock($unknownIcs, 'Unknown status'));

        $hiddenIcs = $this->unfold($this->calendar($this->subscribe($restricted->getId(), 'Rita phone')));
        $this->assertStringContainsString('Secret Contact', $hiddenIcs);
        $this->assertStringNotContainsString('secret@example.com', $hiddenIcs);

        $modifiedAt = $this->getEntityManager()->getEntityById('Meeting', $meeting->getId())?->get('modifiedAt');
        $firstToken = $this->subscribe($ana->getId(), 'Repeat');
        $once = $this->calendar($firstToken);
        $twice = $this->calendar($firstToken);
        $this->assertSame($once, $twice);
        $this->assertSame(
            $modifiedAt,
            $this->getEntityManager()->getEntityById('Meeting', $meeting->getId())?->get('modifiedAt'),
        );
    }

    public function testAcceptanceChangeDoesNotTouchTheMeeting(): void
    {
        $ana = $this->makeUser('ana-accept', 'Ana', 'Silva', 'ana.accept@example.com', $this->meetingAcl());
        $meeting = $this->makeMeeting('Acceptance', $ana->getId(), $this->at('+2 days'), $this->at('+2 days +1 hour'));
        $token = $this->subscribe($ana->getId());
        $before = $this->calendar($token);
        $modifiedAt = $this->getEntityManager()->getEntityById('Meeting', $meeting->getId())?->get('modifiedAt');

        $this->getEntityManager()->getRelation($meeting, 'users')->updateColumns($ana, ['status' => 'Accepted']);
        $this->getContainer()->getByClass(HookManager::class)->process(
            'Meeting',
            'afterConfirmation',
            $meeting,
            [],
            ['link' => 'users', 'status' => 'Accepted'],
        );

        $this->assertSame(
            $modifiedAt,
            $this->getEntityManager()->getEntityById('Meeting', $meeting->getId())?->get('modifiedAt'),
        );

        $after = $this->unfold($this->calendar($token));

        $this->assertNotSame($before, $after);
        $this->assertStringContainsString('Ana Silva (Assigned User, Accepted)', str_replace('\\,', ',', $after));
        $this->assertStringContainsString('PARTSTAT=ACCEPTED', $after);
        $this->assertGreaterThan($this->sequence($before), $this->sequence($after));
    }

    private function sequence(string $ics): int
    {
        $this->assertSame(1, preg_match('/SEQUENCE:(\d+)/', $ics, $matches));

        return (int) $matches[1];
    }

    private function eventBySummary(string $ics, string $summary): object
    {
        foreach ($this->parse($ics)->events() as $event) {
            if ($event->summary === $summary) {
                return $event;
            }
        }

        $this->fail('Missing event ' . $summary);
    }

    private function eventBlock(string $ics, string $summary): string
    {
        $parts = explode('BEGIN:VEVENT', $ics);

        foreach ($parts as $part) {
            if (str_contains($part, 'SUMMARY:' . $summary)) {
                return $part;
            }
        }

        $this->fail('Missing event ' . $summary);
    }
}
