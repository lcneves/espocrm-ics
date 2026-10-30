<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\CalendarFeed;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Api\ResponseWrapper;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\User;
use Espo\Modules\CalendarFeed\Tools\Feed\Api\GetFeed;
use Espo\Modules\CalendarFeed\Tools\Settings\Api\GetSettings;
use Espo\Modules\CalendarFeed\Tools\Settings\Api\PutSettings;
use Espo\Modules\CalendarFeed\Tools\Settings\FeedSettings;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use ICal\ICal;
use tests\integration\Core\BaseTestCase;

trait FeedTestSupport
{
    protected function makeUser(
        string $userName,
        string $firstName,
        string $lastName,
        ?string $email,
        array $scopes,
    ): User {
        $data = [
            'userName' => $userName,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        if ($email !== null) {
            $data['emailAddress'] = $email;
        }

        return $this->createUser($data, [
            'data' => $scopes,
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function makeMeeting(
        string $name,
        string $assignedUserId,
        string $start,
        string $end,
        array $extra = [],
    ): Entity {
        $meeting = $this->getEntityManager()->getNewEntity('Meeting');
        $meeting->set(array_merge([
            'name' => $name,
            'status' => 'Planned',
            'dateStart' => $start,
            'dateEnd' => $end,
            'assignedUserId' => $assignedUserId,
        ], $extra));

        $this->getEntityManager()->saveEntity($meeting);

        return $meeting;
    }

    protected function subscribe(string $userId, string $name = 'Phone'): string
    {
        $this->ensureSiteUrl();

        $result = $this->getContainer()
            ->getByClass(ServiceContainer::class)
            ->get('CalendarFeedSubscription')
            ->create((object) [
                'name' => $name,
                'userId' => $userId,
            ]);

        $url = $result->getEntity()->get('subscriptionUrl');

        $this->assertIsString($url);

        $token = $this->tokenFromUrl($url);

        $this->assertNotSame('', $token, 'URL was: ' . $url);
        $this->assertNull($result->getEntity()->get('tokenHash'));

        return $token;
    }

    protected function feed(string $token): ResponseWrapper
    {
        $action = $this->getInjectableFactory()->create(GetFeed::class);
        $response = $action->process($this->createRequest('GET', [], [], null, ['token' => $token]));

        $this->assertInstanceOf(ResponseWrapper::class, $response);

        return $response;
    }

    protected function calendar(string $token): string
    {
        $response = $this->feed($token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/calendar; charset=utf-8', $response->getHeader('Content-Type'));
        $this->assertSame('private, no-store, max-age=0', $response->getHeader('Cache-Control'));
        $this->assertNotNull($response->getHeader('Last-Modified'));

        return $this->body($response);
    }

    protected function body(ResponseWrapper $response): string
    {
        $stream = $response->getBody();
        $stream->rewind();

        return $stream->getContents();
    }

    protected function unfold(string $ics): string
    {
        return preg_replace("/\r\n[ \t]/", '', $ics) ?? $ics;
    }

    protected function parse(string $ics): ICal
    {
        $calendar = new ICal();
        $calendar->initString($ics);

        return $calendar;
    }

    protected function at(string $modify): string
    {
        $value = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($modify);

        $this->assertInstanceOf(DateTimeImmutable::class, $value);

        return $value->format('Y-m-d H:i:s');
    }

    protected function meetingAcl(): array
    {
        return [
            'Meeting' => ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no'],
            'Contact' => ['create' => 'yes', 'read' => 'yes', 'edit' => 'yes', 'delete' => 'no'],
            'Lead' => ['create' => 'yes', 'read' => 'yes', 'edit' => 'yes', 'delete' => 'no'],
            'Account' => ['create' => 'yes', 'read' => 'yes', 'edit' => 'no', 'delete' => 'no'],
            'User' => ['create' => 'no', 'read' => 'yes', 'edit' => 'no', 'delete' => 'no'],
        ];
    }

    protected function ensureSiteUrl(): void
    {
        if ($this->getConfig()->get('siteUrl') === 'http://localhost') {
            return;
        }

        $writer = $this->getInjectableFactory()->create(ConfigWriter::class);
        $writer->set('siteUrl', 'http://localhost');
        $writer->save();
    }

    protected function tokenFromUrl(string $url): string
    {
        $marker = '/api/v1/CalendarFeed/';
        $position = strpos($url, $marker);

        if ($position === false) {
            return '';
        }

        $token = substr($url, $position + strlen($marker));
        $token = explode('?', $token, 2)[0];

        return rawurldecode($token);
    }

    protected function eventBlock(string $ics, string $summary): string
    {
        $parts = explode('BEGIN:VEVENT', $ics);

        foreach ($parts as $part) {
            if (str_contains($part, 'SUMMARY:' . $summary)) {
                return $part;
            }
        }

        $this->fail('Missing event ' . $summary);
    }

    protected function setIncludeNotHeld(bool $include): void
    {
        $writer = $this->getInjectableFactory()->create(ConfigWriter::class);
        $writer->set(FeedSettings::INCLUDE_NOT_HELD, $include);
        $writer->save();
    }
}
