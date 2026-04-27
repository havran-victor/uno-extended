<?php
declare(strict_types=1);

namespace Uno\Database\Exceptions;

final class ConflictException extends HttpException
{
    public function __construct(string $message = 'conflict', string $errorCode = 'conflict')
    {
        parent::__construct(409, $errorCode, $message);
    }
}
