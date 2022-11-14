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

    public function enterNode(Node $node)
    {
        // For MaybeStmtArray, we tag the inner expression node as being attached to a Stmt\Expression
        if ($node instanceof Node\Stmt\Expression) {
            $node->expr->setAttribute(AttrName::IN_EXPR_STMT, true);
        }
    }

    public function leaveNode(Node $node)
    {
        // If Stmt\Expression was forwarded a MaybeStmtArray, now is the time to action it
        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof MaybeStmtArray) {
            return $node->expr->stmts;
        }
        try {
            $newNode = $this->reduceNode($node);
            // Reducer wants to return a statement array, we forward this request if we'e inside a Stmt\Expression
            // Otherwise, use the fallback expression
            if ($newNode instanceof MaybeStmtArray) {
                if ($node->getAttribute(AttrName::IN_EXPR_STMT) === true) {
                    return $newNode;
                }
                return $newNode->expr;
            }
            return $newNode;
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
