<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node;

use PHPDeobfuscator\FunctionSandbox;
use PHPDeobfuscator\Utils;
use PHPDeobfuscator\ValRef\ScalarValue;

class FuncCallReducer extends AbstractReducer
{
    private $funcCallMap = array();

    public function addReducer(FuncCallReducer\FunctionReducer $reducer)
    {
        foreach ($reducer->getSupportedNames() as $funcName) {
            if (isset($this->funcCallMap[$funcName])) {
                throw new \RuntimeException("Tried adding {$funcName} from reducer " . get_class($reducer)
                    . "but was already added from " . get_class($this->funcCallMap[$funcName]));
            }
            $this->funcCallMap[$funcName] = $reducer;
        }
    }

    public function reduceFunctionCall(Node\Expr\FuncCall $node)
    {
        if ($node->name instanceof Node\Name) {
            $name = $node->name->toString();
        } else {
            $name = Utils::getValue($node->name);
            $nameNode = new Node\Name($name);
            // Special case for MetadataVisitor
            $nameNode->setAttribute('replaces', $node->name);
            $node->name = $nameNode;
        }
        // Normalise to lowercase - function names are case insensitive
        return $this->makeFunctionCall(strtolower($name), $node);
    }

    private function makeFunctionCall($name, $node)
    {
        if(!isset($this->funcCallMap[$name])) {
            return;
        }
        $args = array();
        foreach ($node->args as $arg) {
            $valRef = Utils::getValueRef($arg->value);
            if ($arg->byRef) {
                return; // "Call-time pass-by-reference has been removed"
            }
            $args[] = $valRef;
        }
        return $this->funcCallMap[$name]->execute($name, $args, $node);
    }

}
