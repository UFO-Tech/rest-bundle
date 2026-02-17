<?php
namespace Ufo\RestBundle\DocAdapters\Outputs;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\RestBundle\Controller\RestEntryPointController;
use Ufo\RestBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\RestBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcTransport;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Services\RestServiceMap;

use function explode;

class PostmanAdapter
{
    protected PostmanSpecBuilder $postmanSpecBuilder;
    protected string $version = Info::DEFAULT_VERSION;

    public function __construct(
        protected RestServiceMap $serviceMap,
        protected RpcMainConfig $mainConfig,
        protected RouterInterface $router
    ) {}

    public function adapt(string $version = Info::DEFAULT_VERSION): array
    {
        $this->version = $version;
        $this->buildSignature();
        $this->buildServer();
        $this->buildServices();
        return $this->postmanSpecBuilder->build();
    }

    protected function buildSignature(): void
    {
        $this->postmanSpecBuilder = PostmanSpecBuilder::createBuilder(
            name: 'REST: ' . $this->mainConfig->docsConfig->projectName,
            description: $this->mainConfig->docsConfig->projectDesc,
            version: $this->version,
        );
    }

    protected function buildServer(): void
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $path = $this->router->generate(RestEntryPointController::REST_API_DOC);
        if ($this->version !== Info::DEFAULT_VERSION) {
            $path = $this->router->generate(RestEntryPointController::REST_API_DOC_VER, ['ver' => $this->version]);
        }
        $this->postmanSpecBuilder->addVariable('base_url', $http->getDomainUrl());

        $this->postmanSpecBuilder->addServer(
            $http->getDomainUrl() . $path,
        );
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
        $variableApiToken = $this->postmanSpecBuilder->addVariable('apiToken', '!changeMe!');
        $headers = [
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ],
            [
                'key' => $this->mainConfig->securityConfig->tokenName,
                'value' => '{{' . $variableApiToken->key . '}}',
            ]
        ];

        $method = $this->postmanSpecBuilder->buildMethod(
            name: $route->getPath(),
            description: $service->getDescription(),
            headers: $headers,
            folder: $service->procedure,
            httpMethod: $route->getMethods()[0]
        );

        $params = $service->getParams();
//        $vars = [];
        foreach ($route->compile()->getPathVariables() as $variable) {
            if ($params[$variable] ?? false) {
//                $vars[$variable] = $params[$variable];
                unset($params[$variable]);
            }
        }

        foreach ($params as $param) {
            $this->buildParam($method, $param);
        }
    }

    protected function buildParam(Method $method, ParamDefinition $param): void
    {
        $this->postmanSpecBuilder->buildParam(
            $method,
            $param
        );
    }

}
