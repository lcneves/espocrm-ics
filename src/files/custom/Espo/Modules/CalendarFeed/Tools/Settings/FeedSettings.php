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

final class FeedSettings
{
    public const PAST_MONTHS = 'calendarFeedPastMonths';

    public const FUTURE_MONTHS = 'calendarFeedFutureMonths';

    public const INCLUDE_NOT_HELD = 'calendarFeedIncludeNotHeld';

    public const DEFAULT_PAST = 12;

    public const DEFAULT_FUTURE = 24;

    public const MAX_MONTHS = 120;
}
