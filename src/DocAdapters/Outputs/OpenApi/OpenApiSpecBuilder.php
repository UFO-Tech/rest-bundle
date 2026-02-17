<?php

namespace Ufo\RestBundle\DocAdapters\Outputs\OpenApi;

use cebe\openapi\spec\Contact;
use cebe\openapi\spec\Info;
use cebe\openapi\spec\License;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Server;
use Ufo\RpcObject\RPC\Param;
use Ufo\RestBundle\Exceptions\RestApplicationException;
use Ufo\RestBundle\Exceptions\RestBadRequestException;
use Ufo\RestBundle\Exceptions\RestErrorInterface;
use Ufo\RestBundle\Exceptions\RestErrorNormalizer;
use Ufo\RestBundle\Package;

use function array_unique;
use function json_decode;
use function json_encode;
use function strtolower;

class OpenApiSpecBuilder
{
    public const string OPEN_API_VER = "3.1.0";
    public const string OPEN_API_SERVER_NAME = "UFO REST API Server";

    protected OpenApi $openapi;
    protected array $paths = [];

    protected array $servers = [];

    public static function createBuilder(
        string $title,
        string $description = '',
        string $apiVersion = 'latest',
        string $openApiVersion = self::OPEN_API_VER,
        ?string $licenseName = null,
        ?string $contactName = null,
        ?string $contactLink = null,
        array $versions = []
    ): self
    {
        $self = new static();

        $self->openapi = new OpenApi([
            'openapi' => $openApiVersion,
            'info' => new Info([
                'title' => $title,
                'version' => $apiVersion,
                'description' => $description,
                'license' => $licenseName ? new License(['name' => $licenseName]) : null,
                'contact' => $contactName ? new Contact(['name' => $contactName, 'url' => $contactLink]) : null,
                'x-versions' => $versions,
            ]),
            'paths' => new Paths([]),
        ]);

        return $self;
    }

    public function addPath(string $path, string $method, Operation $operation): void
    {
        if (!isset($this->paths[$path])) {
            $this->paths[$path] = new PathItem([]);
        }

        $this->paths[$path]->{$method} = $operation;
    }

    public function build(): array
    {
        $data = [
            'openapi',
            'info',
            'servers',
            'paths',
            'components',
        ];
        $this->openapi->servers = $this->servers;
        $this->openapi->paths = new Paths($this->paths);
        $openapi = json_decode(json_encode($this->openapi->getSerializableData()), true);
        $apiDoc = [];
        foreach ($data as $key) {
            if ($openapi[$key] ?? false) {
                $apiDoc[$key] = $openapi[$key];
            }
        }
        return $apiDoc;
    }

    public function addServer(
        string $url,
        string $envelop,
        ?string $name = self::OPEN_API_SERVER_NAME,
        array $rpcEnv = []
    ): void
    {
        $this->servers = [
            new Server([
                'url' => $url,
                'description' => $name . ' ' . Package::version(),
//                'name' => $name . ' ' . Package::version(),
//                'description' => Package::description(),
                'x-ufo' => [
                    'envelop' => $envelop,
                    'environment' => $rpcEnv,
                    'documentation' => [
                        ...Package::protocolSpecification(),
                    ]
                ]
            ])
        ];
    }

    public function buildOperation(
        string $operationId,
        string $method,
        string $path,
        string $summary,
        array $vars = [],
        bool $deprecated = false
    ): Operation
    {
        /**
         * @var Param $var
         */
        $params = [];
        foreach ($vars as $name => $var) {
            $params[] = new Parameter([
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => new Schema([
                    'type' => 'string',
                ]),
            ]);
        }
        $operation = new Operation([
            ...[
                'summary' => empty($summary) ? $operationId : $summary,
                'operationId' => $operationId,
                'responses' => [
                    '200' => new Response([
                        'description' => 'OK',
                    ]),
                ],
                'parameters' => $params,
            ],
            ...($deprecated ? ['deprecated' => true] : [])

        ]);

        $this->addPath($path, strtolower($method), $operation);

        return $operation;
    }

    public function buildParam(
        Operation $method,
        string $name,
        string $description,
        bool $required = true,
        mixed $default = null,
        array $schema = [],
    ): void
    {
        if (!$method->requestBody) {
            $method->requestBody = new RequestBody([
                'content' => [
                    'application/json' => new MediaType([
                        'schema' => new Schema([
                            'type' => 'object',
                            'required' => [],
                            'properties' => [],
                        ]),
                    ]),
                ],
            ]);
        }
        $schemas = $method->requestBody->content['application/json']->schema;
        $requiredList = $schemas->required ?? [];
        $properties = $schemas->properties ?? [];

        $property = [
            ...$schema,
        ];

        if (!empty($description)) {
            $property['description'] = $description;
        }

        if ($required) {
            $requiredList[] = $name;
        } else {
            $property = [
                ...(!$required? ['default' => $default] : []),
                ...$property
            ];
        }
        $properties[$name] = new Schema($property);

        $schemas->required = $requiredList;
        $schemas->properties = $properties;
    }

    /**
     * @param array<string,class-string> $throws
     */
    public function buildResult(
        Operation $operation,
        string $description,
        array $schema,
        array $throws = []
    ): void
    {
        $schema = new Schema($schema);

        $responses = $operation->responses ?? [];

        $responses['200'] = new Response([
            'description' => $description ?? 'Successful operation',
            'content' => [
                'application/json' => new MediaType([
                    'schema' => $schema,
                ]),
            ],
        ]);

        $codes = [];
        foreach ($throws as $errorName => $exception) {
            if ($errorName === 'Throwable') {
                $exception = RestApplicationException::class;
            }
            try {
                throw new $exception();
            } catch (\Throwable $e) {
                if ($e instanceof $exception) {
                    try {
                        $e = RestErrorNormalizer::normalizeError($e);
                    } catch (RestErrorInterface $e) {
                    }
                } else {
                    $e = new RestBadRequestException();
                }
                if (isset($codes[$e->getCode()])) continue;
                $codes[$e->getCode()] = true;
                $responses[$e->getCode()] = new Response([
                    'description' => $e->getMessage(),
                ]);
            }
        }

        $operation->responses = $responses;
    }

    /**
     * @param Operation $operation
     * @param string $tag
     */
    public function addTag(Operation $operation, string $tag): void
    {
        $operation->tags = array_unique([
            ...$operation->tags,
            $tag
        ]);
    }

    public function setComponents(array $schema): void
    {
        $this->openapi->components = new \stdClass();
        $this->openapi->components->schemas = $schema;
    }

}