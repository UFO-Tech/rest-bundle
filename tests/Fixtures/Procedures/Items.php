<?php

namespace Ufo\RestBundleTest\Fixtures\Procedures;

use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

/** A class route with a path and no name: the name comes from the procedure alias. */
#[RPC\Info('shopItems')]
#[Route('/items/')]
class Items implements IRpcService
{
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): string { return $id; }
}
