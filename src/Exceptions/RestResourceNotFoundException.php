<?php

namespace Ufo\RestBundle\Exceptions;

use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;

class RestResourceNotFoundException extends ServiceNotFoundException implements RestErrorInterface
{
    protected $message = 'Resource not found';
    protected $code = 404;

}