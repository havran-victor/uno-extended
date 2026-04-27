<?php
declare(strict_types=1);

namespace Uno\Database\Exceptions;

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'forbidden', string $errorCode = 'forbidden')
    {
        parent::__construct(403, $errorCode, $message);
    }
}
