<?php

namespace Ufo\RestBundleTest\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\RestBundleTest\TestKernel;

class RestApiTest extends KernelTestCase
{
    protected static function getKernelClass(): string { return TestKernel::class; }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestKernel::$security = ['protected_api' => false, 'protected_doc' => false];
        restore_exception_handler();
    }

    private function request(string $path, string $method = 'GET', ?string $body = null, ?string $token = null): array
    {
        $request = Request::create('http://localhost'.$path, $method, server: ['CONTENT_TYPE' => 'application/json', ...($token === null ? [] : ['HTTP_UFO_RPC_TOKEN' => $token])], content: $body);
        $response = self::$kernel->handle($request);
        return [$response->getStatusCode(), json_decode($response->getContent(), true)];
    }

    public function testLegacyRoutesAndGroupImports(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertSame('/rest', $router->generate('ufo_rest_doc'));
        self::assertSame('/internal', $router->generate('internal_ufo_rest_doc'));
        self::assertSame('/public', $router->generate('public_ufo_rest_doc'));
        self::assertSame('/second', $router->generate('internal_second_ufo_rest_doc'));
        self::assertSame('/internal/v2/catalog/shared', $router->generate('internal_ufo_rest_api_catalog_shared_v2'));
        self::assertSame('/rpc', $router->generate('ufo_rpc_api_server'));
        self::assertSame([200, 'ok'], $this->request('/rest/catalog/status'));
    }

    public function testExecutionIsIsolatedAcrossSequentialRequests(): void
    {
        self::bootKernel();
        self::assertSame([200, 42], $this->request('/internal/catalog/price', 'POST', '{"price":42}'));
        self::assertSame(404, $this->request('/public/catalog/price', 'POST', '{"price":42,"api_group":"internal"}')[0]);
        self::assertSame([200, 'shared'], $this->request('/public/catalog/shared'));
        self::assertSame([200, 'ok'], $this->request('/rest/catalog/status'));
        self::assertSame(404, $this->request('/rest/catalog/shared')[0]);
        self::assertSame(404, $this->request('/internal/catalog/hidden', 'POST')[0]);
    }

    public function testVersionInheritanceAndRemoval(): void
    {
        self::bootKernel();
        self::assertSame([200, 'v2'], $this->request('/internal/v2/catalog/shared'));
        self::assertSame([200, ['id' => '3', 'value' => 'inherited']], $this->request('/internal/v2/catalog/echo/3', 'POST', '{"value":"inherited"}'));
        self::assertSame(404, $this->request('/internal/v2/catalog/price', 'POST', '{"price":1}')[0]);
        self::assertSame(404, $this->request('/public/v2/catalog/shared')[0]);
        self::assertSame([200, 'shared'], $this->request('/public/catalog/shared'));
    }

    public function testDocsAreScopedAndHaveTheCorrectEntryPoint(): void
    {
        self::bootKernel();
        foreach (['/internal' => ['shared', 'price', 'echo/{id}', 'format'], '/public' => ['shared', 'echo/{id}'], '/rest' => ['status'], '/second' => ['shared', 'price', 'echo/{id}', 'format']] as $base => $methods) {
            [$status, $doc] = $this->request($base);
            self::assertSame(200, $status, json_encode($doc));
            self::assertSame('http://localhost'.$base, $doc['servers'][0]['url']);
            $paths = array_map(fn($method) => '/catalog/'.$method, $methods);
            if ($base === '/rest') $paths[] = '/plain';
            self::assertEqualsCanonicalizing($paths, array_keys($doc['paths']));
            if ($base === '/internal') self::assertArrayHasKey('Format', $doc['components']['schemas']);
            if ($base === '/public') self::assertArrayNotHasKey('components', $doc);
            [$status, $postman] = $this->request($base.'/postman');
            self::assertSame(200, $status);
            $json = str_replace('\\/', '/', json_encode($postman));
            self::assertSame('http://localhost', array_column($postman['variables'], 'value', 'key')['base_url']);
            self::assertStringContainsString('{{base_url}}'.$base.'/catalog/', $json);
            if ($base === '/public') self::assertStringNotContainsString('/catalog/price', $json);
        }
        [$status, $doc] = $this->request('/internal/v2');
        self::assertSame(200, $status);
        self::assertArrayNotHasKey('/catalog/price', $doc['paths']);
        self::assertSame('http://localhost/internal/v2', $doc['servers'][0]['url']);
        self::assertSame(404, $this->request('/public/v2')[0]);
    }

    public function testParameterPrecedenceAndMalformedBody(): void
    {
        self::bootKernel();
        self::assertSame([200, ['id' => 'body', 'value' => 'body']], $this->request('/internal/catalog/echo/route?id=query&value=query', 'POST', '{"id":"body","value":"body"}'));
        self::assertSame(400, $this->request('/internal/catalog/price', 'POST', '{broken')[0]);
        self::assertSame(400, $this->request('/internal/catalog/price', 'POST', 'null')[0]);
    }

    public function testHttpMethodAndUnknownRoute(): void
    {
        self::bootKernel();
        self::assertSame(405, $this->request('/internal/catalog/shared', 'POST')[0]);
        self::assertSame(200, $this->request('/internal/catalog/shared', 'HEAD')[0]);
        [$status, $error] = $this->request('/internal/unknown');
        self::assertSame(404, $status);
        self::assertSame(404, $error['status']);
    }

    public function testGroupSecurityProviders(): void
    {
        TestKernel::$security = [
            'protected_api' => true, 'protected_doc' => false,
            'clients_tokens' => ['general-key'],
            'providers' => [
                'internal' => ['api_groups' => ['internal'], 'clients_tokens' => ['internal-key']],
                'public' => ['api_groups' => ['public'], 'clients_tokens' => ['public-key']],
            ],
        ];
        self::bootKernel();
        self::assertSame(401, $this->request('/internal/catalog/shared')[0]);
        self::assertSame(401, $this->request('/internal/catalog/shared', token: 'public-key')[0]);
        self::assertSame([200, 'shared'], $this->request('/internal/catalog/shared', token: 'internal-key'));
        self::assertSame([200, 'shared'], $this->request('/public/catalog/shared', token: 'public-key'));
        self::assertSame([200, 'ok'], $this->request('/rest/catalog/status', token: 'general-key'));
    }
    public function testProtectedDocumentationUsesItsGroupProvider(): void
    {
        TestKernel::$security = [
            'protected_api' => false, 'protected_doc' => true,
            'providers' => [
                'internal' => ['api_groups' => ['internal'], 'clients_tokens' => ['internal-key']],
                'public' => ['api_groups' => ['public'], 'clients_tokens' => ['public-key']],
            ],
        ];
        self::bootKernel();
        foreach (['/internal', '/internal/postman', '/internal/v2', '/internal/v2/postman'] as $path) {
            self::assertSame(401, $this->request($path)[0]);
            self::assertSame(401, $this->request($path, token: 'public-key')[0]);
            self::assertSame(200, $this->request($path, token: 'internal-key')[0]);
        }
        self::assertSame(200, $this->request('/public', token: 'public-key')[0]);
    }

    public function testRpcStillExecutesAlongsideRest(): void
    {
        self::bootKernel();
        [$status, $response] = $this->request('/rpc', 'POST', '{"jsonrpc":"2.0","id":1,"method":"catalog.status"}');
        self::assertSame(200, $status);
        self::assertSame('ok', $response['result']);
        self::assertSame([200, 'shared'], $this->request('/internal/catalog/shared'));
        [$status, $doc] = $this->request('/rpc');
        self::assertSame(200, $status);
        self::assertContains('catalog.status', array_column($doc['methods'], 'name'));
        self::assertNotContains('catalog.price', array_column($doc['methods'], 'name'));
    }

    public function testDefaultPostPreservesRpcRouteMetadata(): void
    {
        self::bootKernel();
        self::assertSame([200, 'plain'], $this->request('/rest/plain', 'POST'));
        self::assertSame(405, $this->request('/rest/plain')[0]);
        $rpcMap = self::getContainer()->get(\Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap::class);
        $route = $rpcMap->getService('plain.defaultPost')->getAttrCollection()->getAttribute(\Symfony\Component\Routing\Attribute\Route::class);
        self::assertNull($route->name);
        self::assertSame([], $route->methods);
    }

    public function testFormBodyAndJsonOverride(): void
    {
        self::bootKernel();
        $request = Request::create('http://localhost/internal/catalog/echo/route?id=query&value=query', 'POST',
            ['id' => 'form', 'value' => 'form'], server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: 'id=form&value=form');
        $response = self::$kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['id' => 'form', 'value' => 'form'], json_decode($response->getContent(), true));

        $request = Request::create('http://localhost/internal/catalog/echo/route?id=query&value=query', 'POST',
            ['id' => 'form', 'value' => 'form'], server: ['CONTENT_TYPE' => 'application/json'], content: '{"value":"json"}');
        $response = self::$kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['id' => 'form', 'value' => 'json'], json_decode($response->getContent(), true));
    }

}
