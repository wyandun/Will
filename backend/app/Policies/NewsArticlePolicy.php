<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\NewsArticle;
use App\Models\User;

class NewsArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY, Role::ADMIN_SM]);
    }

    public function fetchAny(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::ADMIN_SM]);
    }

    public function publish(User $user, NewsArticle $newsArticle): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::ADMIN_SM]);
    }

    public function reject(User $user, NewsArticle $newsArticle): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::ADMIN_SM]);
    }
}
