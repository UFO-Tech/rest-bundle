<?php

namespace Ufo\RestBundle\Exceptions;

use Ufo\RpcError\RpcCustomApplicationException;

class RestApplicationException extends RpcCustomApplicationException implements RestErrorInterface
{
    protected $message = 'Rest application error';
    protected $code = 400;
}