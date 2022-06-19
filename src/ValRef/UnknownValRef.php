<?php

namespace PHPDeobfuscator\ValRef;

use PHPDeobfuscator\ValRef;

class UnknownValRef extends AbstractValRef
{
    public static $INSTANCE;

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
        return "UNKNOWN";
    }

}

UnknownValRef::$INSTANCE = new UnknownValRef();
