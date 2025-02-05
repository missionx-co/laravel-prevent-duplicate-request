<?php

namespace MissionX\LaravelPreventDuplicateRequest\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class MissingIdempotencyKeyException extends HttpException
{
    public function __construct()
    {
        parent::__construct(400);
    }
}
