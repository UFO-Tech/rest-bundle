<?php

namespace Ufo\RestBundleTest\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RestBundle\Exceptions\RestResourceNotAllowedException;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Interfaces\RestRequestParserInterface;
use Ufo\RestBundle\Services\RestRequestParser;
use Ufo\RestBundle\Services\RpcFromRest;
use Ufo\RestBundleTest\TestKernel;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcTokenNotSentException;

class RestRequestParserTest extends KernelTestCase
{
    protected static function getKernelClass(): string { return TestKernel::class; }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestKernel::$security = ['protected_api' => false, 'protected_doc' => false];
        restore_exception_handler();
    }

    private function parser(): RestRequestParser
    {
        $parser = self::getContainer()->get(RestRequestParserInterface::class);
        self::assertInstanceOf(RestRequestParser::class, $parser);
        return $parser;
    }

    private function request(string $method = 'GET', array $attributes = [], array $headers = []): Request
    {
        $request = Request::create('http://localhost/x', $method, server: $headers);
        $request->attributes->add($attributes);
        return $request;
    }

    public function testParseResolvesTheServiceAndSetsTheCarrier(): void
    {
        self::bootKernel();
        $request = $this->request(attributes: ['api_group' => 'internal', 'ver' => 'v2', '_rest_route' => 'ufo_rest_api_catalog_shared']);
        $service = $this->parser()->parse('ufo_rest_api_catalog_shared', $request);

        self::assertSame('catalog.shared', $service->name);
        self::assertSame('internal', self::getContainer()->get(ApiGroupContext::class)->getGroup());
        $carrier = self::getContainer()->get(RequestCarrier::class)->getCarrier();
        self::assertInstanceOf(RpcFromRest::class, $carrier);
        self::assertSame($request, $carrier->getHttpRequest());
        self::assertSame('catalog.shared', $carrier->getRequestObject()->getMethod());
        self::assertSame('v2', $carrier->getRequestObject()->apiVersion);
    }

    public function testVersionSuffixIsStrippedFromAPlainRouteName(): void
    {
        self::bootKernel();
        $service = $this->parser()->parse('ufo_rest_api_catalog_shared_v2', $this->request(attributes: ['api_group' => 'internal', 'ver' => 'v2']));

        self::assertSame('catalog.shared', $service->name);
    }

    public function testHeadIsServedByGet(): void
    {
        self::bootKernel();
        $service = $this->parser()->parse('ufo_rest_api_catalog_status', $this->request('HEAD', ['_rest_route' => 'x']));

        self::assertSame('catalog.status', $service->name);
    }

    public function testWrongHttpMethod(): void
    {
        self::bootKernel();
        $this->expectException(RestResourceNotAllowedException::class);
        $this->parser()->parse('ufo_rest_api_catalog_status', $this->request('DELETE', ['_rest_route' => 'x']));
    }

    public function testRouteOfAnotherGroupIsNotFound(): void
    {
        self::bootKernel();
        $this->expectException(RestResourceNotFoundException::class);
        $this->expectExceptionMessage('Route "/x" is not found in REST Service Map');
        $this->parser()->parse('ufo_rest_api_catalog_price', $this->request('POST', ['_rest_route' => 'x', 'api_group' => 'public']));
    }

    public function testUnknownVersionIsNotFound(): void
    {
        self::bootKernel();
        $this->expectException(RestResourceNotFoundException::class);
        $this->parser()->parse('ufo_rest_api_catalog_status', $this->request(attributes: ['_rest_route' => 'x', 'ver' => 'v9']));
    }

    public function testTokenIsChecked(): void
    {
        TestKernel::$security = ['protected_api' => true, 'protected_doc' => false, 'clients_tokens' => ['secret']];
        self::bootKernel();
        $attributes = ['_rest_route' => 'x'];

        self::assertSame('catalog.status', $this->parser()->parse('ufo_rest_api_catalog_status', $this->request(attributes: $attributes, headers: ['HTTP_UFO_RPC_TOKEN' => 'secret']))->name);

        try {
            $this->parser()->parse('ufo_rest_api_catalog_status', $this->request(attributes: $attributes));
            self::fail('token is required');
        } catch (RpcTokenNotSentException) {
        }

        $this->expectException(RpcInvalidTokenException::class);
        $this->parser()->parse('ufo_rest_api_catalog_status', $this->request(attributes: $attributes, headers: ['HTTP_UFO_RPC_TOKEN' => 'wrong']));
    }
}
