<?php

namespace Ufo\RestBundleTest\Functional;

/**
 * REST is registered with IApiRouteImport, so another adapter registered the same way
 * gets its own per-group routes next to REST and RPC.
 */
class AdapterCoexistenceTest extends FunctionalTestCase
{
    public function testEveryAdapterGetsItsOwnGroupRoutes(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertSame('/adapter', $router->generate('general_adapter_endpoint'));
        self::assertSame('/adapter', $router->generate('adapter_endpoint'), 'legacy alias for general');
        self::assertSame('/adapter/internal', $router->generate('internal_adapter_endpoint'));
        self::assertSame('/internal', $router->generate('internal_ufo_rest_doc'));
        self::assertSame('/rpc', $router->generate('ufo_rpc_api_server'));

        self::assertSame([200, ['group' => 'internal', 'prefix' => 'internal_']], $this->json('/adapter/internal'));
        self::assertSame([200, ['group' => 'general', 'prefix' => 'general_']], $this->json('/adapter'));
        self::assertSame([200, 'shared'], $this->json('/internal/catalog/shared'));
    }

    public function testRestRoutesHaveRestDefaults(): void
    {
        self::bootKernel();
        $routes = self::getContainer()->get('router')->getRouteCollection();

        $doc = $routes->get('internal_second_ufo_rest_doc');
        self::assertSame('internal', $doc->getDefault('api_group'));
        self::assertSame('internal_second_', $doc->getDefault('_rest_name_prefix'));
        self::assertNull($doc->getDefault('_adapter_name_prefix'));

        $call = $routes->get('internal_ufo_rest_api_catalog_shared_v1');
        self::assertSame('ufo_rest_api_catalog_shared', $call->getDefault('_rest_route'));
        self::assertSame(['GET'], $call->getMethods());
        self::assertNull($routes->get('general_ufo_rest_api_catalog_shared_v1'), 'catalog.shared is not in general');
    }
}
