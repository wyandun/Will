<?php

namespace App\Exceptions;

class ArticleAlreadyPublishedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Article is already published.');
    }
}
