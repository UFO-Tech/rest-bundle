<?php

namespace Ufo\RestBundleTest\Fixtures\Procedures;

use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;
use Ufo\RestBundleTest\Fixtures\Procedures\Format;

#[RPC\Info('catalog')]
#[RPC\APIGroups(['internal', 'public'], false)]
#[Route('/catalog', name: 'catalog')]
class Catalog implements IRpcService
{
    #[Route('/shared', name: 'shared', methods: ['GET'])]
    public function shared(): string { return 'shared'; }

    #[RPC\APIGroups(['internal'], false)]
    #[Route('/price', name: 'price', methods: ['POST'])]
    public function price(int $price): int { return $price; }

    #[RPC\APIGroups()]
    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): string { return 'ok'; }

    #[RPC\IgnoreApi]
    #[Route('/hidden', name: 'hidden')]
    public function hidden(): string { return 'hidden'; }

    #[Route('/echo/{id}', name: 'echo', methods: ['POST'])]
    public function echoValue(string $id, string $value): array { return ['id' => $id, 'value' => $value]; }
    #[RPC\APIGroups(['internal'], false)]
    #[Route('/format', name: 'format', methods: ['POST'])]
    public function format(Format $format): string { return $format->value; }

}
