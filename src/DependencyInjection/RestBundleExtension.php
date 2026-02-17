<?php

namespace Ufo\RestBundle\DependencyInjection;

use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Yaml\Yaml;

use function in_array;
use function is_null;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class RestBundleExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');

        $path = $projectDir.'/config/routes/ufo_json_rpc.yaml';
        $data = Yaml::parseFile($path);
        $prefix = $data['ufo_json_rpc']['prefix'] ?? null;
        $container->setParameter('ufo_api_prefix', $prefix);
    }

}
