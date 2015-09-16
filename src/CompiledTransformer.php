<?php

namespace Bumblebee\Bundle;

use Bumblebee\Compiler;
use Bumblebee\TransformerProvider;
use Bumblebee\TypeProvider;
use Bumblebee\TypeTransformer\CompilableTypeTransformer;
use Bumblebee\TransformerInterface;

class CompiledTransformer implements TransformerInterface
{
    /**
     * @var Compiler
     */
    protected $compiler;

    /**
     * @var TypeProvider
     */
    protected $types;

    /**
     * @var TransformerProvider
     */
    protected $transformers;

    /**
     * @var string
     */
    protected $baseDir;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var \Closure[]
     */
    protected $compiled;

    /**
     * @var array Set of non-compilable types
     */
    protected $nonCompilable;

    /**
     * @param Compiler $compiler
     * @param TypeProvider $types
     * @param TransformerProvider $transformers
     * @param string $baseDir
     * @param bool $debug
     */
    public function __construct(Compiler $compiler, TypeProvider $types, TransformerProvider $transformers,
        $baseDir, $debug)
    {
        $this->compiler = $compiler;
        $this->types = $types;
        $this->transformers = $transformers;
        $this->baseDir = $baseDir;
        $this->debug = $debug;
        $this->compiled = [];
        $this->nonCompilable = [];
    }

    /**
     * @inheritdoc
     */
    public function transform($input, $type)
    {
        if (isset($this->compiled[$type])) {
            $func = $this->compiled[$type];
            return $func($input, $this);
        }

        if (isset($this->nonCompilable[$type])) {
            $metadata = $this->types->get($type);
            $transformer = $this->transformers->get($metadata->getTransformer());

            return $transformer->transform($input, $metadata, $this);
        }

        return $this->transformCached($input, $type);
    }

    /**
     * Recompiles type and saves it in the cache
     *
     * @param string $type
     */
    public function recompileType($type)
    {
        $metadata = $this->types->get($type);
        $transformer = $this->transformers->get($metadata->getTransformer());

        if ($transformer instanceof CompilableTypeTransformer) {
            $fileName = $this->baseDir . "/" . md5($type) . ".php";
            $this->doCompile($fileName, $type);
        }
    }

    protected function transformCached($input, $type)
    {
        $fileName = $this->baseDir . "/" . md5($type) . ".php";
        $func = @include $fileName;

        if ($func instanceof \Closure) {
            if ($this->debug) {
                // invalidate cache if needed
            }

            $this->compiled[$type] = $func;
            return $func($input, $this);
        }

        $metadata = $this->types->get($type);
        $transformer = $this->transformers->get($metadata->getTransformer());
        if ( ! $transformer instanceof CompilableTypeTransformer) {
            $this->nonCompilable[$type] = true;
            return $transformer->transform($input, $metadata, $this);
        }

        $func = $this->doCompile($fileName, $type);
        return $func($input, $this);
    }

    /**
     * @param string $fileName
     * @param string $type
     * @return \Closure
     */
    protected function doCompile($fileName, $type)
    {
        $code = $this->compiler->compile($type);
        file_put_contents($fileName, "<?php return {$code};\n");

        $func = eval("return {$code};");
        $this->compiled[$type] = $func;

        return $func;
    }
}
