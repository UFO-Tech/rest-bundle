<?php

namespace Ufo\RestBundleTest\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RestBundle\Controller\RestEntryPointController;
use Ufo\RestBundle\EventDispatcher\RestControllerListener;

class RestControllerListenerTest extends TestCase
{
    private ApiGroupContext $context;

    protected function setUp(): void
    {
        $this->context = new ApiGroupContext();
    }

    private function process(array $attributes): Request
    {
        $request = Request::create('/x');
        $request->attributes->add($attributes);
        (new RestControllerListener($this->context))->process(
            new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST)
        );
        return $request;
    }

    public function testOtherRoutesAreIgnored(): void
    {
        $request = $this->process(['_controller' => 'App\\Controller::index', 'api_group' => 'internal']);

        self::assertSame('App\\Controller::index', $request->attributes->get('_controller'));
        self::assertSame('general', $this->context->getGroup());
    }

    public function testRestRouteGoesToTheEntryPoint(): void
    {
        $request = $this->process([
            '_rest_route' => 'ufo_rest_api_catalog_shared',
            '_ver' => 'v2',
            'api_group' => 'internal',
            '_rest_name_prefix' => 'internal_second_',
        ]);

        self::assertSame(RestEntryPointController::class.'::rest', $request->attributes->get('_controller'));
        self::assertSame('ufo_rest_api_catalog_shared', $request->attributes->get('rpcRoute'));
        self::assertSame('v2', $request->attributes->get('ver'));
        self::assertSame('internal', $this->context->getGroup());
        self::assertSame('internal_second_ufo_rest_doc', $this->context->getRouteName('ufo_rest_doc'));
    }

    public function testDefaultVersionAndGroup(): void
    {
        $request = $this->process(['_rest_route' => 'ufo_rest_api_catalog_status']);

        self::assertSame('v1', $request->attributes->get('ver'));
        self::assertSame('general', $this->context->getGroup());
    }

    public function testDocRouteOnlySetsTheGroup(): void
    {
        $request = $this->process(['_rest_doc' => true, '_controller' => 'doc', 'api_group' => 'public']);

        self::assertSame('doc', $request->attributes->get('_controller'));
        self::assertFalse($request->attributes->has('rpcRoute'));
        self::assertSame('public', $this->context->getGroup());
    }
}
