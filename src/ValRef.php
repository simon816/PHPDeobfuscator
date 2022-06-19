<?php

namespace PHPDeobfuscator;

interface ValRef
{
    public function isMutable();

    public function setMutable($mutable);

    public function getValue();

    public function arrayFetch($dim);

    public function arrayAssign($dim, ValRef $valRef);

    public function arrayUnset($dim);

    public function propertyFetch($name);

    public function propertyAssign($name, ValRef $valRef);

    public function propertyUnset($name);

    public function __toString();
}
