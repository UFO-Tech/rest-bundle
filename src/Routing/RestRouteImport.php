<?php

namespace Ufo\RestBundle\Routing;

use Ufo\JsonRpcBundle\Routing\IApiRouteImport;

/**
 * Registers this bundle's config/router.yaml with the core RPC YAML loader: every import of it
 * (one per API group) gets route names "<group>_<name>" and the "<group>_<name_prefix>" value in
 * the "_rest_name_prefix" route default. Works side by side with other adapters (mcp-bundle).
 */
final class RestRouteImport implements IApiRouteImport
{
    public const string NAME_PREFIX_ATTR = '_rest_name_prefix';

    public function getResource(): string
    {
        return __DIR__.'/../../config/router.yaml';
    }

    public function getRouteNamePrefixAttribute(): string
    {
        return self::NAME_PREFIX_ATTR;
    }
}
