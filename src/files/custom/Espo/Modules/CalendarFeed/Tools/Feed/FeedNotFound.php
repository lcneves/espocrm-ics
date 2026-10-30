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

namespace Espo\Modules\CalendarFeed\Tools\Feed;

use RuntimeException;

/**
 * The token does not belong to an active subscription.
 *
 * The message is intentionally empty so it cannot leak into logs or headers.
 */
final class FeedNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('');
    }
}
