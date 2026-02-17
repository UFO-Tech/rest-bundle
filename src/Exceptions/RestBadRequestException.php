<?php

namespace Ufo\RestBundle\Exceptions;

use Ufo\RpcError\RpcRuntimeException;

class RestBadRequestException extends RpcRuntimeException implements RestErrorInterface
{
    protected $message = 'Rest bad request error';
    protected $code = 400;
}