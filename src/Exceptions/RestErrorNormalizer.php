<?php

namespace Ufo\RestBundle\Exceptions;

use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcError\CustomApplicationException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcLogicException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\RpcTokenNotSentException;

abstract class RestErrorNormalizer
{
    public const array REST_ERROR_CODES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',

        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];
    public const array ERROR_MAPPING = [
        // Checked with instanceof in this order: subclasses go before their parents.
        RpcJsonParseException::class => RestBadRequestException::class,
        RpcMethodNotFoundExceptionRpc::class => RestResourceNotFoundException::class,
        ConstraintsImposedException::class => RestValidationException::class,
        RpcBadParamException::class => RestBadRequestException::class,
        RpcBadRequestException::class => RestBadRequestException::class,
        RpcInternalException::class => RestInternalServerErrorException::class,
        RpcRuntimeException::class => RestInternalServerErrorException::class,
        RpcLogicException::class => RestBadRequestException::class,
        RpcTokenNotSentException::class => RestUnauthorizedException::class,
        RpcInvalidTokenException::class => RestUnauthorizedException::class,
        RpcDataNotFoundException::class => RestResourceNotFoundException::class,
        CustomApplicationException::class => RestApplicationException::class,
    ];

    public static function normalizeError(\Throwable $error): \Throwable
    {
        foreach (self::ERROR_MAPPING as $rpcClass => $restClass) {
            if ($error instanceof $rpcClass) {
                // An HTTP error code of the source exception is kept, otherwise the REST exception's own code is used.
                return new $restClass(
                    message: $error->getMessage(),
                    code: isset(static::REST_ERROR_CODES[$error->getCode()]) ? $error->getCode() : 0,
                    previous: $error
                );
            }
        }
        $code = $error->getCode();


        if (!isset(static::REST_ERROR_CODES[$code])) {
            $code = 400;
        }
        return new RestBadRequestException(
            message: $error->getMessage() ?? static::REST_ERROR_CODES[$code] ?? 'Unknown Error',
            code: $code,
            previous: $error
        );
    }
}