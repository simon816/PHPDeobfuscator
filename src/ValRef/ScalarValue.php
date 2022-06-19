<?php

namespace PHPDeobfuscator\ValRef;

use PHPDeobfuscator\ValRef;

class ScalarValue extends AbstractValRef
{
    private $value;

    public function __construct($value)
    {
        if (!(is_scalar($value) || is_null($value))) {
            throw new \InvalidArgumentException("Value not scalar!");
        }
        $this->value = $value;
    }

    public function __toString()
    {
        return "Val{{$this->value}}";
    }

    protected function getValueImpl()
    {
        return $this->value;
    }

    public function arrayFetch($dim)
    {
        $val = $this->getValue();
        if (isset($val[$dim])) {
            return new ScalarValue($val[$dim]);
        }
        return new ScalarValue(null);
    }

    public function arrayAssign($dim, ValRef $valRef)
    {
        if ($dim === null) {
            $this->value[] = $valRef->getValue();
        } else {
            $this->value[$dim] = $valRef->getValue();
        }
    }
}
