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

/**
 * A person who should appear on a meeting in the feed.
 */
final class Participant
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $status,
        public readonly bool $assigned,
    ) {}
}
