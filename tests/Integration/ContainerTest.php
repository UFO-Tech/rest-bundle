<?php

namespace Ufo\RestBundleTest\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelEvents;
use Ufo\RestBundle\EventDispatcher\RestControllerListener;
use Ufo\RestBundle\EventDispatcher\RestDocLocaleListener;
use Ufo\RestBundleTest\TestKernel;

class ContainerTest extends KernelTestCase
{
    protected static function getKernelClass(): string { return TestKernel::class; }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function priorities(string $event): array
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $priorities = [];
        foreach ($dispatcher->getListeners($event) as $listener) {
            if (is_array($listener) && is_object($listener[0])) {
                $priorities[$listener[0]::class.'::'.$listener[1]] = $dispatcher->getListenerPriority($event, $listener);
            }
        }
        return $priorities;
    }

    public function testRequestListenersRunInOrder(): void
    {
        self::bootKernel();
        $priorities = $this->priorities(KernelEvents::REQUEST);

        // After RouterListener (32): the route is matched; after LocaleListener (16): the locale is not overwritten.
        self::assertSame(30, $priorities[RestControllerListener::class.'::process']);
        self::assertSame(8, $priorities[RestDocLocaleListener::class.'::setLocale']);
    }

    public function testResponseListener(): void
    {
        self::bootKernel();

        self::assertArrayHasKey(RestDocLocaleListener::class.'::addLanguageHeaders', $this->priorities(KernelEvents::RESPONSE));
    }
}
