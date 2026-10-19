<?php

namespace Ufo\RestBundle\EventDispatcher;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Ufo\JsonRpcBundle\Server\RpcLocaleNegotiator;

/**
 * Language of the REST documentation (OpenAPI / Postman) from `Accept-Language`,
 * the same way the RPC documentation picks it: method titles and descriptions come from RPC translations.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'setLocale', priority: 8)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'addLanguageHeaders')]
class RestDocLocaleListener
{
    public function __construct(
        protected RpcLocaleNegotiator $localeNegotiator,
    ) {}

    /**
     * Runs after RouterListener (32) and LocaleListener (16), so the route is known and the locale is not overwritten.
     */
    public function setLocale(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_rest_doc')) return;

        $this->localeNegotiator->applyLocale($request);
    }

    public function addLanguageHeaders(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_rest_doc')) return;

        $this->localeNegotiator->addLanguageHeaders($request, $event->getResponse());
    }
}
