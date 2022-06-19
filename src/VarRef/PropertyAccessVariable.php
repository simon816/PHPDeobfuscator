<?php

namespace PHPDeobfuscator\VarRef;

use PHPDeobfuscator\Scope;
use PHPDeobfuscator\ValRef;
use PHPDeobfuscator\VarRef;

class PropertyAccessVariable implements VarRef
{
    private $object;
    private $name;

    public function __construct(VarRef $object, $propName)
    {
        $this->object = $object;
        $this->name = $propName;
    }

    public function getValue(Scope $scope)
    {
        $objVal = $this->object->getValue($scope);
        if ($objVal !== null && !$objVal->isMutable()) {
            return $objVal->propertyFetch($this->name);
        }
        return null;
    }

    public function assignValue(Scope $scope, ValRef $valRef)
    {
        $objVal = $this->object->getValue($scope);
        if ($objVal !== null) {
            $objVal->propertyAssign($this->name, $valRef);
            return true;
        }
        return false;
    }

    public function unsetVar(Scope $scope)
    {
        $objVal = $this->object->getValue($scope);
        if ($objVal !== null) {
            $objVal->propertyUnset($this->name);
        }
    }

    public function __toString()
    {
        return "{$this->object}->{{$this->name}}";
    }
}
