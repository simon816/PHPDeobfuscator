<?php

namespace PHPDeobfuscator;

interface VarRef
{
    public function getValue(Scope $scope);

    public function assignValue(Scope $scope, ValRef $valRef);

    public function unsetVar(Scope $scope);

    public function __toString();
}
