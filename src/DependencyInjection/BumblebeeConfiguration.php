<?php

namespace Bumblebee\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class BumblebeeConfiguration implements ConfigurationInterface
{

    protected $isProd;

    public function __construct($isProd)
    {
        $this->isProd = $isProd;
    }

    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder();
        $root = $tb->root("bumblebee");

        $metadataCacheType = ["memcache", "memcached", "file", "redis"];

        $root
            ->children()
                ->scalarNode("resource")
                    ->info("File with transformations configuration")->isRequired()->cannotBeEmpty()
                ->end()
                ->arrayNode("metadata_cache")
                    ->info("You can either set type of cache to use or a caching_service")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode("type")->values($metadataCacheType)->defaultNull()->end()
                        ->scalarNode("caching_service")->defaultNull()->info("Name of Doctrine\\Common\\Cache\\Cache service")->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) { return $v["type"] && $v["caching_service"]; })
                        ->thenInvalid("You can't use both 'type' and 'caching_service' for metadata_cache")
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) use ($metadataCacheType) { return $v["type"] && !in_array($v["type"], $metadataCacheType); })
                        ->thenInvalid("Caching type you used is not supported. The list of supported types is: " . implode(", ", $metadataCacheType))
                    ->end()
                ->end()
                ->booleanNode("compile")
                    ->info("Enable for compilation of transformers into more optimal PHP code")
                    ->defaultValue($this->isProd)
                ->end()
                ->arrayNode("custom_transformers")->defaultValue([])
                    ->prototype("scalar")
                    ->end()
                ->end()
                ->arrayNode("custom_configuration_compilers")->defaultValue([])
                    ->prototype("scalar");

        return $tb;
    }
}
