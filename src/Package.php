<?php

namespace Ufo\RestBundle;

use Ufo\Packages\UfoPackage;
use Ufo\JsonRpcBundle\Package as JsonRpcPackage;

final class Package extends UfoPackage
{
    const string ENV_REST = 'REST (RFC 9110, 9457)';
    
    public static function description(): string
    {
        $self = static::getInstance(static::class);
        $self->description = 'REST api server from UFO-Tech';
        $self->description .= PHP_EOL.PHP_EOL;
        $self->description .= UfoPackage::UFO_DESCRIPTION;

        return parent::description();
    }

    public static function protocolSpecification(): array
    {
        $self = static::getInstance(static::class);
        return [
            self::ENV_REST => 'https://datatracker.ietf.org/doc/html/rfc9110',
            JsonRpcPackage::bundleName() => JsonRpcPackage::bundleDocumentation(),
            $self->bundleName => $self->homepage,
        ];
    }

}
