<?php

namespace Ufo\RestBundle\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

interface RestRequestParserInterface
{
    public function parse(string $routeName, Request $request): Service;
}