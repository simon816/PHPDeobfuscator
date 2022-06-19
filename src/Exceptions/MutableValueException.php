<?php

namespace PHPDeobfuscator\Exceptions;

use PHPDeobfuscator\ValRef;

class MutableValueException extends BadValueException
{
    public function __construct(ValRef $val)
    {
        parent::__construct("Value could be mutable: $val");
    }
}
