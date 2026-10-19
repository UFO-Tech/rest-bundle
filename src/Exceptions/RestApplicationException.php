<?php

namespace Ufo\RestBundle\Exceptions;

use Throwable;
use Ufo\RpcError\CustomApplicationException;

class RestApplicationException extends CustomApplicationException implements RestErrorInterface
{
    protected $message = 'Rest application error';
    protected $code = 400;

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
