<?php

namespace Ufo\RestBundleTest\Fixtures\Procedures;

use RuntimeException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcError\CustomApplicationException;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcObject\RPC;

#[RPC\Info('errors')]
#[RPC\APIGroups(['errors'], false)]
#[Route('/errors', name: 'errors')]
class Errors implements IRpcService
{
    /**
     * @throws RpcDataNotFoundException
     */
    #[Route('/not-found', name: 'not_found', methods: ['GET'])]
    public function notFound(): string { throw new RpcDataNotFoundException('Item is not found'); }

    #[Route('/runtime', name: 'runtime', methods: ['GET'])]
    public function runtime(): string { throw new RuntimeException('Something broke'); }

    /**
     * @throws CustomApplicationException
     */
    #[Route('/custom', name: 'custom', methods: ['GET'])]
    public function custom(): string { throw new CustomApplicationException('Order is closed', 409); }

    #[Route('/positive', name: 'positive', methods: ['POST'])]
    public function positive(#[RPC\Assertions([new Assert\Positive()])] int $amount): int { return $amount; }

    /**
     * @throws \Throwable
     */
    #[Route('/any', name: 'any', methods: ['GET'])]
    public function any(): string { return 'any'; }

    /**
     * @deprecated use errors.any
     */
    #[Route('/old', name: 'old', methods: ['GET'])]
    public function old(): string { return 'old'; }
}
