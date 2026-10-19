<?php

namespace Ufo\RestBundleTest\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Routing\IApiRouteImport;
use Ufo\RestBundle\Routing\RestRouteImport;

class RestRouteImportTest extends TestCase
{
    public function testPointsToTheBundleRouter(): void
    {
        $import = new RestRouteImport();

        self::assertInstanceOf(IApiRouteImport::class, $import);
        self::assertSame(realpath(__DIR__.'/../../../config/router.yaml'), realpath($import->getResource()));
        self::assertSame('_rest_name_prefix', $import->getRouteNamePrefixAttribute());
    }
}
