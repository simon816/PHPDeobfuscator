<?php

namespace PHPDeobfuscator\ValRef;

use PHPDeobfuscator\ValRef;

class ResourceValue extends AbstractValRef
{
    private $filename;
    private $resource;
    private $isClosed = false;

    public function __construct($filename, $resource)
    {
        $this->filename = $filename;
        $this->resource = $resource;
    }

    public function isMutable()
    {
        return true;
    }

    protected function getValueImpl()
    {
        // Do nothing
    }

    public function __toString()
    {
        return "resource{{$this->filename}}";
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function close()
    {
        $this->isClosed = true;
    }

    public function isClosed()
    {
        return $this->isClosed;
    }

    public function getResource()
    {
        if ($this->isClosed) {
            throw new \LogicException("Tried to use closed resource: {$this->filename}");
        }
        return $this->resource;
    }
}
