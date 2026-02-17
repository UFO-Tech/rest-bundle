<?php

namespace Ufo\RestBundle\EventDispatcher;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Ufo\RpcObject\RPC\Info;
use Ufo\RestBundle\Controller\RestEntryPointController;
use Ufo\RestBundle\Services\RestServiceMap;

use function str_starts_with;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'process', priority: 30)]
class RestControllerListener
{
    public function __construct(
        public RouterInterface $router
    ) {}

    public function process(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        $route = $this->router->match($path);
        $routeName = $route['_route'] ?? null;

        if ($routeName && str_starts_with($routeName, RestServiceMap::ROUTE_PREFIX)) {
            $request->attributes->set('_controller', RestEntryPointController::class . '::rest');
            $request->attributes->set('rpcRoute', $routeName);
            $request->attributes->set('ver', $route['_ver'] ?? Info::DEFAULT_VERSION);
        }
    }
}