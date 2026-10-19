<?php

namespace Ufo\RestBundleTest\Unit\Exceptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Ufo\RestBundle\Exceptions\RestApplicationException;
use Ufo\RestBundle\Exceptions\RestBadRequestException;
use Ufo\RestBundle\Exceptions\RestErrorInterface;
use Ufo\RestBundle\Exceptions\RestErrorNormalizer;
use Ufo\RestBundle\Exceptions\RestInternalServerErrorException;
use Ufo\RestBundle\Exceptions\RestResourceNotAllowedException;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Exceptions\RestUnauthorizedException;
use Ufo\RestBundle\Exceptions\RestValidationException;
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

class RestErrorNormalizerTest extends TestCase
{
    public static function mapped(): iterable
    {
        yield 'json parse' => [new RpcJsonParseException('m'), RestBadRequestException::class, 400];
        yield 'bad request' => [new RpcBadRequestException('m'), RestBadRequestException::class, 400];
        yield 'method not found' => [new RpcMethodNotFoundExceptionRpc('m'), RestResourceNotFoundException::class, 404];
        yield 'constraints' => [new ConstraintsImposedException('m', ['amount' => ['positive']]), RestValidationException::class, 422];
        yield 'bad param' => [new RpcBadParamException('m'), RestBadRequestException::class, 400];
        yield 'internal' => [new RpcInternalException('m'), RestInternalServerErrorException::class, 500];
        yield 'runtime' => [new RpcRuntimeException('m'), RestInternalServerErrorException::class, 500];
        yield 'logic' => [new RpcLogicException('m'), RestBadRequestException::class, 400];
        yield 'token not sent' => [new RpcTokenNotSentException('m'), RestUnauthorizedException::class, 401];
        yield 'invalid token' => [new RpcInvalidTokenException('m'), RestUnauthorizedException::class, 401];
        yield 'data not found' => [new RpcDataNotFoundException('m'), RestResourceNotFoundException::class, 404];
        yield 'application, rpc code' => [new CustomApplicationException('m', 5001), RestApplicationException::class, 400];
        yield 'application, http code' => [new CustomApplicationException('m', 409), RestApplicationException::class, 409];
    }

    #[DataProvider('mapped')]
    public function testRpcErrorsAreMappedToRestErrors(Throwable $error, string $restClass, int $status): void
    {
        $rest = RestErrorNormalizer::normalizeError($error);

        self::assertInstanceOf($restClass, $rest);
        self::assertInstanceOf(RestErrorInterface::class, $rest);
        self::assertSame($status, $rest->getCode());
        self::assertSame('m', $rest->getMessage());
        self::assertSame($error, $rest->getPrevious());
    }

    public function testConstraintsAreKept(): void
    {
        $rest = RestErrorNormalizer::normalizeError(new ConstraintsImposedException('m', ['amount' => ['positive']]));

        self::assertSame(['amount' => ['positive']], $rest->getConstraintsImposed());
    }

    public static function unmapped(): iterable
    {
        yield 'http code is kept' => [new RuntimeException('m', 409), 409];
        yield 'another http code' => [new RuntimeException('m', 503), 503];
        yield 'no code' => [new RuntimeException('m'), 400];
        yield 'not an http code' => [new RuntimeException('m', 42), 400];
        yield 'rpc code' => [new RuntimeException('m', -32000), 400];
    }

    #[DataProvider('unmapped')]
    public function testOtherErrorsBecomeBadRequestWithHttpCode(Throwable $error, int $status): void
    {
        $rest = RestErrorNormalizer::normalizeError($error);

        self::assertInstanceOf(RestBadRequestException::class, $rest);
        self::assertSame($status, $rest->getCode());
        self::assertSame('m', $rest->getMessage());
        self::assertSame($error, $rest->getPrevious());
    }

    public static function restExceptions(): iterable
    {
        yield [new RestApplicationException(), 400, 'Rest application error'];
        yield [new RestBadRequestException(), 400, 'Rest bad request error'];
        yield [new RestInternalServerErrorException(), 500, 'Internal Server Error'];
        yield [new RestResourceNotAllowedException(), 405, 'Method not allowed'];
        yield [new RestResourceNotFoundException(), 404, 'Resource not found'];
        yield [new RestUnauthorizedException(), 401, null];
        yield [new RestValidationException(), 422, 'Validation error'];
    }

    #[DataProvider('restExceptions')]
    public function testRestExceptionsHaveHttpStatus(Throwable $e, int $status, ?string $message): void
    {
        self::assertInstanceOf(RestErrorInterface::class, $e);
        self::assertSame($status, $e->getCode());
        if ($message !== null) {
            self::assertSame($message, $e->getMessage());
        }
        self::assertArrayHasKey($status, RestErrorNormalizer::REST_ERROR_CODES);
    }
}
