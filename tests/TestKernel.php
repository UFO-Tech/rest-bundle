<?php

namespace Ufo\RestBundleTest;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Ufo\JsonRpcBundle\UfoJsonRpcBundle;
use Ufo\RestBundle\RestBundleBundle;
use Ufo\RestBundleTest\Fixtures\AdapterController;
use Ufo\RestBundleTest\Fixtures\AdapterRouteImport;
use Ufo\RestBundleTest\Fixtures\PostResponseRecorder;
use Ufo\RestBundleTest\Fixtures\Procedures\Catalog;
use Ufo\RestBundleTest\Fixtures\Procedures\Errors;
use Ufo\RestBundleTest\Fixtures\Procedures\Typed;
use Ufo\RestBundleTest\Fixtures\Procedures\PlainApi;
use Ufo\RestBundleTest\Fixtures\Procedures\CatalogV2;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public static array $security = ['protected_api' => false, 'protected_doc' => false];

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new UfoJsonRpcBundle(), new RestBundleBundle()];
    }

    public function getProjectDir(): string { return sys_get_temp_dir().'/ufo-rest-tests/app'; }
    public function getCacheDir(): string { return sys_get_temp_dir().'/ufo-rest-tests/'.md5(serialize(self::$security)); }
    public function getLogDir(): string { return sys_get_temp_dir().'/ufo-rest-tests/log'; }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false]);
        $c->extension('security', ['providers' => ['memory' => ['memory' => null]], 'firewalls' => ['main' => ['lazy' => true]]]);
        $c->extension('ufo_json_rpc', [
            'cache' => [],
            'security' => self::$security,
            'docs' => ['project_name' => 'REST tests', 'validations' => []],
            'translations' => ['paths' => [__DIR__.'/Fixtures/translations']],
        ]);
        $services = $c->services();
        $services->defaults()->autowire()->autoconfigure()->public();
        $services->set(Catalog::class);
        $services->set(CatalogV2::class);
        $services->set(PlainApi::class);
        $services->set(Errors::class);
        $services->set(Typed::class);
        $services->set(PostResponseRecorder::class);
        $services->set(AdapterRouteImport::class);
        $services->set(AdapterController::class)->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/Fixtures/routes.yaml');
    }
}
