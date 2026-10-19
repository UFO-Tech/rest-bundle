<?php

namespace Ufo\RestBundleTest\Unit\Services;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\SnakeCaseToCamelCaseNameConverter;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RestBundle\Services\RestServiceMap;
use Ufo\RestBundle\Services\RpcRestRouteLoader;
use Ufo\RestBundleTest\Fixtures\Procedures\Catalog;
use Ufo\RestBundleTest\Unit\Support\ServiceHolderStub as S;

class RpcRestRouteLoaderTest extends TestCase
{
    private function loader(): RpcRestRouteLoader
    {
        $context = new ApiGroupContext();
        $map = new RestServiceMap(new S($context, ['general' => [
            'v1' => ['catalog.shared' => S::service('catalog.shared', Catalog::class, new Route('/shared', name: 'shared', methods: ['GET']))],
            'v2' => ['catalog.shared' => S::service('catalog.shared', Catalog::class, new Route('/shared', name: 'shared', methods: ['GET']))],
        ]]), new SnakeCaseToCamelCaseNameConverter(), new CamelCaseToSnakeCaseNameConverter(), $context);

        return new RpcRestRouteLoader($map);
    }

    public function testSupportsOnlyItsType(): void
    {
        self::assertTrue($this->loader()->supports('ufo_rest_api', 'ufo_rest_api'));
        self::assertFalse($this->loader()->supports('ufo_rest_api', 'yaml'));
        self::assertFalse($this->loader()->supports('ufo_rest_api'));
    }

    public function testRoutesPerVersionWithMethodNotAllowedFallback(): void
    {
        $routes = $this->loader()->load('ufo_rest_api');

        self::assertEqualsCanonicalizing([
            'ufo_rest_api_catalog_shared_v1',
            'ufo_rest_api_catalog_shared_v1_method_not_allowed',
            'ufo_rest_api_catalog_shared_v2',
            'ufo_rest_api_catalog_shared_v2_method_not_allowed',
        ], array_keys($routes->all()));
        // The method-specific route is matched before its fallback.
        $names = array_keys($routes->all());
        self::assertLessThan(array_search('ufo_rest_api_catalog_shared_v1_method_not_allowed', $names), array_search('ufo_rest_api_catalog_shared_v1', $names));

        $v1 = $routes->get('ufo_rest_api_catalog_shared_v1');
        self::assertSame('/catalog/shared', $v1->getPath());
        self::assertSame(['GET'], $v1->getMethods());
        self::assertSame('ufo_rest_api_catalog_shared', $v1->getDefault('_rest_route'));
        self::assertNull($v1->getDefault('_ver'));

        $v2 = $routes->get('ufo_rest_api_catalog_shared_v2');
        self::assertSame('/v2/catalog/shared', $v2->getPath());
        self::assertSame('v2', $v2->getDefault('_ver'));

        $fallback = $routes->get('ufo_rest_api_catalog_shared_v1_method_not_allowed');
        self::assertSame([], $fallback->getMethods());
        self::assertSame('/catalog/shared', $fallback->getPath());
        self::assertSame(-10, $routes->getPriority('ufo_rest_api_catalog_shared_v1_method_not_allowed'));
    }

    public function testMapRoutesAreNotModified(): void
    {
        $loader = $this->loader();
        $loader->load('ufo_rest_api');
        $routes = $loader->load('ufo_rest_api');

        self::assertSame('/v2/catalog/shared', $routes->get('ufo_rest_api_catalog_shared_v2')->getPath());
    }
}
