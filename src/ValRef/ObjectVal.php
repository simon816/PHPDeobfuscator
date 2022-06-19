<?php

namespace PHPDeobfuscator\ValRef;

use PHPDeobfuscator\ValRef;

class ObjectVal extends AbstractValRef
{
    private $propArr = array();

    protected function getValueImpl()
    {
        $value = new \stdClass();
        foreach ($this->propArr as $name => $ref) {
            $value->$name = $ref->getValue();
        }
        return $value;
    }

    public function propertyFetch($name)
    {
        if (isset($this->propArr[$name])) {
            return $this->propArr[$name];
        }
        return null;
    }

    public function propertyAssign($name, ValRef $valRef)
    {
        $this->propArr[$name] = $valRef;
    }

    public function propertyUnset($name)
    {
        unset($this->propArr[$name]);
    }

    public function __toString()
    {
        $arr = $this->propArr;
        return 'Object(' . implode(', ', array_map(function ($key) use (&$arr) {
            return "$key => " . $arr[$key];
        }, array_keys($arr))) . ')';
    }

    public function __clone()
    {
        foreach ($this->propArr as $name => &$ref) {
            $ref = clone $ref;
        }
    }
}
