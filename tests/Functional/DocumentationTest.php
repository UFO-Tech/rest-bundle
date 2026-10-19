<?php

namespace Ufo\RestBundleTest\Functional;

use Ufo\RestBundle\Package;
use Ufo\RestBundleTest\TestKernel;

class DocumentationTest extends FunctionalTestCase
{
    public function testOpenApiDocument(): void
    {
        self::bootKernel();
        [$status, $doc] = $this->json('/internal');
        self::assertSame(200, $status);

        self::assertSame('3.1.0', $doc['openapi']);
        self::assertSame('REST tests', $doc['info']['title']);
        self::assertSame('v1', $doc['info']['version']);
        self::assertSame(['v1', 'v2'], $doc['info']['x-versions']);
        self::assertStringStartsWith(Package::ENV_REST, $doc['servers'][0]['x-ufo']['envelop']);

        $echo = $doc['paths']['/catalog/echo/{id}']['post'];
        self::assertSame('catalog.echoValue', $echo['operationId']);
        self::assertSame(['catalog'], $echo['tags']);
        self::assertSame([['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], $echo['parameters']);
        $body = $echo['requestBody']['content']['application/json']['schema'];
        self::assertSame(['value'], $body['required']);
        self::assertArrayNotHasKey('id', $body['properties'], 'a path variable is not a body param');
        self::assertSame('array', $echo['responses']['200']['content']['application/json']['schema']['type']);

        self::assertArrayNotHasKey('requestBody', $doc['paths']['/catalog/shared']['get']);
        self::assertArrayHasKey('Format', $doc['components']['schemas']);
    }

    public function testErrorsAndDeprecationInOpenApi(): void
    {
        self::bootKernel();
        [, $doc] = $this->json('/errors-api');

        self::assertArrayHasKey('404', $doc['paths']['/errors/not-found']['get']['responses']);
        self::assertTrue($doc['paths']['/errors/old']['get']['deprecated']);
        self::assertArrayNotHasKey('deprecated', $doc['paths']['/errors/any']['get']);
    }

    public function testVersionedDocument(): void
    {
        self::bootKernel();
        [$status, $doc] = $this->json('/internal/v2');
        self::assertSame(200, $status);

        self::assertSame('v2', $doc['info']['version']);
        self::assertSame('http://localhost/internal/v2', $doc['servers'][0]['url']);
        self::assertArrayHasKey('/catalog/shared', $doc['paths']);
        self::assertArrayNotHasKey('/catalog/price', $doc['paths'], 'removed in v2');
        self::assertArrayHasKey('/catalog/echo/{id}', $doc['paths'], 'inherited from v1');

        [$status, $postman] = $this->json('/internal/v2/postman');
        self::assertSame(200, $status);
        self::assertSame('v2', $postman['info']['version']);
    }

    public function testPostmanCollection(): void
    {
        self::bootKernel();
        [$status, $postman] = $this->json('/internal/postman');
        self::assertSame(200, $status);

        self::assertStringStartsWith('REST: REST tests [internal]', $postman['info']['name']);
        self::assertSame(['base_url' => 'http://localhost', 'apiToken' => '!changeMe!'], array_column($postman['variables'], 'value', 'key'));
        self::assertSame('catalog', $postman['item'][0]['name']);
        $items = array_column($postman['item'][0]['item'], null, 'name');
        self::assertSame('POST', $items['/catalog/echo/{id}']['request']['method']);
        self::assertSame('{{base_url}}/internal/catalog/echo/:id', $items['/catalog/echo/{id}']['request']['url']);
        self::assertSame(['value' => ''], json_decode($items['/catalog/echo/{id}']['request']['body']['raw'], true));
        self::assertSame('GET', $items['/catalog/shared']['request']['method']);
        self::assertContains('Ufo-RPC-Token', array_column($items['/catalog/shared']['request']['header'], 'key'));

        [, $general] = $this->json('/rest/postman');
        self::assertStringNotContainsString('[general]', $general['info']['name']);
    }

    public function testDocumentationRoutesUseTheHttpMethodGet(): void
    {
        self::bootKernel();
        self::assertSame(405, $this->json('/internal', 'POST')[0]);
    }

    public function testDocumentationIsProtectedWithProtectedDoc(): void
    {
        TestKernel::$security = ['protected_api' => false, 'protected_doc' => true, 'clients_tokens' => ['secret']];
        self::bootKernel();

        [$status, $problem] = $this->json('/rest');
        self::assertSame(401, $status);
        self::assertSame('RFC 9457', $problem['_error_object_standard']);
        self::assertSame(401, $this->json('/rest/postman')[0]);
        self::assertSame(200, $this->json('/rest', headers: ['Ufo-RPC-Token' => 'secret'])[0]);
        self::assertSame([200, 'ok'], $this->json('/rest/catalog/status'), 'calls are not protected by protected_doc');
    }
}
