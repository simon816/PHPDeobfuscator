<?php
namespace Exceptions;

use ValRef;

class MutableValueException extends BadValueException
{
    public function __construct(ValRef $val)
    {
        parent::__construct("Value could be mutable: $val");
    }
}
