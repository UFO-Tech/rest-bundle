<?php

namespace Ufo\RestBundle\Services;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\SnakeCaseToCamelCaseNameConverter;
use Ufo\RpcObject\RPC\Info;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RestBundle\Package;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;

use function array_key_exists;
use function explode;
use function in_array;

#[AutoconfigureTag(IServiceHolder::TAG)]
class RestServiceMap implements IServiceHolder
{
    const string ROUTE_PREFIX = 'ufo_rest_api_';

    protected string $envelope;
    /**
     * @var array<string,array<string,Service>>
     */
    protected array $services = [];

    /**
     * @var array<class-string,Route>
     */
    protected array $classRoutes = [];

    /**
     * @var array<string,array<string,SymfonyRoute>>
     */
    protected array $routes = [];

    private array $groupMaps = [];
    private ?string $activeGroup = null;
    private array $versions = [];

    public function __construct(
        protected IServiceHolder $serviceMap,
        protected SnakeCaseToCamelCaseNameConverter $toCamelCaseNameConverter,
        protected CamelCaseToSnakeCaseNameConverter $toSnakeCaseNameConverter,
        protected ApiGroupContext $apiGroupContext,
    ) {}

    private function activateGroup(): void
    {
        $group = $this->apiGroupContext->getGroup();
        if ($this->activeGroup === $group) {
            return;
        }
        if (!isset($this->groupMaps[$group])) {
            $this->services = $this->routes = $this->classRoutes = [];
            $this->versions = $this->serviceMap->getVersions();
            $this->init();
            $this->groupMaps[$group] = [$this->services, $this->routes, $this->versions];
        }
        [$this->services, $this->routes, $this->versions] = $this->groupMaps[$group];
        $this->activeGroup = $group;
    }

    protected function init(): void
    {
        $versions = $this->versions;
        foreach ($versions as $version) {
            foreach ($this->serviceMap->getServices($version) as $service) {
                /** @var Route $route */
                if ($route = $service->getAttrCollection()->getAttribute(Route::class)) {
                    $route = clone $route;
                    if (!$route->name) {
                        $route->name = $this->toSnakeCaseNameConverter->normalize($service->getMethodName());
                    }
                    if (empty($route->methods)) {
                        $route->methods = ['POST'];
                    }
                    $this->addService($route, $service, $version);
                }
            }
        }
    }

    protected function addService(Route $route, Service $service, string $version): void
    {
        $classFQCN = $service->getProcedureFQCN();
        if (!array_key_exists($classFQCN, $this->classRoutes)) {
            $refClass = new ReflectionClass($classFQCN);
            $this->classRoutes[$classFQCN] = ($refClass->getAttributes(Route::class)[0] ?? null)?->newInstance();

            if (!$this->classRoutes[$classFQCN]) {
                $serviceName = $this->toSnakeCaseNameConverter->normalize(explode($service->concat, $service->getName())[0] ?? '');
                $this->classRoutes[$classFQCN] = new Route(name: $serviceName);
            }
        }
        $classRouteAttr = $this->classRoutes[$classFQCN];

        $classPath = rtrim((string) $classRouteAttr->path, '/');
        $methodPath = ltrim((string) $route->path, '/');

        $mergedPath = $methodPath === ''
            ? ($classPath === '' ? '/' : $classPath)
            : ($classPath === '' ? '/' . $methodPath : $classPath . '/' . $methodPath);

        $className = $classRouteAttr->name
                     ?? $this->toSnakeCaseNameConverter->normalize(explode($service->concat, $service->getName())[0] ?? '');
        $methodName = $route->name ?? '';
        $concat = ($className !== '' && $methodName !== '') ? '_' : '';

        $mergedName = $className . $concat . $methodName;
        $route->path = $mergedPath;
        $route->name = $mergedName;

        $route->name = static::ROUTE_PREFIX . $route->name;
        $this->services[$version][$route->name] = $service;
        $this->routes[$version][$route->name] = new SymfonyRoute(
            $route->path,
            methods: $route->methods
        );

    }

    public function getService(string $serviceName, string $version = Info::DEFAULT_VERSION): Service
    {
        return $this->getServices($version)[$serviceName]
            ?? throw new RestResourceNotFoundException('Service "'.$serviceName.'" for version "'.$version.'" is not found on REST Service Map');
    }

    /** @return SymfonyRoute[] */
    public function getRoutes(string $version = Info::DEFAULT_VERSION): array
    {
        $this->assertVersion($version);
        return $this->routes[$version] ?? [];
    }

    /** @return Service[] */
    public function getServices(string $version = Info::DEFAULT_VERSION): array
    {
        $this->assertVersion($version);
        return $this->services[$version] ?? [];
    }

    private function assertVersion(string $version): void
    {
        if (!in_array($version, $this->getVersions(), true)) {
            throw new RestResourceNotFoundException('Version "'.$version.'" is not registered on REST Service Map');
        }
    }

    public function getRoute(string $routeName, string $version = Info::DEFAULT_VERSION): SymfonyRoute
    {
        return $this->getRoutes($version)[$routeName]
            ?? throw new RestResourceNotFoundException('Route "'.$routeName.'" for version "'.$version.'" is not found on REST Service Map');
    }

    public function getEnvelope(): string
    {
        if (!isset($this->envelope)) {
            $this->envelope = Package::ENV_REST.'/UFO-REST-' . Package::version();
        }
        return $this->envelope;
    }

    public function getVersions(): array
    {
        $this->activateGroup();
        return $this->versions;
    }

}