<?php

namespace Ufo\RestBundleTest\Fixtures;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPostResponseEvent;

#[AsEventListener(event: RpcEvent::POST_RESPONSE)]
class PostResponseRecorder
{
    /** @var string[] */
    public array $methods = [];

    public function __invoke(RpcPostResponseEvent $event): void
    {
        $this->methods[] = $event->rpcRequest->getMethod();
    }
}
