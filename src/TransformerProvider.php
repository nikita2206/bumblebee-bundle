<?php

namespace Bumblebee\Bundle;

use Bumblebee\Bundle\Exception\RuntimeException;
use Bumblebee\TransformerProvider as TransformerProviderInterface;
use Bumblebee\TypeTransformer\TypeTransformer;

class TransformerProvider implements TransformerProviderInterface
{

    /**
     * @var string[]
     */
    protected $classMap;

    /**
     * @var TypeTransformer[]
     */
    protected $instances;

    /**
     * @param array $classMap Map of transformer names to class names of TypeTransformers
     * @param TypeTransformer[] $instances
     */
    public function __construct(array $classMap, array $instances = [])
    {
        $this->classMap = $classMap;
        $this->instances = $instances;
    }

    /**
     * @inheritdoc
     */
    public function get($transformer)
    {
        if (isset($this->instances[$transformer])) {
            return $this->instances[$transformer];
        }

        if ( ! isset($this->classMap[$transformer])) {
            throw new RuntimeException("There's no transformer '{$transformer}'");
        }

        $className = $this->classMap[$transformer];
        return $this->instances[$transformer] = new $className();
    }
}
