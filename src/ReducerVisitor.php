<?php

namespace PHPDeobfuscator;

use PhpParser\Node;

class ReducerVisitor extends \PhpParser\NodeVisitorAbstract
{
    private $reducerByClass = array();
    private $prettyPrinter;

    public function addReducer(Reducer $reducer)
    {
        foreach ($reducer->getNodeClasses() as $className) {
            if (isset($this->reducerByClass[$className])) {
                throw new \RuntimeException("Tried adding {$className} from reducer " . get_class($reducer)
                    . "but was already added from " . get_class($this->reducerByClass[$className]));
            }
            $this->reducerByClass[$className] = $reducer;
        }
    }

    public function leaveNode(Node $node)
    {
        try {
            return $this->reduceNode($node);
        } catch (Exceptions\BadValueException $e) {
        }
    }

    private function reduceNode(Node $node)
    {
        $className = get_class($node);
        if (isset($this->reducerByClass[$className])) {
            return $this->reducerByClass[$className]->reduce($node, $this);
        }
    }
}
