<?php

namespace Ufo\RestBundleTest\Unit\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcHttpCarrier;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RestBundle\Exceptions\RestBadRequestException;
use Ufo\RestBundle\Services\RpcFromRest;
use Ufo\RestBundleTest\Fixtures\Procedures\Catalog;
use Ufo\RestBundleTest\Unit\Support\ServiceHolderStub;
use Ufo\RpcError\WrongWayException;

class RpcFromRestTest extends TestCase
{
    private function carrier(Request $request): RpcFromRest
    {
        return new RpcFromRest(ServiceHolderStub::service('catalog.echoValue', Catalog::class), $request);
    }

    private function jsonRequest(string $uri, ?string $body, array $routeParams = []): Request
    {
        $request = Request::create($uri, 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $request->attributes->set('_route_params', $routeParams);
        return $request;
    }

    public function testBuildsRpcRequestForTheService(): void
    {
        $request = $this->jsonRequest('/catalog/echo/1', '{"value":"x"}', ['id' => '1']);
        $carrier = $this->carrier($request);
        $rpc = $carrier->getRequestObject();

        self::assertInstanceOf(IRpcHttpCarrier::class, $carrier);
        self::assertSame($request, $carrier->getHttpRequest());
        self::assertSame('catalog.echoValue', $rpc->getMethod());
        self::assertSame(['id' => '1', 'value' => 'x'], $rpc->getParams());
        self::assertSame('v1', $rpc->apiVersion);
    }

    public function testVersionComesFromTheRequestAttribute(): void
    {
        $request = $this->jsonRequest('/v2/catalog/echo/1', null);
        $request->attributes->set('ver', 'v2');

        self::assertSame('v2', $this->carrier($request)->getRequestObject()->apiVersion);
    }

    public function testServiceRouteAttributesAreNotParams(): void
    {
        $request = $this->jsonRequest('/catalog/echo/1', null, [
            'id' => '1', 'api_group' => 'internal', 'ver' => 'v1', 'rpcRoute' => 'x', '_rest_route' => 'x',
            '_rest_name_prefix' => 'internal_', '_rest_doc' => true, '_ver' => 'v2', '_controller' => 'c',
            '_format' => 'json', '_locale' => 'uk', '_stateless' => true,
        ]);

        self::assertSame(['id' => '1'], $this->carrier($request)->getRequestObject()->getParams());
    }

    public function testParamsPrecedenceRouteQueryFormJson(): void
    {
        $request = Request::create('/catalog/echo/route?id=query&value=query&q=1', 'POST',
            ['value' => 'form', 'f' => 1], server: ['CONTENT_TYPE' => 'application/json'], content: '{"value":"json"}');
        $request->attributes->set('_route_params', ['id' => 'route', 'r' => 1]);

        $params = $this->carrier($request)->getRequestObject()->getParams();
        ksort($params);
        self::assertSame(['f' => 1, 'id' => 'query', 'q' => '1', 'r' => 1, 'value' => 'json'], $params);
    }

    public function testFormBodyIsNotParsedAsJson(): void
    {
        $request = Request::create('/x', 'POST', ['value' => 'form'],
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: 'value=form');

        self::assertSame(['value' => 'form'], $this->carrier($request)->getRequestObject()->getParams());
    }

    public function testEmptyBodyGivesNoParams(): void
    {
        self::assertSame([], $this->carrier($this->jsonRequest('/x', ''))->getRequestObject()->getParams());
    }

    public static function badBodies(): iterable
    {
        yield 'broken json' => ['{broken', 'Invalid JSON request body'];
        yield 'null' => ['null', 'JSON request body must be an object or array'];
        yield 'scalar' => ['42', 'JSON request body must be an object or array'];
        yield 'string' => ['"text"', 'JSON request body must be an object or array'];
    }

    #[DataProvider('badBodies')]
    public function testBadBodyIsBadRequest(string $body, string $message): void
    {
        $this->expectException(RestBadRequestException::class);
        $this->expectExceptionMessage($message);
        $this->expectExceptionCode(400);
        $this->carrier($this->jsonRequest('/x', $body));
    }

    public function testThereIsNoBatch(): void
    {
        $this->expectException(WrongWayException::class);
        $this->carrier($this->jsonRequest('/x', null))->getBatchRequestObject();
    }

    public static function casts(): iterable
    {
        yield 'int' => ['42', 'int', 42];
        yield 'negative int' => ['-7', 'int', -7];
        yield 'zero' => ['0', 'int', 0];
        yield 'int, leading zero' => ['007', 'int', '007'];
        yield 'int, plus sign' => ['+1', 'int', '+1'];
        yield 'int, spaces' => [' 1', 'int', ' 1'];
        yield 'int, decimal' => ['1.5', 'int', '1.5'];
        yield 'int, overflow' => ['99999999999999999999', 'int', '99999999999999999999'];
        yield 'int, empty' => ['', 'int', ''];
        yield 'nullable int, empty' => ['', ['int', 'null'], null];
        yield '?int string form, empty' => ['', '?int', null];
        yield 'nullable int' => ['3', ['int', 'null'], 3];
        yield 'float' => ['1.5', 'float', 1.5];
        yield 'float from int literal' => ['2', 'float', 2.0];
        yield 'float, exponent' => ['1e3', 'float', 1000.0];
        yield 'float, comma' => ['1,5', 'float', '1,5'];
        yield 'int|float, int literal' => ['2', ['int', 'float'], 2];
        yield 'int|float, decimal' => ['2.5', ['int', 'float'], 2.5];
        yield 'bool true' => ['true', 'bool', true];
        yield 'bool TRUE' => ['TRUE', 'bool', true];
        yield 'bool 1' => ['1', 'bool', true];
        yield 'bool false' => ['false', 'bool', false];
        yield 'bool 0' => ['0', 'bool', false];
        yield 'bool yes' => ['yes', 'bool', 'yes'];
        yield 'bool empty' => ['', 'bool', ''];
        yield 'nullable bool empty' => ['', ['bool', 'null'], null];
        yield 'int|bool prefers int' => ['1', ['int', 'bool'], 1];
        yield 'string' => ['42', 'string', '42'];
        yield 'int|string keeps string' => ['42', ['string', 'int'], '42'];
        yield 'mixed' => ['42', 'mixed', '42'];
        yield 'nullable string, empty' => ['', ['string', 'null'], ''];
        yield 'enum is left to the core' => ['csv', 'App\\Enum\\Format', 'csv'];
        yield 'array is left to the core' => ['x', 'array', 'x'];
    }

    #[DataProvider('casts')]
    public function testCastString(string $value, string|array $realType, mixed $expected): void
    {
        self::assertSame($expected, RpcFromRest::castString($value, $realType));
    }

    public function testOnlyStringValuesOfKnownParamsAreCast(): void
    {
        $service = ServiceHolderStub::service('catalog.echoValue', Catalog::class)
            ->addParam(new ParamDefinition('id', ['type' => 'integer'], 'int'))
            ->addParam(new ParamDefinition('page', ['type' => 'integer'], 'int'))
            ->addParam(new ParamDefinition('ids', ['type' => 'array'], 'array'))
            ->addParam(new ParamDefinition('amount', ['type' => 'integer'], 'int'));
        $request = Request::create('/catalog/echo/5?page=2&extra=3&ids[]=1', 'POST',
            server: ['CONTENT_TYPE' => 'application/json'], content: '{"amount":"4"}');
        $request->attributes->set('_route_params', ['id' => '5']);

        $params = (new RpcFromRest($service, $request))->getRequestObject()->getParams();
        ksort($params);

        self::assertSame(['amount' => '4', 'extra' => '3', 'id' => 5, 'ids' => ['1'], 'page' => 2], $params);
    }
}
