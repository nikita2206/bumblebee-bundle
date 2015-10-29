<?php

namespace Bumblebee\Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TaggedTransformersPass implements CompilerPassInterface
{
    /**
     * @inheritdoc
     */
    public function process(ContainerBuilder $container)
    {
        $transformers = [];

        foreach ($container->findTaggedServiceIds("bumblebee.transformer") as $id => $tags) {
            foreach ($tags as $tag) {
                $transformers[$tag["transformer"]] = new Reference($id);
            }
        }

        $container->getDefinition("bumblebee.transformers")->addArgument($transformers);
    }
}
