<?php

namespace Bumblebee\Bundle;

use Bumblebee\Bundle\DependencyInjection\TaggedTransformersPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BumblebeeBundle extends Bundle
{
    /**
     * @inheritdoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new TaggedTransformersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
