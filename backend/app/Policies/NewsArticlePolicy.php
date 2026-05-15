<?php

namespace App\Policies;

use App\Models\NewsArticle;
use App\Models\User;

class NewsArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin_sm']);
    }

    public function fetchAny(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin_sm']);
    }

    public function publish(User $user, NewsArticle $newsArticle): bool
    {
        return $user->hasRole(['superadmin', 'admin_sm']);
    }

    public function reject(User $user, NewsArticle $newsArticle): bool
    {
        return $user->hasRole(['superadmin', 'admin_sm']);
    }
}
