<?php

namespace Ufo\RestBundleTest\Functional;

use Ufo\RestBundle\Exceptions\RestApplicationException;
use Ufo\RestBundle\Exceptions\RestBadRequestException;
use Ufo\RestBundle\Exceptions\RestInternalServerErrorException;
use Ufo\RestBundle\Exceptions\RestResourceNotAllowedException;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Exceptions\RestUnauthorizedException;
use Ufo\RestBundleTest\Fixtures\PostResponseRecorder;
use Ufo\RestBundleTest\TestKernel;

/**
 * Errors are RFC 9457 problem details with the HTTP status of the error.
 */
class ErrorResponseTest extends FunctionalTestCase
{
    private function assertProblem(array $problem, int $status, string $title, string $instance, ?string $detail = null): void
    {
        self::assertSame('RFC 9457', $problem['_error_object_standard']);
        self::assertSame($status, $problem['status']);
        self::assertSame('about:blank', $problem['type']);
        self::assertSame($instance, $problem['instance']);
        self::assertSame($title, $problem['title']);
        if ($detail !== null) {
            self::assertSame($detail, $problem['detail']);
        }
    }

    public function testDomainErrorsOfMethods(): void
    {
        self::bootKernel();

        [$status, $problem] = $this->json('/errors-api/errors/not-found');
        self::assertSame(404, $status);
        $this->assertProblem($problem, 404, RestResourceNotFoundException::class, '/errors-api/errors/not-found', 'Item is not found');

        [$status, $problem] = $this->json('/errors-api/errors/runtime');
        self::assertSame(500, $status);
        $this->assertProblem($problem, 500, RestInternalServerErrorException::class, '/errors-api/errors/runtime', 'Something broke');

        [$status, $problem] = $this->json('/errors-api/errors/custom');
        self::assertSame(409, $status, 'an HTTP code of an application error is kept');
        $this->assertProblem($problem, 409, RestApplicationException::class, '/errors-api/errors/custom', 'Order is closed');
    }

    public function testInvalidParamsHaveDetailData(): void
    {
        self::bootKernel();

        [$status, $problem] = $this->json('/errors-api/errors/positive', 'POST', '{"amount":-1}');
        self::assertSame(400, $status);
        $this->assertProblem($problem, 400, RestBadRequestException::class, '/errors-api/errors/positive', 'Invalid Data for call method: errors.positive');
        self::assertSame(['amount' => ['This value should be positive.']], $problem['detail-data']);

        [$status, $problem] = $this->json('/errors-api/errors/positive', 'POST', '{}');
        self::assertSame(400, $status);
        self::assertSame('Passed (0) parameters and expected (1)', $problem['detail']);

        [$status, $problem] = $this->json('/internal/catalog/echo/1', 'POST', '{}');
        self::assertSame(400, $status);
        self::assertSame(['value' => ['Required parameter "value" not passed']], $problem['detail-data']);

        [$status, $problem] = $this->json('/errors-api/errors/positive', 'POST', '{"amount":"abc"}');
        self::assertSame(400, $status);
        self::assertArrayHasKey('amount', $problem['detail-data']);

        self::assertSame([200, 5], $this->json('/errors-api/errors/positive', 'POST', '{"amount":5}'));
    }

    /**
     * Scalars are cast by RpcFromRest (see TypeCastingTest); enums are converted by the RPC core (JsonRpcBundle 10.4.4).
     */
    public function testEnumIsConvertedByTheCore(): void
    {
        self::bootKernel();

        self::assertSame([200, 42], $this->json('/internal/catalog/price?price=42', 'POST'));
        self::assertSame([200, 'csv'], $this->json('/internal/catalog/format?format=csv', 'POST'));
        self::assertSame([200, 'json'], $this->json('/internal/catalog/format', 'POST', '{"format":"json"}'));

        foreach ([['/internal/catalog/format?format=xml', null], ['/internal/catalog/format', '{"format":"xml"}']] as [$path, $body]) {
            [$status, $problem] = $this->json($path, 'POST', $body);
            self::assertSame(400, $status);
            $this->assertProblem($problem, 400, RestBadRequestException::class, '/internal/catalog/format', 'Invalid Data for call method: catalog.format');
            self::assertSame(['format' => ['Invalid value "xml", expected one of: json, csv']], $problem['detail-data']);
        }
    }

    public function testRequestErrors(): void
    {
        self::bootKernel();

        [$status, $problem] = $this->json('/errors-api/errors/positive', 'POST', '{broken');
        self::assertSame(400, $status);
        $this->assertProblem($problem, 400, RestBadRequestException::class, '/errors-api/errors/positive', 'Invalid JSON request body');

        [$status, $problem] = $this->json('/errors-api/errors/positive', 'GET');
        self::assertSame(405, $status);
        $this->assertProblem($problem, 405, RestResourceNotAllowedException::class, '/errors-api/errors/positive');

        self::assertSame(405, $this->json('/errors-api/errors/positive', 'OPTIONS')[0]);

        [$status, $problem] = $this->json('/errors-api/nothing/here');
        self::assertSame(404, $status);
        $this->assertProblem($problem, 404, RestResourceNotFoundException::class, '/errors-api/nothing/here');
        self::assertStringContainsString('/errors-api/nothing/here', $problem['detail']);

        self::assertSame(404, $this->json('/errors-api/v7/errors/runtime')[0], 'unknown version');
    }

    public function testTokenErrors(): void
    {
        TestKernel::$security = ['protected_api' => true, 'protected_doc' => false, 'clients_tokens' => ['secret']];
        self::bootKernel();

        [$status, $problem] = $this->json('/rest/catalog/status');
        self::assertSame(401, $status);
        $this->assertProblem($problem, 401, RestUnauthorizedException::class, '/rest/catalog/status');

        self::assertSame(401, $this->json('/rest/catalog/status', headers: ['Ufo-RPC-Token' => 'wrong'])[0]);
        self::assertSame([200, 'ok'], $this->json('/rest/catalog/status', headers: ['Ufo-RPC-Token' => 'secret']));
        self::assertSame(200, $this->json('/rest')[0], 'documentation is not protected by protected_api');
    }

    public function testNoPostResponseForErrors(): void
    {
        self::bootKernel();
        $recorder = self::getContainer()->get(PostResponseRecorder::class);
        foreach (['/errors-api/errors/not-found', '/errors-api/errors/runtime', '/errors-api/errors/custom'] as $path) {
            $request = \Symfony\Component\HttpFoundation\Request::create('http://localhost'.$path);
            self::$kernel->terminate($request, self::$kernel->handle($request));
        }

        self::assertSame([], $recorder->methods);
    }
}
