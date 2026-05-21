<?php

namespace App\Enums;

enum NewsArticleStatus: string
{
    case PendingAi = 'pending_ai';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';

    public function canBePublished(): bool
    {
        return $this === self::PendingReview;
    }

    public function canBeRejected(): bool
    {
        return $this === self::PendingReview;
    }

    public function transitionErrorMessage(string $action): string
    {
        return match ($this) {
            self::Published => 'Article is already published.',
            self::Rejected => 'Article is already rejected.',
            default => "Only articles pending review can be {$action}.",
        };
    }
}
