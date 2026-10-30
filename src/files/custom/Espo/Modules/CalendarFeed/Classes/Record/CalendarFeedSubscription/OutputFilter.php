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

namespace Espo\Modules\CalendarFeed\Classes\Record\CalendarFeedSubscription;

use Espo\Core\Record\Output\Filter;
use Espo\ORM\Entity;

/**
 * @implements Filter<Entity>
 */
class OutputFilter implements Filter
{
    public function filter(Entity $entity): void
    {
        $entity->clear('tokenHash');
    }
}
