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

namespace Espo\Modules\CalendarFeed\Tools\Settings;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class SettingsService
{
    public function __construct(
        private Config $config,
        private ConfigWriter $configWriter,
    ) {}

    /**
     * @return array{pastMonths: int, futureMonths: int, includeNotHeld: bool}
     */
    public function get(): array
    {
        return [
            'pastMonths' => $this->months(FeedSettings::PAST_MONTHS, FeedSettings::DEFAULT_PAST),
            'futureMonths' => $this->months(FeedSettings::FUTURE_MONTHS, FeedSettings::DEFAULT_FUTURE),
            'includeNotHeld' => (bool) $this->config->get(FeedSettings::INCLUDE_NOT_HELD, false),
        ];
    }

    /**
     * @throws BadRequest
     */
    public function save(mixed $pastMonths, mixed $futureMonths, mixed $includeNotHeld): array
    {
        $past = $this->requireMonths($pastMonths);
        $future = $this->requireMonths($futureMonths);
        $include = $this->requireBool($includeNotHeld);

        $this->configWriter->set(FeedSettings::PAST_MONTHS, $past);
        $this->configWriter->set(FeedSettings::FUTURE_MONTHS, $future);
        $this->configWriter->set(FeedSettings::INCLUDE_NOT_HELD, $include);
        $this->configWriter->save();

        return [
            'pastMonths' => $past,
            'futureMonths' => $future,
            'includeNotHeld' => $include,
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

    /**
     * @throws BadRequest
     */
    private function requireMonths(mixed $value): int
    {
        if (is_int($value)) {
            $months = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $months = (int) $value;
        } else {
            throw new BadRequest('Months must be a whole number from 0 to 120.');
        }

        if ($months < 0 || $months > FeedSettings::MAX_MONTHS) {
            throw new BadRequest('Months must be a whole number from 0 to 120.');
        }

        return $months;
    }

    /**
     * @throws BadRequest
     */
    private function requireBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new BadRequest('Include Not Held must be true or false.');
    }
}
