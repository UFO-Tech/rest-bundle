<?php

namespace Ufo\RestBundleTest\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Ufo\RestBundle\DependencyInjection\RestBundleExtension;
use Ufo\RestBundle\Routing\RestRouteImport;
use Ufo\RestBundle\Services\RestServiceMap;
use Ufo\RestBundle\Services\RpcFromRest;
use Ufo\RestBundle\Services\RpcRestRouteLoader;

class RestBundleExtensionTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/ufo-rest-extension-'.uniqid();
        mkdir($this->projectDir.'/config/routes', 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectDir);
        return $container;
    }

    public function testPrependReadsTheRpcRoutePrefix(): void
    {
        file_put_contents($this->projectDir.'/config/routes/ufo_json_rpc.yaml', "ufo_json_rpc:\n    prefix: /api\n");
        $container = $this->container();
        (new RestBundleExtension())->prepend($container);

        self::assertSame('/api', $container->getParameter('ufo_api_prefix'));
    }

    public function testPrependWithoutRpcRoutesFile(): void
    {
        $container = $this->container();
        (new RestBundleExtension())->prepend($container);

        self::assertNull($container->getParameter('ufo_api_prefix'));
    }

    public function testLoadRegistersServices(): void
    {
        $container = $this->container();
        (new RestBundleExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(RestServiceMap::class));
        self::assertTrue($container->hasDefinition(RestRouteImport::class));
        self::assertTrue(!$container->hasDefinition(RpcFromRest::class) || $container->getDefinition(RpcFromRest::class)->hasTag('container.excluded'), 'a carrier is created per request');
        self::assertSame([[]], $container->getDefinition(RpcRestRouteLoader::class)->getTag('routing.loader'));
        self::assertTrue($container->getDefinition(RestRouteImport::class)->isAutoconfigured());
    }
}
