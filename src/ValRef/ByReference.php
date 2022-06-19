<?php

namespace PHPDeobfuscator\ValRef;

use PHPDeobfuscator\Exceptions;
use PHPDeobfuscator\Scope;
use PHPDeobfuscator\ValRef;
use PHPDeobfuscator\VarRef;

class ByReference implements ValRef
{
    private $variable;
    private $scope;

    public function __construct(VarRef $varRef, Scope $scope)
    {
        $this->variable = $varRef;
        $this->scope = $scope;
    }

    public function isMutable()
    {
        return $this->getVal()->isMutable();
    }

    public function setMutable($mutable)
    {
        try {
            $this->getVal()->setMutable($mutable);
        } catch (Exceptions\UnknownValueException $e) {
            // Don't care
        }
    }

    public function getValue()
    {
        return $this->getVal()->getValue();
    }

    public function arrayFetch($dim)
    {
        return $this->getVal()->arrayFetch($dim);
    }

    public function arrayAssign($dim, ValRef $valRef)
    {
        $this->getVal()->arrayAssign($dim, $valRef);
    }

    public function arrayUnset($dim)
    {
        $this->getVal()->arrayUnset($dim);
    }

    public function propertyFetch($name)
    {
        return $this->getVal()->propertyFetch($name);
    }

    public function propertyAssign($name, ValRef $valRef)
    {
        $this->getVal()->propertyAssign($name, $valRef);
    }

    public function propertyUnset($name)
    {
        $this->getVal()->propertyUnset($name);
    }

    public function __toString()
    {
        return "ByRef{{$this->variable} in scope {$this->scope}}";
    }

    public function getVariable()
    {
        return $this->variable;
    }

    private function getVal()
    {
        $val = $this->variable->getValue($this->scope);
        if ($val === null) {
            throw new Exceptions\UnknownValueException("Cannot get value of reference");
        }
        return $val;
    }

}
