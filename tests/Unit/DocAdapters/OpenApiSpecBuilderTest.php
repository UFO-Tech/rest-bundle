<?php

namespace Ufo\RestBundleTest\Unit\DocAdapters;

use PHPUnit\Framework\TestCase;
use Ufo\RestBundle\DocAdapters\Outputs\OpenApi\OpenApiSpecBuilder;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Package;
use Ufo\RpcError\CustomApplicationException;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\RpcInvalidTokenException;

class OpenApiSpecBuilderTest extends TestCase
{
    private function builder(): OpenApiSpecBuilder
    {
        return OpenApiSpecBuilder::createBuilder(
            title: 'Shop',
            description: 'Shop API',
            apiVersion: 'v2',
            licenseName: 'MIT',
            contactName: 'ufo-tech/rest-bundle',
            contactLink: 'https://example.com',
            versions: ['v1', 'v2'],
        );
    }

    public function testSignatureAndServer(): void
    {
        $builder = $this->builder();
        $builder->addServer('http://localhost/rest/v2', 'REST/UFO-REST-1', rpcEnv: ['env' => 'test']);
        $doc = $builder->build();

        self::assertSame(OpenApiSpecBuilder::OPEN_API_VER, $doc['openapi']);
        self::assertSame('Shop', $doc['info']['title']);
        self::assertSame('Shop API', $doc['info']['description']);
        self::assertSame('v2', $doc['info']['version']);
        self::assertSame(['v1', 'v2'], $doc['info']['x-versions']);
        self::assertSame('MIT', $doc['info']['license']['name']);
        self::assertSame(['name' => 'ufo-tech/rest-bundle', 'url' => 'https://example.com'], $doc['info']['contact']);
        self::assertCount(1, $doc['servers']);
        self::assertSame('http://localhost/rest/v2', $doc['servers'][0]['url']);
        self::assertStringStartsWith(OpenApiSpecBuilder::OPEN_API_SERVER_NAME, $doc['servers'][0]['description']);
        self::assertSame('REST/UFO-REST-1', $doc['servers'][0]['x-ufo']['envelop']);
        self::assertSame(['env' => 'test'], $doc['servers'][0]['x-ufo']['environment']);
        self::assertSame(Package::protocolSpecification(), $doc['servers'][0]['x-ufo']['documentation']);
        self::assertArrayNotHasKey('paths', $doc, 'empty sections are dropped');
        self::assertArrayNotHasKey('components', $doc);
    }

    public function testOperation(): void
    {
        $builder = $this->builder();
        $builder->buildOperation('catalog.echo', 'POST', '/catalog/echo/{id}', 'Echo', ['id' => 'id'], true, 'Returns what it gets');
        $operation = $builder->build()['paths']['/catalog/echo/{id}']['post'];

        self::assertSame('Echo', $operation['summary']);
        self::assertSame('Returns what it gets', $operation['description']);
        self::assertSame('catalog.echo', $operation['operationId']);
        self::assertTrue($operation['deprecated']);
        self::assertSame([['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], $operation['parameters']);
        self::assertSame(['description' => 'OK'], $operation['responses']['200']);
    }

    public function testPathVariableSchema(): void
    {
        $builder = $this->builder();
        $builder->buildOperation('catalog.item', 'GET', '/catalog/{id}/{slug}', 'Item', ['id' => ['type' => 'integer'], 'slug' => []]);
        $parameters = $builder->build()['paths']['/catalog/{id}/{slug}']['get']['parameters'];

        self::assertSame(['type' => 'integer'], $parameters[0]['schema']);
        self::assertSame(['type' => 'string'], $parameters[1]['schema'], 'without a schema a path variable is a string');
    }

    public function testSummaryFallsBackToOperationId(): void
    {
        $builder = $this->builder();
        $builder->buildOperation('catalog.status', 'GET', '/catalog/status', '');
        $operation = $builder->build()['paths']['/catalog/status']['get'];

        self::assertSame('catalog.status', $operation['summary']);
        self::assertArrayNotHasKey('description', $operation);
        self::assertArrayNotHasKey('deprecated', $operation);
    }

    public function testSeveralMethodsOnOnePath(): void
    {
        $builder = $this->builder();
        $builder->buildOperation('item.get', 'GET', '/item', 'get');
        $builder->buildOperation('item.put', 'PUT', '/item', 'put');

        self::assertSame(['get', 'put'], array_keys($builder->build()['paths']['/item']));
    }

    public function testParamsGoToTheJsonBody(): void
    {
        $builder = $this->builder();
        $operation = $builder->buildOperation('catalog.find', 'POST', '/catalog/find', 'Find');
        $builder->buildParam($operation, 'query', 'Search text', schema: ['type' => 'string']);
        $builder->buildParam($operation, 'limit', '', false, 20, ['type' => 'integer']);
        $schema = $builder->build()['paths']['/catalog/find']['post']['requestBody']['content']['application/json']['schema'];

        self::assertSame('object', $schema['type']);
        self::assertSame(['query'], $schema['required']);
        self::assertSame(['type' => 'string', 'description' => 'Search text'], $schema['properties']['query']);
        self::assertEquals(['default' => 20, 'type' => 'integer'], $schema['properties']['limit']);
    }

    public function testResultAndErrorResponses(): void
    {
        $builder = $this->builder();
        $operation = $builder->buildOperation('catalog.get', 'GET', '/catalog/get', 'Get');
        $builder->buildResult($operation, 'Item', ['type' => 'object'], [
            'RpcDataNotFoundException' => RpcDataNotFoundException::class,
            'RestResourceNotFoundException' => RestResourceNotFoundException::class,
            'RpcInvalidTokenException' => RpcInvalidTokenException::class,
            'Throwable' => \Throwable::class,
            'CustomApplicationException' => CustomApplicationException::class,
        ]);
        $responses = $builder->build()['paths']['/catalog/get']['get']['responses'];

        self::assertSame('Item', $responses['200']['description']);
        self::assertSame(['type' => 'object'], $responses['200']['content']['application/json']['schema']);
        self::assertSame(['200', '404', '401', '400'], array_map('strval', array_keys($responses)), 'one response per status');
        self::assertSame('Rest application error', $responses['400']['description']);
    }

    public function testTagsAreUnique(): void
    {
        $builder = $this->builder();
        $operation = $builder->buildOperation('catalog.get', 'GET', '/catalog/get', 'Get');
        $builder->addTag($operation, 'catalog');
        $builder->addTag($operation, 'catalog');
        $builder->addTag($operation, 'shop');

        self::assertSame(['catalog', 'shop'], $builder->build()['paths']['/catalog/get']['get']['tags']);
    }

    public function testComponents(): void
    {
        $builder = $this->builder();
        $builder->setComponents(['Item' => ['type' => 'object']]);

        self::assertSame(['schemas' => ['Item' => ['type' => 'object']]], $builder->build()['components']);
    }
}
