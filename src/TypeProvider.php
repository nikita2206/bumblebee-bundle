<?php

namespace Bumblebee\Bundle;

use Bumblebee\Bundle\Exception\RuntimeException;
use Bumblebee\Exception\InvalidTypeException;
use Bumblebee\Metadata\TypeMetadata;
use Bumblebee\TypeProvider as TypeProviderInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class TypeProvider implements TypeProviderInterface
{
    /**
     * @var string
     */
    protected $resource;

    /**
     * @var ConfigurationCompilerFactory
     */
    protected $configCompilerFactory;

    /**
     * @var TypeMetadata[]
     */
    protected $metadata;

    /**
     * @var string[] Map<TypeName, Filename>
     */
    protected $typeToFilename;

    /**
     * @param string $resource A filename with configuration
     * @param ConfigurationCompilerFactory $configCompilerFactory
     */
    public function __construct($resource, ConfigurationCompilerFactory $configCompilerFactory)
    {
        $this->resource = $resource;
        $this->configCompilerFactory = $configCompilerFactory;
        $this->typeToFilename = [];
    }

    public function getFilename($type)
    {
        if ($this->metadata === null) {
            $this->load();
        }

        if ( ! isset($this->typeToFilename[$type])) {
            throw new InvalidTypeException($type);
        }

        return $this->typeToFilename[$type];
    }

    /**
     * @inheritdoc
     */
    public function get($type)
    {
        if ($this->metadata === null) {
            $this->load();
        }

        if ( ! isset($this->metadata[$type])) {
            throw new InvalidTypeException($type);
        }

        return $this->metadata[$type];
    }

    /**
     * @inheritdoc
     */
    public function all()
    {
        if ($this->metadata === null) {
            $this->load();
        }

        return $this->metadata;
    }

    protected function load()
    {
        $parser = new Parser();
        $files = new \SplQueue();
        $files->enqueue($this->resource);
        $loaded = [];

        while ($file = $files->dequeue()) {
            $file = realpath($file);

            if (isset($loaded[$file])) {
                throw new RuntimeException("File \"{$file}\" was requested to be loaded twice (it might be circular reference)");
            }

            $config = $this->loadResource($parser, $file);
            $loaded[$file] = $config;

            if (isset($config["imports"])) {
                foreach ($config["imports"] as $res) {
                    if ($res[0] !== "/") {
                        $res = dirname($file) . "/" . $res;
                    }

                    $files->enqueue($res);
                }
            }
        }

        $this->metadata = $this->processLoadedResources($loaded);
    }

    protected function processLoadedResources(array $resources)
    {
        $types = [];

        foreach ($resources as $fileName => $resource) {
            if (isset($resource["types"])) {
                if ($intersected = array_intersect_key($types, $resource["types"])) {
                    $intersected = key($intersected);
                    throw new RuntimeException("Type \"{$intersected}\" is redefined in {$fileName}");
                }

                $types += $resource["types"];
                $this->typeToFilename += array_fill_keys(array_keys($resource["types"]), $fileName);
            }
        }

        $compiler = $this->configCompilerFactory->buildArrayCompiler();

        return $compiler->compile($types);
    }

    protected function loadResource(Parser $parser, $res)
    {
        if (!is_file($res) || !is_readable($res) || !stream_is_local($res)) {
            throw new RuntimeException("File \"{$res}\" doesn't exist on a local filesystem or is not readable");
        }

        try {
            $config = $parser->parse(file_get_contents($res));
        } catch (ParseException $e) {
            throw new RuntimeException("File \"{$res}\" doesn't contain valid YAML", 0, $e);
        }

        return $config;
    }
}
