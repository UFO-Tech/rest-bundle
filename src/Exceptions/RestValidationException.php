<?php

namespace Ufo\RestBundle\Exceptions;


use Throwable;
use Ufo\RpcError\ConstraintsImposedException;

class RestValidationException extends ConstraintsImposedException implements RestErrorInterface
{
    public const int HTTP_CODE = 422;

    /**
     * Constraints are taken from a previous ConstraintsImposedException when not given.
     */
    public function __construct(
        string $message = 'Validation error',
        array $constraintsImposed = [],
        int $code = self::HTTP_CODE,
        ?Throwable $previous = null
    )
    {
        if (!$constraintsImposed && $previous instanceof ConstraintsImposedException) {
            $constraintsImposed = $previous->getConstraintsImposed();
        }
        parent::__construct($message, $constraintsImposed, $code ?: self::HTTP_CODE, $previous);
    }
}
