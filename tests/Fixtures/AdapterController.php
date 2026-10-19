<?php

namespace Ufo\RestBundleTest\Fixtures;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AdapterController
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'group' => $request->attributes->get('api_group'),
            'prefix' => $request->attributes->get('_adapter_name_prefix'),
        ]);
    }
}
