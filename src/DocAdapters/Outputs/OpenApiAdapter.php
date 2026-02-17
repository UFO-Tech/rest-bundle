<?php
namespace Ufo\RestBundle\DocAdapters\Outputs;

use cebe\openapi\spec\Operation;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\DocAdapters\Traits\JsonSchemaDtoFormatTrait;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcTransport;
use Ufo\RestBundle\Controller\RestEntryPointController;
use Ufo\RestBundle\DocAdapters\Outputs\OpenApi\OpenApiSpecBuilder;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Package;
use Ufo\RestBundle\Services\RestServiceMap;

use function array_diff_key;
use function array_flip;
use function array_map;
use function array_unique;
use function current;
use function explode;
use function str_replace;

class OpenApiAdapter
{
    use JsonSchemaDtoFormatTrait;
    protected OpenApiSpecBuilder $builder;

    protected string $version = Info::DEFAULT_VERSION;

    public function __construct(
        protected RestServiceMap $serviceMap,
        protected RpcMainConfig $mainConfig,
        protected ChainParamConvertor $paramConvertor,
        protected RouterInterface $router
    ) {}

    public function adapt(bool $fullInfo = true, string $version = Info::DEFAULT_VERSION): array
    {
        $this->version = $version;
        $this->buildSignature();
        $this->buildServer();
        if ($fullInfo) {
            $this->buildServices();
            $this->buildComponents();
        }
        return $this->builder->build();
    }


    protected function buildSignature(): void
    {
        $this->builder = OpenApiSpecBuilder::createBuilder(
            title: $this->mainConfig->docsConfig->projectName,
            description: $this->mainConfig->docsConfig->projectDesc,
            apiVersion: $this->version,
            licenseName: Package::projectLicense(),
            contactName: Package::bundleName(),
            contactLink: Package::bundleDocumentation(),
            versions: $this->serviceMap->getVersions()
        );
    }
    protected function buildComponents(): void
    {
        if (empty($this->schemas)) return;
        $this->builder->setComponents($this->schemas);
    }

    protected function buildServer(): void
    {
        $http = RpcTransport::fromArray($this->mainConfig->url);
        $this->builder->addServer(
            url: $http->getDomainUrl().$this->router->generate(RestEntryPointController::REST_API_DOC),
            envelop: $this->serviceMap->getEnvelope(),
            rpcEnv: [
                ...['fromCache' => $this->serviceMap->isFromCache()],
                ...Package::ufoEnvironment(),
            ]
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
        $params = $service->getParams();
        $vars = [];
        foreach ($route->compile()->getPathVariables() as $variable) {
            if ($params[$variable] ?? false) {
                $vars[$variable] = $params[$variable];
                unset($params[$variable]);
            }
        }
        $method = $this->builder->buildOperation(
            operationId: $service->getName(),
            method: current($route->getMethods()),
            path: $route->getPath(),
            summary: $service->getDescription(),
            vars: $vars,
            deprecated: $service->isDeprecated(),
        );

        array_map(
            fn(ParamDefinition $param) => $this->buildParam($method, $param, $service),
            $params
        );

        $objSchema = [];
        if (!empty($service->getReturn())) {
            $objSchema = T::applyToSchema(
                $service->getReturn(),
                fn(array $schema) => $this->checkAndGetSchemaFromDesc($schema)
            );
        }

        $throws = [];
        foreach ($service->getThrows() as $rawThrow) {
            $throws = array_unique([
                ...$throws,
                ...explode('|', $rawThrow),
            ]);
        }
        $throwClasses = array_intersect_key($service->uses, array_flip(array_map(fn($throw) => str_replace('\\', '', $throw), $throws)));

        $this->builder->buildResult(
            operation: $method,
            description: T::jsonSchemaToTypeDescription($service->getReturn()),
            schema: $objSchema,
            throws: $throwClasses,
        );

        $this->builder->addTag($method, current(explode($service->concat, $service->getName())));

    }

    protected function buildParam(Operation $method, ParamDefinition $param, Service $service): void
    {
        $this->builder->buildParam(
            $method,
            $param->name,
            $param->description,
            !$param->isOptional(),
            $param->getDefault(),
            $this->schemaForParam($param, $service)
        );
    }

    protected function getParamConvertor(): ChainParamConvertor
    {
        return $this->paramConvertor;
    }

}
