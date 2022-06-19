<?php

namespace PHPDeobfuscator\ValRef;

use PHPDeobfuscator\Resolver;
use PHPDeobfuscator\ValRef;
use PHPDeobfuscator\ValRef\ArrayVal;

class GlobalVarArray extends ArrayVal
{
    private $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    protected function &backingArray()
    {
        return $this->resolver->getGlobalScope()->getVariables();
    }

    public function arrayFetch($dim)
    {
        $this->checkMutable();
        return $this->resolver->getGlobalScope()->getVariable($dim);
    }

    public function arrayAssign($dim, ValRef $valRef)
    {
        $this->resolver->getGlobalScope()->setVariable($dim, $valRef);
    }

    public function arrayUnset($dim)
    {
        $this->resolver->getGlobalScope()->unsetVariable($dim);
    }

}
