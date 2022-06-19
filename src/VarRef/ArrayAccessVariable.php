<?php

namespace PHPDeobfuscator\VarRef;

use PHPDeobfuscator\Scope;
use PHPDeobfuscator\ValRef;
use PHPDeobfuscator\VarRef;

class ArrayAccessVariable implements VarRef
{
    private $arr;
    private $dim;

    public function __construct(VarRef $array, $dim)
    {
        $this->arr = $array;
        $this->dim = $dim;
    }

    public function getValue(Scope $scope)
    {
        $arrVal = $this->arr->getValue($scope);
        if ($arrVal !== null && !$arrVal->isMutable()) {
            return $arrVal->arrayFetch($this->dim);
        }
        return null;
    }

    public function assignValue(Scope $scope, ValRef $valRef)
    {
        $arrVal = $this->arr->getValue($scope);
        if ($arrVal !== null) {
            $arrVal->arrayAssign($this->dim, $valRef);
            return true;
        }
        return false;
    }

    public function unsetVar(Scope $scope)
    {
        $arrVal = $this->arr->getValue($scope);
        if ($arrVal !== null) {
            $arrVal->arrayUnset($this->dim);
        }
    }

    public function __toString()
    {
        return "{$this->arr}[{$this->dim}]";
    }
}
