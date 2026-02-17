<?php

namespace Ufo\RestBundle\Services;

use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\SnakeCaseToCamelCaseNameConverter;
use Ufo\RpcObject\RPC\Info;
use Ufo\RestBundle\Package;
use Ufo\JsonRpcBundle\Server\ServiceMap\IServiceHolder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_replace;
use function array_unique;
use function explode;
use function in_array;
use function usort;
use function version_compare;

#[AutoconfigureTag(IServiceHolder::TAG)]
class RestServiceMap implements IServiceHolder
{
    const string ROUTE_PREFIX = 'ufo_rest_api_';

    protected string $envelope;
    /**
     * @var array<string,array<string,Service>>
     */
    protected array $services = [];
    protected array $excludePrevious = [];

    /**
     * @var array<class-string,Route>
     */
    protected array $classRoutes = [];

    /**
     * @var array<string,array<string,SymfonyRoute>>
     */
    protected array $routes = [];

    /**
     * @var array<string,string>
     */
    protected array $routesMap = [];

    public function __construct(
        protected IServiceHolder $serviceMap,
        protected SnakeCaseToCamelCaseNameConverter $toCamelCaseNameConverter,
        protected CamelCaseToSnakeCaseNameConverter $toSnakeCaseNameConverter
    ) {
        $this->init();
    }

    protected function init(): void
    {
        $versions = $this->serviceMap->getVersions();
        foreach ($versions as $version) {
            foreach ($this->serviceMap->getServices($version) as $service) {
                /** @var Route $route */
                if ($route = $service->getAttrCollection()->getAttribute(Route::class)) {
                    if (!$route->name) {
                        $route->name = $this->toSnakeCaseNameConverter->normalize($service->getMethodName());
                    }
                    if (empty($route->methods)) {
                        $route->methods = ['POST'];
                    }
                    $this->addService(clone $route, $service, $version);
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
        $this->routesMap[$service->getName()] = $route->name;
        $this->excludePrevious[$version] = array_unique(array_merge($this->excludePrevious[$version] ?? [], $service->apiClassInfo->removedMethods));

    }

    public function getService(string $serviceName, string $version = Info::DEFAULT_VERSION): Service
    {
        return $this->services[$version][$serviceName]
               ?? throw new RestResourceNotFoundException('Service "'.$serviceName.'" for version "'.$version.'" is not found on REST Service Map');
    }

    /**
     * @return SymfonyRoute[]
     */
    public function getRoutes(string $version = Info::DEFAULT_VERSION): array
    {
        $versions = $this->getVersions();
        if (!in_array($version, $versions, true)) {
            throw new RuntimeException('Version "'.$version.'" is not registered on REST Service Map');
        }
        usort($versions, 'version_compare');

        $result = [];

        foreach ($versions as $ver) {
            if (version_compare($ver, $version, '<=')) {
                $result = array_replace($result, $this->routes[$ver]);
                $excludeServices = $this->excludePrevious[$version] ?? [];
                $excludeRoutes = array_intersect_key($this->routesMap, array_flip($excludeServices));
                $result = array_diff_key($result, array_flip($excludeRoutes));
            }
        }
        return $result;
    }

    /**
     * @param string $version
     * @return Service[]
     */
    public function getServices(string $version = Info::DEFAULT_VERSION): array
    {
        $versions = $this->getVersions();
        if (!in_array($version, $versions, true)) {
            throw new RuntimeException('Version "'.$version.'" is not registered on REST Service Map');
        }
        usort($versions, 'version_compare');

        $result = [];

        foreach ($versions as $ver) {
            if (version_compare($ver, $version, '<=')) {
                $result = array_replace($result, $this->services[$ver]);
                $excludeServices = $this->excludePrevious[$version] ?? [];
                $excludeRoutes = array_intersect_key($this->routesMap, array_flip($excludeServices));
                $result = array_diff_key($result, array_flip($excludeRoutes));
            }
        }

        return $result;
    }

    /**
     * @param string $routeName
     * @param string $version
     * @return SymfonyRoute
     * @throws RestResourceNotFoundException
     */
    public function getRoute(string $routeName, string $version = Info::DEFAULT_VERSION): SymfonyRoute
    {
        return $this->routes[$version][$routeName] ?? throw new RestResourceNotFoundException('Route "'.$routeName.'" for version "'.$version.'" is not adapt for REST Service Map');
    }

    public function getEnvelope(): string
    {
        if (!isset($this->envelope)) {
            $this->envelope = Package::ENV_REST.'/UFO-REST-' . Package::version();
        }
        return $this->envelope;
    }

    public function isFromCache(): bool
    {
        return method_exists($this->serviceMap, 'isFromCache') ? $this->serviceMap->isFromCache() : false;
    }

    public function getVersions(): array
    {
        return array_keys($this->services);
    }

}