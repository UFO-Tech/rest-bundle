<?php

namespace Ufo\RestBundle\Services;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;
use Ufo\RpcObject\RPC\Info;

class RpcRestRouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(
        protected RestServiceMap $map,
        ?string $env = null,
    )
    {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('Do not add twice');
        }

        $routes = new RouteCollection();
        foreach ($this->map->getVersions() as $version) {
            foreach ($this->map->getRoutes($version) as $routeName => $route) {
                $route = clone $route;
                if ($version !== Info::DEFAULT_VERSION) {
                    $route->setPath('/' . $version . $route->getPath());
                    $route->setDefault('_ver', $version);
                }
                $routes->add($routeName . '_' . $version, $route);
            }
        }

        $this->loaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'ufo_rest_api';
    }
}