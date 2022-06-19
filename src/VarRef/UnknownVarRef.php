<?php

namespace PHPDeobfuscator\VarRef;

use PHPDeobfuscator\Scope;
use PHPDeobfuscator\ValRef;
use PHPDeobfuscator\VarRef;

class UnknownVarRef implements VarRef
{
    public static $ANY;
    public static $NOT_A_VAR_REF;

    private $context;
    private $notAVarRef;

    public function __construct(VarRef $parentContext = null, $notAVarRef = false)
    {
        $this->context = $parentContext;
        $this->notAVarRef = $notAVarRef;
    }

    public function notAVarRef()
    {
        return $this->notAVarRef;
    }

    public function getValue(Scope $scope)
    {
        return null;
    }

    public function assignValue(Scope $scope, ValRef $valRef)
    {
        return false;
    }

    public function unsetVar(Scope $scope)
    {
    }

    public function __toString()
    {
        return "Unknown{{$this->context}}";
    }

    public function getContext()
    {
        return $this->context;
    }
}
UnknownVarRef::$ANY = new UnknownVarRef(null);
UnknownVarRef::$NOT_A_VAR_REF = new UnknownVarRef(null, true);
