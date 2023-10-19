<?php

namespace PHPDeobfuscator\Reducer\FuncCallReducer;

use PhpParser\Node\Expr\FuncCall;
use League\Flysystem\Filesystem;
use League\Flysystem\FileNotFoundException;

use PHPDeobfuscator\AttrName;
use PHPDeobfuscator\Exceptions;
use PHPDeobfuscator\Utils;
use PHPDeobfuscator\ValRef\ResourceValue;

class FileSystemCall implements FunctionReducer
{
    private $fileSystem;

    public function __construct(FileSystem $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    public function getSupportedNames()
    {
        return array(
            'file_get_contents',
            'file',
            'fopen',
            'fread',
            'fwrite',
            'fclose',
        );
    }

    public function execute($name, array $args, FuncCall $node)
    {
        if (method_exists($this, $name . 'Prepare')) {
            $args = call_user_func(array($this, $name . 'Prepare'), $args, $node);
        } else {
            $args = Utils::refsToValues($args);
        }
        return call_user_func_array(array($this, $name), $args);
    }

    private function file_get_contents($filename, $flags = 0, $context = null, $offset = -1, $maxlen = -1)
    {
        if (Utils::safeFileExists($this->fileSystem, $filename)) {
            return Utils::scalarToNode($this->fileSystem->read($filename));
        }
        return null;
    }

    private function file($filename, $flags = 0, $context = null)
    {
        if (Utils::safeFileExists($this->fileSystem, $filename)) {
            $content = $this->fileSystem->read($filename);
            $lines = preg_split("/(\r\n|\r|\n)/", $content);
            return Utils::scalarToNode($lines);
        }
        return null;
    }

    private function fopenPrepare(array $args, FuncCall $node)
    {
        return array_merge(array($node), Utils::refsToValues($args));
    }

    private function fopen(FuncCall $node, $filename, $mode, $use_include_path = false, $context = null)
    {
        if (strpos($mode, 'r') !== false) {
            try {
                $stream = $this->fileSystem->readStream($filename);
            } catch (FileNotFoundException $e) {
                return;
            }
        } elseif (strpos($mode, 'w') !== false) {
            $stream = fopen('php://memory', 'w+b');
            $this->fileSystem->writeStream($filename, $stream);
        } else {
            return;
        }
        $node->setAttribute(AttrName::VALUE, new ResourceValue($filename, $stream));
    }

    private function firstArgIsResource(array $args)
    {
        $newArgs = array();
        foreach ($args as $i => $arg) {
            if ($i == 0) {
                if (!($arg instanceof ResourceValue)) {
                    throw new Exceptions\BadValueException("file handle is not a resource");
                }
                if ($arg->isClosed()) {
                    throw new Exceptions\BadValueException("file handle is closed");
                }
                $newArgs[] = $arg;
            } else {
                $newArgs[] = $arg->getValue();
            }
        }
        return $newArgs;
    }

    private function freadPrepare(array $args, FuncCall $node)
    {
        return $this->firstArgIsResource($args);
    }

    private function fread(ResourceValue $handle, $length)
    {
        return Utils::scalarToNode(fread($handle->getResource(), $length));
    }

    private function fwritePrepare(array $args, FuncCall $node)
    {
        return $this->firstArgIsResource($args);
    }

    private function fwrite(ResourceValue $handle, $string, $length = null)
    {
        if ($length !== null) {
            fwrite($handle->getResource(), $string, $length);
        } else {
            fwrite($handle->getResource(), $string);
        }
        $this->fileSystem->writeStream($handle->getFilename(), $handle->getResource());
    }

    private function fclosePrepare(array $args, FuncCall $node)
    {
        return $this->firstArgIsResource($args);
    }

    private function fclose(ResourceValue $handle)
    {
        fclose($handle->getResource());
        $handle->close();
    }
}
