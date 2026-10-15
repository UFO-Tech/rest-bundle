<?php

namespace Ufo\RestBundleTest\Fixtures\Procedures;

use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('catalog', version: 2, removedMethods: ['price'])]
#[RPC\APIGroups(['internal'], false)]
#[Route('/catalog', name: 'catalog')]
class CatalogV2 implements IRpcService
{
    #[Route('/shared', name: 'shared', methods: ['GET'])]
    public function shared(): string { return 'v2'; }
}
