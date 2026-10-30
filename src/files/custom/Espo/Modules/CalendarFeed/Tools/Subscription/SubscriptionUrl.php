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

namespace Espo\Modules\CalendarFeed\Tools\Subscription;

use Espo\Core\Utils\Config;

final class SubscriptionUrl
{
    public function __construct(private Config $config) {}

    public function forToken(string $token): string
    {
        return $this->siteUrl() . '/api/v1/CalendarFeed/' . $token;
    }

    public function meeting(string $id): string
    {
        return $this->siteUrl() . '/#Meeting/view/' . rawurlencode($id);
    }

    private function siteUrl(): string
    {
        return rtrim((string) $this->config->get('siteUrl'), '/');
    }
}
