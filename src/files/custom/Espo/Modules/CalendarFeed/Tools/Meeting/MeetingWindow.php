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

namespace Espo\Modules\CalendarFeed\Tools\Meeting;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config;
use Espo\Modules\CalendarFeed\Tools\Settings\FeedSettings;
use RuntimeException;

/**
 * The UTC window a feed covers.
 */
class MeetingWindow
{
    public function __construct(private Config $config) {}

    /**
     * @return array{start: string, end: string, includeNotHeld: bool}
     */
    public function current(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $past = $this->months(FeedSettings::PAST_MONTHS, FeedSettings::DEFAULT_PAST);
        $future = $this->months(FeedSettings::FUTURE_MONTHS, FeedSettings::DEFAULT_FUTURE);

        $start = $now->modify('-' . $past . ' months');
        $end = $now->modify('+' . $future . ' months');

        if ($start === false || $end === false) {
            throw new RuntimeException('Could not calculate the calendar window.');
        }

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'includeNotHeld' => (bool) $this->config->get(FeedSettings::INCLUDE_NOT_HELD, false),
        ];
    }

    private function months(string $key, int $default): int
    {
        $value = (int) $this->config->get($key, $default);

        if ($value < 0) {
            return 0;
        }

        if ($value > FeedSettings::MAX_MONTHS) {
            return FeedSettings::MAX_MONTHS;
        }

        return $value;
    }
}
