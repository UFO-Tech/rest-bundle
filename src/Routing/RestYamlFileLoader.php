<?php

namespace Ufo\RestBundle\Routing;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\JsonRpcBundle\Routing\RpcYamlFileLoader;

#[AutoconfigureTag('routing.loader', ['priority' => 200])]
class RestYamlFileLoader extends RpcYamlFileLoader
{
    protected function getRouteNamePrefixAttribute(array $config, string $path): ?string
    {
        if ($this->isResourceImport($config['resource'], $path, __DIR__.'/../../config/router.yaml')) {
            return '_rest_name_prefix';
        }

        return parent::getRouteNamePrefixAttribute($config, $path);
    }
}
