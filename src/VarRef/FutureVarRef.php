<?php

namespace PHPDeobfuscator\VarRef;

use PhpParser\Node\Expr;

use PHPDeobfuscator\Resolver;
use PHPDeobfuscator\Scope;
use PHPDeobfuscator\ValRef;
use PHPDeobfuscator\VarRef;
/**
 *  This is used for list assignment (ListVarRef) for variables that are not known
 *  until another variable in the list is known.
 *  e.g.
 *  list($$y, $y) = array(10, 'x');
 *
 *  $$y is not known until $y is known, but we know it must be 'x' i.e. $x = 10
 *  So FutureVarRef defers resolving $$y until a later date
 */
class FutureVarRef implements VarRef
{
    private $expr;
    private $resolver;

    public function __construct(Expr $expr, Resolver $resolver)
    {
        $this->expr = $expr;
        $this->resolver = $resolver;
    }

    public function getValue(Scope $scope)
    {
        return $this->tryResolve()->getValue($scope);
    }

    public function assignValue(Scope $scope, ValRef $valRef)
    {
        return $this->tryResolve()->assignValue($scope, $valRef);
    }

    public function unsetVar(Scope $scope)
    {
        $this->tryResolve()->unsetVar($scope);
    }

    public function __toString()
    {
        return $this->tryResolve()->__toString();
    }

    private function tryResolve()
    {
        return $this->resolver->resolveVariable($this->expr, true);
    }

}
