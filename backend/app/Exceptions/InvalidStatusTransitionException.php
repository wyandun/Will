<?php

namespace App\Exceptions;

use App\Enums\NewsArticleStatus;

class InvalidStatusTransitionException extends \RuntimeException
{
    public function __construct(private readonly NewsArticleStatus $status, string $action)
    {
        parent::__construct($status->transitionErrorMessage($action));
    }

    public function getStatus(): NewsArticleStatus
    {
        return $this->status;
    }
}
