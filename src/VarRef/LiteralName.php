<?php

namespace PHPDeobfuscator\VarRef;

use PHPDeobfuscator\Scope;
use PHPDeobfuscator\ValRef;
use PHPDeobfuscator\VarRef;

class LiteralName implements VarRef
{
    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getValue(Scope $scope)
    {
        return $scope->getVariable($this->name);
    }

    public function assignValue(Scope $scope, ValRef $valRef)
    {
        $scope->setVariable($this->name, $valRef);
        return true;
    }

    public function unsetVar(Scope $scope)
    {
        $scope->unsetVariable($this->name);
    }

    public function __toString()
    {
        return "Var{{$this->name}}";
    }

}
