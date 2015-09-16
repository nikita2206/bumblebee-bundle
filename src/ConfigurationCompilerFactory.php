<?php

namespace Bumblebee\Bundle;

use Bumblebee\Configuration\ArrayConfigurationCompiler;
use Bumblebee\Configuration\ArrayConfiguration as C;

class ConfigurationCompilerFactory
{
    /**
     * @var string[]
     */
    protected $arrayCompilers;

    /**
     * @param string[] $arrayCompilers Class-map from transformer names to class names
     */
    public function __construct(array $arrayCompilers)
    {
        $this->arrayCompilers = $arrayCompilers;
    }

    /**
     * @return ArrayConfigurationCompiler
     */
    public function buildArrayCompiler()
    {
        $compilers = array_map(function ($className) {
            return new $className();
        }, $this->arrayCompilers);

        return new ArrayConfigurationCompiler($compilers);
    }
}
