<?php

namespace Ufo\RestBundle\Exceptions;


use Ufo\RpcError\ConstraintsImposedException;

class RestValidationException extends ConstraintsImposedException
{
    protected $code = 422;
}