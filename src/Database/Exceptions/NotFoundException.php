<?php
declare(strict_types=1);

namespace Uno\Database\Exceptions;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'resource not found', string $errorCode = 'not_found')
    {
        parent::__construct(404, $errorCode, $message);
    }
}
