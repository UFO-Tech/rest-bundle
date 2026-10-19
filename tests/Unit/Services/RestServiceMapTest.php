<?php

namespace Ufo\RestBundleTest\Unit\Services;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\SnakeCaseToCamelCaseNameConverter;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Package;
use Ufo\RestBundle\Services\RestServiceMap;
use Ufo\RestBundleTest\Fixtures\Procedures\Catalog;
use Ufo\RestBundleTest\Fixtures\Procedures\Items;
use Ufo\RestBundleTest\Fixtures\Procedures\PlainApi;
use Ufo\RestBundleTest\Unit\Support\ServiceHolderStub as S;

class RestServiceMapTest extends TestCase
{
    private ApiGroupContext $context;

    protected function setUp(): void
    {
        $this->context = new ApiGroupContext();
    }

    private function map(array $map): RestServiceMap
    {
        return new RestServiceMap(
            new S($this->context, $map),
            new SnakeCaseToCamelCaseNameConverter(),
            new CamelCaseToSnakeCaseNameConverter(),
            $this->context,
        );
    }

    public function testClassAndMethodRoutesAreMerged(): void
    {
        $map = $this->map(['general' => ['v1' => [
            'catalog.shared' => S::service('catalog.shared', Catalog::class, new Route('/shared', name: 'shared', methods: ['GET'])),
        ]]]);

        $route = $map->getRoute('ufo_rest_api_catalog_shared');
        self::assertSame('/catalog/shared', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
        self::assertSame('catalog.shared', $map->getService('ufo_rest_api_catalog_shared')->name);
    }

    public function testDefaultsForNameMethodsAndClassWithoutRoute(): void
    {
        $attribute = new Route('/plain');
        $map = $this->map(['general' => ['v1' => [
            'plain.defaultPost' => S::service('plain.defaultPost', PlainApi::class, $attribute),
        ]]]);

        $route = $map->getRoute('ufo_rest_api_plain_default_post');
        self::assertSame('/plain', $route->getPath());
        self::assertSame(['POST'], $route->getMethods());
        self::assertNull($attribute->name, 'the RPC attribute is not mutated');
        self::assertSame([], $attribute->methods);
    }

    public function testClassRouteWithoutNameUsesTheProcedureAlias(): void
    {
        $map = $this->map(['general' => ['v1' => [
            'shopItems.get' => S::service('shopItems.get', Items::class, new Route('/{id}', name: 'get', methods: ['GET'])),
        ]]]);

        self::assertSame('/items/{id}', $map->getRoute('ufo_rest_api_shop_items_get')->getPath());
    }

    public function testEmptyPathsFallBackToClassPathOrRoot(): void
    {
        $map = $this->map(['general' => ['v1' => [
            'catalog.list' => S::service('catalog.list', Catalog::class, new Route('', name: 'list', methods: ['GET'])),
            'plain.root' => S::service('plain.root', PlainApi::class, new Route(name: 'root')),
        ]]]);

        self::assertSame('/catalog', $map->getRoute('ufo_rest_api_catalog_list')->getPath());
        self::assertSame('/', $map->getRoute('ufo_rest_api_plain_root')->getPath());
    }

    public function testServicesWithoutRouteAreNotPublished(): void
    {
        $map = $this->map(['general' => ['v1' => [
            'catalog.hidden' => S::service('catalog.hidden', Catalog::class),
        ]]]);

        self::assertSame([], $map->getRoutes());
        self::assertSame([], $map->getServices());
    }

    public function testVersionsAreSeparated(): void
    {
        $map = $this->map(['general' => [
            'v1' => ['catalog.shared' => S::service('catalog.shared', Catalog::class, new Route('/shared', name: 'shared', methods: ['GET']))],
            'v2' => ['catalog.price' => S::service('catalog.price', Catalog::class, new Route('/price', name: 'price'))],
        ]]);

        self::assertSame(['v1', 'v2'], $map->getVersions());
        self::assertSame(['ufo_rest_api_catalog_shared'], array_keys($map->getRoutes('v1')));
        self::assertSame(['ufo_rest_api_catalog_price'], array_keys($map->getRoutes('v2')));
    }

    public function testUnknownVersionRouteAndServiceThrowNotFound(): void
    {
        $map = $this->map(['general' => ['v1' => []]]);

        foreach ([
            fn() => $map->getRoutes('v3'),
            fn() => $map->getServices('v3'),
            fn() => $map->getRoute('ufo_rest_api_unknown'),
            fn() => $map->getService('ufo_rest_api_unknown'),
        ] as $call) {
            try {
                $call();
                self::fail('RestResourceNotFoundException expected');
            } catch (RestResourceNotFoundException $e) {
                self::assertSame(404, $e->getCode());
            }
        }
    }

    public function testEveryApiGroupHasItsOwnMapBuiltOnce(): void
    {
        $holder = new S($this->context, [
            'general' => ['v1' => ['catalog.status' => S::service('catalog.status', Catalog::class, new Route('/status', name: 'status'))]],
            'internal' => ['v1' => ['catalog.price' => S::service('catalog.price', Catalog::class, new Route('/price', name: 'price'))]],
        ]);
        $map = new RestServiceMap($holder, new SnakeCaseToCamelCaseNameConverter(), new CamelCaseToSnakeCaseNameConverter(), $this->context);

        self::assertSame(['ufo_rest_api_catalog_status'], array_keys($map->getRoutes()));
        $this->context->setGroup('internal');
        self::assertSame(['ufo_rest_api_catalog_price'], array_keys($map->getRoutes()));
        $this->context->setGroup('general');
        self::assertSame(['ufo_rest_api_catalog_status'], array_keys($map->getRoutes()));
        $this->context->setGroup('internal');
        $map->getServices();

        self::assertSame(['general/v1' => 1, 'internal/v1' => 1], $holder->calls);
    }

    public function testEnvelope(): void
    {
        self::assertStringStartsWith(Package::ENV_REST.'/UFO-REST-', $this->map([])->getEnvelope());
    }
}
