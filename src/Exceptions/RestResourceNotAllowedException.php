<?php

namespace Ufo\RestBundle\Exceptions;

use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;

class RestResourceNotAllowedException extends ServiceNotFoundException implements RestErrorInterface
{
    protected $message = 'Method not allowed';
    protected $code = 405;

}