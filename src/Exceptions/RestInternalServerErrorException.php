<?php

namespace Ufo\RestBundle\Exceptions;

use Ufo\RpcError\RpcInternalException;

class RestInternalServerErrorException extends RpcInternalException implements RestErrorInterface
{
    protected $message = 'Internal Server Error';
    protected $code = 500;
}