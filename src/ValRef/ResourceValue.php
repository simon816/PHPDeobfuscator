<?php
namespace ValRef;

use ValRef;

class ResourceValue extends AbstractValRef
{
    private $filename;
    private $resource;

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

    public function getResource()
    {
        return $this->resource;
    }
}
