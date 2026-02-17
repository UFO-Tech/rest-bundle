<?php

namespace Ufo\RestBundle\Exceptions;

use Ufo\RpcError\RpcTokenNotSentException;

class RestUnauthorizedException extends RpcTokenNotSentException implements RestErrorInterface
{
    protected $code = 401;
}