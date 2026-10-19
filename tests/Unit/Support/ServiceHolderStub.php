<?php

namespace Ufo\RestBundleTest\Unit\Support;

use Symfony\Component\Routing\Attribute\Route;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Info;

/**
 * RPC service map of the current API group: [group => [version => [name => Service]]].
 */
class ServiceHolderStub implements IServiceHolder
{
    /** @var array<string,int> getServices() calls per "group/version" */
    public array $calls = [];

    public function __construct(
        protected ApiGroupContext $apiGroupContext,
        protected array $map = [],
    ) {}

    public static function service(string $name, string $procedureFQCN, ?Route $route = null): Service
    {
        $service = new Service($name, $procedureFQCN, new Info(explode('.', $name)[0]));
        if ($route) {
            $service->setAttribute($route);
        }
        return $service;
    }

    public function getService(string $serviceName, string $version = Info::DEFAULT_VERSION): Service
    {
        return $this->getServices($version)[$serviceName] ?? throw new ServiceNotFoundException($serviceName);
    }

    public function getServices(string $version = Info::DEFAULT_VERSION): array
    {
        $group = $this->apiGroupContext->getGroup();
        $this->calls[$group.'/'.$version] = ($this->calls[$group.'/'.$version] ?? 0) + 1;
        return $this->map[$group][$version] ?? [];
    }

    public function getVersions(): array
    {
        return array_keys($this->map[$this->apiGroupContext->getGroup()] ?? [Info::DEFAULT_VERSION => []]);
    }
}
