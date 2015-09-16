<?php

namespace Bumblebee\Bundle;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer as AbstractCacheWarmer;

class CacheWarmer extends AbstractCacheWarmer
{
    /**
     * @var CompiledTransformer
     */
    protected $compiledTransformer;

    /**
     * @var CachingTypeProvider
     */
    protected $types;

    public function __construct(CompiledTransformer $compiledTransformer, CachingTypeProvider $types)
    {
        $this->compiledTransformer = $compiledTransformer;
        $this->types = $types;
    }

    /**
     * @inheritdoc
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function warmUp($cacheDir)
    {
        $this->types->warmUpCache();

        foreach ($this->types->all() as $type => $_) {
            $this->compiledTransformer->recompileType($type);
        }
    }
}
