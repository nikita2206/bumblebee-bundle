<?php

namespace Bumblebee\Bundle;

use Bumblebee\Metadata\TypeMetadata;
use Doctrine\Common\Cache\Cache;
use Bumblebee\TypeProvider as TypeProviderInterface;

class CachingTypeProvider implements TypeProviderInterface
{
    /**
     * @var TypeProvider
     */
    protected $typeProvider;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var TypeMetadata[]
     */
    protected $loaded;

    public function __construct(TypeProvider $typeProvider, Cache $cache, $debug)
    {
        $this->typeProvider = $typeProvider;
        $this->cache = $cache;
        $this->debug = $debug;
        $this->loaded = [];
    }

    /**
     * @inheritdoc
     */
    public function get($type)
    {
        if (isset($this->loaded[$type])) {
            return $this->loaded[$type];
        }

        if (($metadata = $this->cache->fetch($type)) === false) {
            goto miss;
        }

        if ( ! $this->debug) {
            return $this->loaded[$type] = $metadata[0];
        }

        $time = $metadata[1];
        $typeFilename = $metadata[2];
        if ($time < filemtime($typeFilename)) {
            goto miss;
        }

        return $this->loaded[$type] = $metadata[0];

        miss:
        return $this->loaded[$type] = $this->reload($type);
    }

    /**
     * @inheritdoc
     */
    public function all()
    {
        return $this->typeProvider->all();
    }

    /**
     * Reloads all types and saves them to cache
     */
    public function warmUpCache()
    {
        foreach ($this->all() as $type => $_) {
            $this->reload($type);
        }
    }

    protected function reload($type)
    {
        $metadata = $this->typeProvider->get($type);
        $this->cache->save($type, [$metadata, time(), $this->typeProvider->getFilename($type)]);

        return $metadata;
    }
}
