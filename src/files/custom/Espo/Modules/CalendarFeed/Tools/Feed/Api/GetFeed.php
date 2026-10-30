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

namespace Espo\Modules\CalendarFeed\Tools\Feed\Api;

use DateTimeZone;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Utils\Log;
use Espo\Modules\CalendarFeed\Tools\Feed\FeedNotFound;
use Espo\Modules\CalendarFeed\Tools\Feed\FeedService;
use Throwable;

/**
 * Public calendar feed.
 *
 * Unknown tokens return an empty 404. Throwing NotFound would put the token
 * into the request log and the X-Status-Reason header.
 */
class GetFeed implements Action
{
    private const CACHE_CONTROL = 'private, no-store, max-age=0';

    public function __construct(
        private FeedService $feed,
        private Log $log,
    ) {}

    public function process(Request $request): Response
    {
        $token = $request->getRouteParam('token') ?? '';

        try {
            $document = $this->feed->render($token);
        } catch (FeedNotFound) {
            return $this->notFound();
        } catch (Throwable $exception) {
            $this->log->error(
                'Calendar feed failed. ' . str_replace($token, '[redacted]', $exception->getMessage()),
            );

            return ResponseComposer::empty()
                ->setStatus(500)
                ->setHeader('Cache-Control', self::CACHE_CONTROL);
        }

        $lastModified = $document->lastModified
            ->setTimezone(new DateTimeZone('GMT'))
            ->format('D, d M Y H:i:s') . ' GMT';

        return ResponseComposer::empty()
            ->setStatus(200)
            ->setHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->setHeader('Cache-Control', self::CACHE_CONTROL)
            ->setHeader('Last-Modified', $lastModified)
            ->writeBody($document->body);
    }

    private function notFound(): Response
    {
        return ResponseComposer::empty()
            ->setStatus(404)
            ->setHeader('Cache-Control', self::CACHE_CONTROL);
    }
}
