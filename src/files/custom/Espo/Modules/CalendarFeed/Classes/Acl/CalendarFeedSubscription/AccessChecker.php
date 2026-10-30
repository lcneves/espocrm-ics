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

namespace Espo\Modules\CalendarFeed\Classes\Acl\CalendarFeedSubscription;

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * @implements AccessEntityCREDChecker<Entity>
 */
class AccessChecker implements AccessEntityCREDChecker
{
    public function check(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkCreate(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkEdit(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkDelete(User $user, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        return $user->isAdmin();
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        return $user->isAdmin();
    }
}
