<?php

namespace Bumblebee\Bundle\DependencyInjection;

use Bumblebee\Bundle\Exception\RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BumblebeeExtension extends Extension
{
    /**
     * @inheritdoc
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $configuration = new BumblebeeConfiguration( ! $container->getParameter("kernel.debug"));
        $config = $this->processConfiguration($configuration, $config);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . "/../Resources"));
        $loader->load("services.xml");

        $container->getDefinition("bumblebee.types_provider")->replaceArgument(0, $config["resource"]);

        $this->setupTypes($config["metadata_cache"], $container);
        $this->setupTransformers($config["custom_transformers"], $container);
        $this->setupConfigCompilers($config["custom_configuration_compilers"], $container);

        if ($config["compile"]) {
            $container->removeDefinition("bumblebee.transformer");
            $container->setAlias("bumblebee.transformer", "bumblebee.compiled_transformer");
        }
    }

    /**
     * @inheritdoc
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new BumblebeeConfiguration( ! $container->getParameter("kernel.debug"));
    }

    protected function setupTypes($cacheConfig, ContainerBuilder $cnt)
    {
        $mdCacheType = $cacheConfig["type"];
        if ($mdCacheType || ! $cacheConfig["caching_service"]) {
            $mdCacheType = $mdCacheType ?: "file";

            if ($mdCacheType !== "file") {
                throw new \Exception("Not supported yet");
            }

            $cacheServiceDefinition = new Definition('Doctrine\Common\Cache\FilesystemCache', [
                $cnt->getParameter("kernel.cache_dir") . "/bumblebee/md"
            ]);
            $cnt->setDefinition("bumblebee.metadata_cache", $cacheServiceDefinition);
        } else {
            $cnt->removeDefinition("bumblebee.metadata_cache");
            $cnt->setAlias("bumblebee.metadata_cache", $cacheConfig["caching_service"]);
        }
    }

    protected function setupTransformers($customTransformers, ContainerBuilder $cnt)
    {
        $transformers = [
            "array_to_object"  => 'Bumblebee\TypeTransformer\ArrayToObjectTransformer',
            "chain"            => 'Bumblebee\TypeTransformer\ChainTransformer',
            "datetime_text"    => 'Bumblebee\TypeTransformer\DateTimeTextTransformer',
            "function"         => 'Bumblebee\TypeTransformer\FunctionTransformer',
            "number_format"    => 'Bumblebee\TypeTransformer\NumberFormatTransformer',
            "object_array"     => 'Bumblebee\TypeTransformer\ObjectArrayTransformer',
            "typed_collection" => 'Bumblebee\TypeTransformer\TypedCollectionTransformer',
            "array_pick"       => 'Bumblebee\TypeTransformer\ArrayPickTransformer'
        ];

        if ($customTransformers) {
            foreach ($customTransformers as $tranName => $className) {
                if ( ! class_exists($className)) {
                    throw new RuntimeException("Class '{$className}' was not found");
                }
                if ( ! is_a($className, 'Bumblebee\TypeTransformer\TypeTransformer', true)) {
                    throw new RuntimeException("Class '{$className}' does not implement Bumblebee\\TypeTransformer\\TypeTransformer");
                }
            }
            $transformers = $customTransformers + $transformers;
        }
        $cnt->getDefinition("bumblebee.transformers")->replaceArgument(0, $transformers);
    }

    protected function setupConfigCompilers($customCompilers, ContainerBuilder $cnt)
    {
        $configCompilers = [
            "array_object" => 'Bumblebee\Configuration\ArrayConfiguration\ArrayToObjectConfigurationCompiler',
            "object_array" => 'Bumblebee\Configuration\ArrayConfiguration\ObjectArrayConfigurationCompiler',
            "num_format"   => 'Bumblebee\Configuration\ArrayConfiguration\NumberFormatConfigurationCompiler',
            "collection"   => 'Bumblebee\Configuration\ArrayConfiguration\TypedCollectionConfigurationCompiler',
            "date_text"    => 'Bumblebee\Configuration\ArrayConfiguration\DateTimeConfigurationCompiler',
            "chain"        => 'Bumblebee\Configuration\ArrayConfiguration\ChainConfigurationCompiler',
            "func"         => 'Bumblebee\Configuration\ArrayConfiguration\FunctionConfigurationCompiler',
            "array_pick"   => 'Bumblebee\Configuration\ArrayConfiguration\ArrayPickConfigurationCompiler'
        ];

        if ($customCompilers) {
            foreach ($customCompilers as $name => $className) {
                if ( ! class_exists($className)) {
                    throw new RuntimeException("Configuration compile class '{$className}' was not found'");
                }
                if ( ! is_a($className, 'Bumblebee\Configuration\ArrayConfiguration\TransformerConfigurationCompiler', true)) {
                    throw new RuntimeException("Class '{$className}' does not implement " . 'Bumblebee\Configuration\ArrayConfiguration\TransformerConfigurationCompiler');
                }

                $class = new \ReflectionClass($className);

                if ($class->getConstructor() && ($class->getConstructor()->getNumberOfRequiredParameters() > 0 || ! $class->getConstructor()->isPublic())) {
                    throw new RuntimeException("Class '{$className}' has private/protected constructor or its constructor has required arguments'");
                }
            }
            $configCompilers = $customCompilers + $configCompilers;
        }
        $cnt->getDefinition("bumblebee.configuration_compiler_factory")
            ->replaceArgument(0, $configCompilers);
    }
}
