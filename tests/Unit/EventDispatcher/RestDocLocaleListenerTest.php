<?php

namespace Ufo\RestBundleTest\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Ufo\JsonRpcBundle\Server\RpcLocaleNegotiator;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\RestBundle\EventDispatcher\RestDocLocaleListener;

class RestDocLocaleListenerTest extends TestCase
{
    private function listener(array $locales): RestDocLocaleListener
    {
        $serviceMap = $this->createMock(ServiceMap::class);
        $serviceMap->method('getLocales')->willReturn($locales);
        return new RestDocLocaleListener(new RpcLocaleNegotiator($serviceMap));
    }

    private function request(bool $doc, ?string $language): Request
    {
        $request = Request::create('/rest', server: $language === null ? [] : ['HTTP_ACCEPT_LANGUAGE' => $language]);
        $request->setLocale('en');
        if ($doc) {
            $request->attributes->set('_rest_doc', true);
        }
        return $request;
    }

    private function handle(RestDocLocaleListener $listener, Request $request): Response
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $listener->setLocale(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $response = new Response();
        $listener->addLanguageHeaders(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        return $response;
    }

    public function testDocLocaleFromAcceptLanguage(): void
    {
        $request = $this->request(true, 'uk-UA,uk;q=0.9,en;q=0.5');
        $response = $this->handle($this->listener(['en', 'uk']), $request);

        self::assertSame('uk', $request->getLocale());
        self::assertSame('uk', $response->headers->get('Content-Language'));
        self::assertContains('Accept-Language', $response->getVary());
    }

    public function testUnknownLanguageFallsBackToTheFirstLocale(): void
    {
        $request = $this->request(true, 'pl');
        $this->handle($this->listener(['en', 'uk']), $request);

        self::assertSame('en', $request->getLocale());
    }

    public function testNoTranslationsNoHeaders(): void
    {
        $request = $this->request(true, 'uk');
        $response = $this->handle($this->listener([]), $request);

        self::assertSame('en', $request->getLocale());
        self::assertFalse($response->headers->has('Content-Language'));
        self::assertSame([], $response->getVary());
    }

    public function testNonDocRequestsAreIgnored(): void
    {
        $request = $this->request(false, 'uk');
        $response = $this->handle($this->listener(['en', 'uk']), $request);

        self::assertSame('en', $request->getLocale());
        self::assertFalse($response->headers->has('Content-Language'));
    }
}
