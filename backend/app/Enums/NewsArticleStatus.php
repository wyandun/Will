<?php

namespace App\Enums;

enum NewsArticleStatus: string
{
    case PendingAi = 'pending_ai';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
}
