<?php

namespace PHPDeobfuscator\ValRef;

use PHPDeobfuscator\Exceptions;
use PHPDeobfuscator\ValRef;

abstract class AbstractValRef implements ValRef
{
    private $isMutable = false;

    public function setMutable($mutable)
    {
        $this->isMutable = $mutable;
    }

    public function isMutable()
    {
        return $this->isMutable;
    }

    protected function checkMutable()
    {
        if ($this->isMutable()) {
            throw new Exceptions\MutableValueException($this);
        }
    }

    public function getValue()
    {
        $this->checkMutable();
        return $this->getValueImpl();
    }

    protected abstract function getValueImpl();

    public function arrayFetch($dim)
    {
        return null;
    }

    public function arrayAssign($dim, ValRef $valRef)
    {
    }

    public function arrayUnset($dim)
    {
    }

    public function propertyFetch($name)
    {
        return null;
    }

    public function propertyAssign($name, ValRef $valRef)
    {
    }

    public function propertyUnset($name)
    {
    }
}
