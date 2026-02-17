<?php

namespace Ufo\RestBundleTest;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}