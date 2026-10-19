<?php

namespace Ufo\RestBundleTest\Fixtures;

use Ufo\JsonRpcBundle\Routing\IApiRouteImport;

/** Another adapter (like mcp-bundle) whose routes are imported per API group next to REST. */
final class AdapterRouteImport implements IApiRouteImport
{
    public function getResource(): string
    {
        return __DIR__.'/adapter/router.yaml';
    }

    public function getRouteNamePrefixAttribute(): string
    {
        return '_adapter_name_prefix';
    }
}
