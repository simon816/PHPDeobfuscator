<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node;
use PHPDeobfuscator\ReducerVisitor;
use PHPDeobfuscator\Reducer;

abstract class AbstractReducer implements Reducer
{
    private $methodMapping;

    public function getNodeClasses()
    {
        if ($this->methodMapping === null) {
            $this->methodMapping = array();
            $class = new \ReflectionClass($this);
            foreach ($class->getMethods() as $method) {
                if (strncmp($method->name, 'reduce', 6) === 0 && $method->class != self::class) {
                    if ($method->getNumberOfParameters() !== 1) {
                        throw new \LogicException("Number of parameters is not 1");
                    }
                    $param = $method->getParameters()[0];
                    $type = $param->getClass();
                    if (!$type->implementsInterface(Node::class)) {
                        throw new \LogicException("Parameter must be instance of Node");
                    }
                    if (isset($this->methodMapping[$type->name])) {
                        throw new \LogicException("Parameter type already mapped. {$type->name} to {$this->methodMapping[$type->name]}, attempted: {$method->name}");
                    }
                    $this->methodMapping[$type->name] = $method->name;
                }
            }
        }
        return array_keys($this->methodMapping);
    }

    public function reduce(Node $node)
    {
        return $this->{$this->methodMapping[get_class($node)]}($node);
    }

}
