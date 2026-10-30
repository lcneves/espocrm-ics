<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\CalendarFeed;

use tests\integration\Core\BaseTestCase;

class FeedGuestTest extends BaseTestCase
{
    use FeedTestSupport;

    protected ?string $pathToFiles = 'Modules/CalendarFeed/custom-guest';

    public function testCustomAttendeeLinkIsExported(): void
    {
        $ana = $this->makeUser('ana-guest', 'Ana', 'Silva', 'ana.guest@example.com', array_merge(
            $this->meetingAcl(),
            [
                'CalendarFeedGuest' => [
                    'create' => 'yes',
                    'read' => 'yes',
                    'edit' => 'no',
                    'delete' => 'no',
                ],
            ],
        ));

        $meeting = $this->makeMeeting('With a guest', $ana->getId(), $this->at('+2 days'), $this->at('+2 days +1 hour'));

        $guest = $this->getEntityManager()->getNewEntity('CalendarFeedGuest');
        $guest->set([
            'name' => 'Renée Guest',
            'email' => 'renee@example.com',
        ]);
        $this->getEntityManager()->saveEntity($guest);
        $this->getEntityManager()->getRelation($meeting, 'guests')->relate($guest, ['status' => 'Accepted']);

        $ics = str_replace('\\,', ',', $this->unfold($this->calendar($this->subscribe($ana->getId()))));

        $this->assertStringContainsString('Renée Guest (CalendarFeedGuest, Accepted) — renee@example.com', $ics);
        $this->assertStringContainsString('PARTSTAT=ACCEPTED', $ics);
        $this->assertStringContainsString('mailto:renee@example.com', $ics);
    }
}
