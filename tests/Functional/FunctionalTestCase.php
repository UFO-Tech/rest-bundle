<?php

namespace Ufo\RestBundleTest\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ufo\RestBundleTest\TestKernel;

abstract class FunctionalTestCase extends KernelTestCase
{
    protected static function getKernelClass(): string { return TestKernel::class; }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestKernel::$security = ['protected_api' => false, 'protected_doc' => false];
        restore_exception_handler();
    }

    protected function send(string $path, string $method = 'GET', ?string $body = null, array $headers = []): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        return self::$kernel->handle(Request::create('http://localhost'.$path, $method, server: $server, content: $body));
    }

    /** @return array{int, mixed} */
    protected function json(string $path, string $method = 'GET', ?string $body = null, array $headers = []): array
    {
        $response = $this->send($path, $method, $body, $headers);
        return [$response->getStatusCode(), json_decode($response->getContent(), true)];
    }
}
