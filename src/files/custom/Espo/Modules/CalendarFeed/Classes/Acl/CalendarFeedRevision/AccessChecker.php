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

namespace Espo\Modules\CalendarFeed\Classes\Acl\CalendarFeedRevision;

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * Revision rows are internal. The feed reads them through the entity manager.
 *
 * @implements AccessEntityCREDChecker<Entity>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    public function check(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkCreate(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkEdit(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkDelete(User $user, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        return false;
    }
}
