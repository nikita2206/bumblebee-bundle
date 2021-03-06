<?php

namespace Bumblebee\Bundle;

use Bumblebee\Exception\ConfigurationCompilationException;
use Bumblebee\Bundle\Exception\RuntimeException;
use Bumblebee\Exception\InvalidTypeException;
use Bumblebee\Metadata\TypeMetadata;
use Bumblebee\TypeProvider as TypeProviderInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class ConfigurationTypeProvider implements TypeProviderInterface
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

        while ( ! $files->isEmpty()) {
            $file = realpath($files->dequeue());

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
        $compiled = [];
        $compiler = $this->configCompilerFactory->buildArrayCompiler();

        foreach ($resources as $fileName => $resource) {
            if (isset($resource["types"])) {
                try {
                    $resourceCompiled = $compiler->compile($resource["types"]);
                } catch (ConfigurationCompilationException $e) {
                    throw new RuntimeException("Error occurred during compilation of {$fileName}: " . $e->getMessage(), 0, $e);
                }

                if ($redefinedTypes = array_intersect_key($resourceCompiled, $compiled)) {
                    foreach ($redefinedTypes as $typeName => $typeDefinition) {
                        if ($compiled[$typeName] == $typeDefinition) {
                            continue;
                        }

                        throw new RuntimeException("Type \"{$typeName}\" is redefined in {$fileName}" .
                            " previously defined in {$this->typeToFilename[$typeName]}");
                    }
                }

                $this->typeToFilename += array_fill_keys(array_keys($resourceCompiled), $fileName);
                $compiled += $resourceCompiled;
            }
        }

        return $compiled;
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
