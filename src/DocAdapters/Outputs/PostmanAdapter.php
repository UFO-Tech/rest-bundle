<?php
namespace Ufo\RestBundle\DocAdapters\Outputs;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\AbstractPostmanAdapter;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\RestBundle\Controller\RestEntryPointController;
use Ufo\RestBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RpcTransport;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Services\RestServiceMap;

class PostmanAdapter extends AbstractPostmanAdapter
{
    protected const string PREFIX = 'REST';
    protected const string ROUTE = RestEntryPointController::REST_API_DOC;
    protected const string ROUTE_VER = RestEntryPointController::REST_API_DOC_VER;

    public function __construct(
        protected RestServiceMap $serviceMap,
        RpcMainConfig $mainConfig,
        RouterInterface $router,
        ApiGroupContext $apiGroupContext,
        protected RequestStack $requestStack,
    ) {
        parent::__construct($mainConfig, $router, $apiGroupContext);
    }

    protected function createSpecBuilder(string $name, string $description, string $version): PostmanSpecBuilder
    {
        return PostmanSpecBuilder::createBuilder($name, $description, $version);
    }

    protected function getTransport(): RpcTransport
    {
        return RpcTransport::fromArray(parse_url($this->requestStack->getCurrentRequest()?->getUri() ?? '') ?: $this->mainConfig->url);
    }

    protected function buildServices(): void
    {
        $routes = $this->serviceMap->getRoutes($this->version);
        foreach ($this->serviceMap->getServices($this->version) as $routeName => $service) {
            $this->buildService(
                $service,
                $routes[$routeName]
                ?? throw new RestResourceNotFoundException('Route "'.$routeName.'" for version "'.$this->version.'" is not adapt for REST Service Map')
            );
        }
    }

    protected function buildService(Service $service, Route $route): void
    {
        $method = $this->postmanSpecBuilder->buildMethod(
            name: $route->getPath(),
            description: $service->getDescription(),
            headers: $this->buildHeaders(),
            folder: $service->procedure,
            httpMethod: $route->getMethods()[0]
        );

        $params = $service->getParams();
        foreach ($route->compile()->getPathVariables() as $variable) {
            if ($params[$variable] ?? false) {
                unset($params[$variable]);
            }
        }

        foreach ($params as $param) {
            $this->buildParam($method, $param);
        }
    }

}
