<?php

namespace Ufo\RestBundleTest\Unit;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Package as JsonRpcPackage;
use Ufo\RestBundle\Package;

class PackageTest extends TestCase
{
    public function testProtocolSpecification(): void
    {
        $spec = Package::protocolSpecification();

        self::assertSame('https://datatracker.ietf.org/doc/html/rfc9110', $spec[Package::ENV_REST]);
        self::assertArrayHasKey(JsonRpcPackage::bundleName(), $spec);
        self::assertArrayHasKey('ufo-tech/rest-bundle', $spec);
    }

    public function testDescription(): void
    {
        self::assertStringStartsWith('REST api server from UFO-Tech', Package::description());
    }
}
