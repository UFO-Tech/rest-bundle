<?php

namespace Ufo\RestBundle\EventDispatcher;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RestBundle\Controller\RestEntryPointController;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'process', priority: 30)]
class RestControllerListener
{
    public function __construct(
        public ApiGroupContext $apiGroupContext
    ) {}

    public function process(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->all();
        if (!isset($route['_rest_route']) && !isset($route['_rest_doc'])) return;

        $this->apiGroupContext->setGroup(
            $route['api_group'] ?? APIGroups::DEFAULT_GROUP,
            $route['_rest_name_prefix'] ?? null
        );
        if (isset($route['_rest_route'])) {
            $request->attributes->set('_controller', RestEntryPointController::class . '::rest');
            $request->attributes->set('rpcRoute', $route['_rest_route']);
            $request->attributes->set('ver', $route['_ver'] ?? Info::DEFAULT_VERSION);
        }
    }
}
