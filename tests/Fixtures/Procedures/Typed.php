<?php

namespace Ufo\RestBundleTest\Fixtures\Procedures;

use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('typed')]
#[RPC\APIGroups(['typed'], false)]
#[Route('/typed', name: 'typed')]
class Typed implements IRpcService
{
    #[Route('/items/{id}', name: 'item', methods: ['GET'])]
    public function item(int $id, ?int $limit = 10, float $ratio = 1.5, bool $active = false, int|string $ref = 0, string $code = ''): array
    {
        return ['id' => $id, 'limit' => $limit, 'ratio' => $ratio, 'active' => $active, 'ref' => $ref, 'code' => $code];
    }

    #[Route('/items/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, int $amount): array
    {
        return ['id' => $id, 'amount' => $amount];
    }
}
