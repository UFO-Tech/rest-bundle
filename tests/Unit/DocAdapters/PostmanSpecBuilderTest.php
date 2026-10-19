<?php

namespace Ufo\RestBundleTest\Unit\DocAdapters;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RestBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\RestBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;

class PostmanSpecBuilderTest extends TestCase
{
    private function builder(): PostmanSpecBuilder
    {
        $builder = PostmanSpecBuilder::createBuilder('REST: Shop', 'Shop API', 'v1');
        $builder->addServer('http://localhost/internal');
        return $builder;
    }

    public function testRestMethodInAFolder(): void
    {
        $builder = $this->builder();
        $method = $builder->buildMethod('/catalog/echo/{id}', 'Echo', [['key' => 'Ufo-RPC-Token', 'value' => '{{apiToken}}']], 'catalog', 'PUT');
        $method->addParam(new ParamDefinition('value', ['string'], 'string'));

        self::assertInstanceOf(Method::class, $method);
        $item = $method->toArray();
        self::assertSame('/catalog/echo/{id}', $item['name']);
        self::assertSame('PUT', $item['request']['method']);
        self::assertSame('Echo', $item['request']['description']);
        self::assertSame('{{base_url}}/internal/catalog/echo/:id', $item['request']['url']);
        self::assertSame([['key' => 'Ufo-RPC-Token', 'value' => '{{apiToken}}']], $item['request']['header']);
        self::assertSame(['value' => ''], json_decode($item['request']['body']['raw'], true), 'the body holds only params, not a JSON-RPC envelope');

        $doc = $builder->build();
        self::assertSame('catalog', $doc['item'][0]['name']);
        self::assertSame('/catalog/echo/{id}', $doc['item'][0]['item'][0]['name']);
    }

    public function testMethodWithoutFolderAndDefaultGet(): void
    {
        $builder = $this->builder();
        $builder->buildMethod('/status', '');
        $doc = $builder->build();

        self::assertSame('/status', $doc['item'][0]['name']);
        self::assertSame('GET', $doc['item'][0]['request']['method']);
    }
}
