<?php

namespace Ufo\RestBundleTest\Fixtures\Procedures;

use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

#[RPC\Info('plain')]
class PlainApi implements IRpcService
{
    #[Route('/plain')]
    public function defaultPost(): string { return 'plain'; }
}
