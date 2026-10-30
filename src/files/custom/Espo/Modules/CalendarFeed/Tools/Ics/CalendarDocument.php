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

namespace Espo\Modules\CalendarFeed\Tools\Ics;

use DateTimeImmutable;

final class CalendarDocument
{
    public function __construct(
        public readonly string $body,
        public readonly DateTimeImmutable $lastModified,
    ) {}
}
