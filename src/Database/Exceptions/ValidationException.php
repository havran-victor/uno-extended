<?php
declare(strict_types=1);

namespace Uno\Database\Exceptions;

final class ValidationException extends HttpException
{
    public function __construct(string $message = 'invalid request', string $errorCode = 'bad_request')
    {
        parent::__construct(400, $errorCode, $message);
    }
}
