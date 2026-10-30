<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\CalendarFeed;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\ServiceContainer;
use Espo\Modules\CalendarFeed\Tools\Settings\Api\GetSettings;
use Espo\Modules\CalendarFeed\Tools\Settings\Api\PutSettings;
use Espo\Modules\CalendarFeed\Tools\Subscription\Api\PostRegenerate;
use Espo\Modules\CalendarFeed\Tools\Subscription\Api\PostRevoke;
use tests\integration\Core\BaseTestCase;

class FeedTokenTest extends BaseTestCase
{
    use FeedTestSupport;

    public function testTokenIsShownOnceAndStaysOutOfTheApi(): void
    {
        $ana = $this->makeUser('ana-token', 'Ana', 'Silva', 'ana.token@example.com', $this->meetingAcl());
        $service = $this->getContainer()->getByClass(ServiceContainer::class)->get('CalendarFeedSubscription');
        $created = $service->create((object) [
            'name' => 'Phone',
            'userId' => $ana->getId(),
        ]);

        $url = $created->getEntity()->get('subscriptionUrl');
        $this->assertIsString($url);
        $token = $this->tokenFromUrl($url);

        $stored = $this->getEntityManager()->getEntityById('CalendarFeedSubscription', $created->getEntity()->getId());
        $this->assertNotNull($stored);
        $this->assertSame(64, strlen((string) $stored->get('tokenHash')));
        $this->assertNotSame($token, $stored->get('tokenHash'));
        $this->assertStringNotContainsString($token, (string) $stored->get('tokenHash'));

        $read = $service->read($created->getEntity()->getId())->getEntity();
        $this->assertNull($read->get('tokenHash'));
        $this->assertNull($read->get('subscriptionUrl'));

        $this->makeMeeting('Visible', $ana->getId(), $this->at('+2 days'), $this->at('+2 days +1 hour'));
        $this->assertSame(200, $this->feed($token)->getStatusCode());

        $regenerated = $this->getInjectableFactory()
            ->create(PostRegenerate::class)
            ->process($this->createRequest('POST', [], [], null, ['id' => $created->getEntity()->getId()]));

        $payload = json_decode($this->body($regenerated), true);
        $newToken = $this->tokenFromUrl($payload['subscriptionUrl']);

        $this->assertNotSame($token, $newToken);
        $this->assertSame(404, $this->feed($token)->getStatusCode());
        $this->assertSame(200, $this->feed($newToken)->getStatusCode());

        $this->getInjectableFactory()
            ->create(PostRevoke::class)
            ->process($this->createRequest('POST', [], [], null, ['id' => $created->getEntity()->getId()]));

        $this->assertTokenHidden($newToken, $this->feed($newToken));
    }

    public function testInvalidTokensLookTheSame(): void
    {
        $ana = $this->makeUser('ana-invalid', 'Ana', 'Silva', 'ana.invalid@example.com', $this->meetingAcl());
        $token = $this->subscribe($ana->getId());

        $ana->set('isActive', false);
        $this->getEntityManager()->saveEntity($ana);
        $this->assertTokenHidden($token, $this->feed($token));

        $ana->set('isActive', true);
        $this->getEntityManager()->saveEntity($ana);
        $this->assertSame(200, $this->feed($token)->getStatusCode());

        $this->getEntityManager()->removeEntity($ana);
        $this->assertTokenHidden($token, $this->feed($token));

        $bogus = 'not-a-real-token-value-1234567890';
        $this->assertTokenHidden($bogus, $this->feed($bogus));
        $this->assertTokenHidden('', $this->feed(''));
    }

    public function testRegularUsersCannotManageSubscriptionsOrSettings(): void
    {
        $ana = $this->makeUser('ana-acl', 'Ana', 'Silva', 'ana.acl@example.com', $this->meetingAcl());

        $this->authenticate($ana->get('userName'));
        $this->reCreateApplication();

        $service = $this->getContainer()->getByClass(ServiceContainer::class)->get('CalendarFeedSubscription');

        $this->assertForbidden(
            function () use ($service, $ana): void {
                $service->create((object) [
                    'name' => 'Nope',
                    'userId' => $ana->getId(),
                ]);
            },
            'A regular user must not create a subscription.',
        );

        $this->assertForbidden(
            function (): void {
                $this->getInjectableFactory()->create(GetSettings::class)->process($this->createRequest('GET'));
            },
            'A regular user must not read feed settings.',
        );

        $this->assertForbidden(
            function (): void {
                $this->getInjectableFactory()->create(PutSettings::class)->process($this->createRequest(
                    'PUT',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['pastMonths' => 1, 'futureMonths' => 1, 'includeNotHeld' => true], JSON_THROW_ON_ERROR),
                ));
            },
            'A regular user must not change feed settings.',
        );
    }

    public function testSettingsRejectOutOfRangeMonths(): void
    {
        $action = $this->getInjectableFactory()->create(PutSettings::class);

        $this->expectException(\Espo\Core\Exceptions\BadRequest::class);

        $action->process($this->createRequest(
            'PUT',
            [],
            ['Content-Type' => 'application/json'],
            json_encode(['pastMonths' => 121, 'futureMonths' => 24, 'includeNotHeld' => false], JSON_THROW_ON_ERROR),
        ));
    }

    private function assertForbidden(callable $action, string $message): void
    {
        try {
            $action();
        } catch (Forbidden) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail($message);
    }

    private function assertTokenHidden(string $token, $response): void
    {
        $this->assertSame(404, $response->getStatusCode());

        if ($token === '') {
            $this->assertSame('', $this->body($response));

            return;
        }

        $this->assertStringNotContainsString($token, $this->body($response));

        foreach ($response->getHeaderNames() as $name) {
            $this->assertStringNotContainsString($token, implode(' ', $response->getHeaderAsArray($name)));
        }

        $log = '';

        foreach (glob('data/logs/*') ?: [] as $file) {
            if (is_file($file)) {
                $log .= (string) file_get_contents($file);
            }
        }

        if ($token !== '') {
            $this->assertStringNotContainsString($token, $log);
        }
    }
}
