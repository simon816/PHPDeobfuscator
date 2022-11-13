<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Cast;
use PHPDeobfuscator\MaybeStmtArray;
use PHPDeobfuscator\Resolver;
use PHPDeobfuscator\Utils;

class UnaryReducer extends AbstractReducer
{

    private $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function reduceUnaryMinus(Expr\UnaryMinus $node)
    {
        return Utils::scalarToNode(-Utils::getValue($node->expr));
    }

    public function reduceBoolCast(Cast\Bool_ $node)
    {
        $val = Utils::getValue($node->expr);
        return Utils::scalarToNode((bool) $val);
    }

    public function reduceDoubleCast(Cast\Double $node)
    {
        $val = Utils::getValue($node->expr);
        return Utils::scalarToNode((double) $val);
    }

    public function reduceIntCast(Cast\Int_ $node)
    {
        $val = Utils::getValue($node->expr);
        return Utils::scalarToNode((int) $val);
    }

    public function reduceStringCast(Cast\String_ $node)
    {
        $val = Utils::getValue($node->expr);
        return Utils::scalarToNode((string) $val);
    }


    public function reducePostInc(Expr\PostInc $node)
    {
        return $this->postIncDec($node, true);
    }

    public function reducePostDec(Expr\PostDec $node)
    {
        return $this->postIncDec($node, false);
    }

    public function reducePreInc(Expr\PreInc $node)
    {
        return $this->preIncDec($node, true);
    }

    public function reducePreDec(Expr\PreDec $node)
    {
        return $this->preIncDec($node, false);
    }

    private function postIncDec(Expr $node, $isInc)
    {
        // Perform the operation and create old and new nodes
        $val = Utils::getValue($node->var);
        $oldValNode = Utils::scalarToNode($val);
        $isInc ? $val++ : $val--;
        $newValNode = Utils::scalarToNode($val);

        // Internally set the new value on the variable
        $var = $this->resolver->resolveVariable($node->var);
        $newValRef = Utils::getValueRef($newValNode);
        $var->assignValue($this->resolver->getCurrentScope(), $newValRef);

        $varNode = $node->var;
        if ($varNode instanceof Expr\PropertyFetch) {
            $varNode = $varNode->var;
        } elseif ($varNode instanceof Expr\ArrayDimFetch) {
            $varNode = $varNode->var;
        }
        // If the return value is ignored, attempt to return the final assignment
        // Fall back to an immediately invoked closure that implements the correct
        // semantics.
        $stmts = [
            new Stmt\Expression(new Expr\Assign($node->var, $newValNode)),
        ];
        $expr = new Expr\FuncCall(new Expr\Closure([
            'uses' => [new Expr\ClosureUse($varNode, true)],
            'stmts' => [
                new Stmt\Expression(new Expr\Assign($node->var, $newValNode)),
                new Stmt\Return_($oldValNode),
            ],
        ]));
        return new MaybeStmtArray($stmts, $expr);
    }

    private function preIncDec(Expr $node, $isInc)
    {
        $val = Utils::getValue($node->var);
        $isInc ? ++$val : --$val;
        $var = $this->resolver->resolveVariable($node->var);
        $valNode = Utils::scalarToNode($val);
        $valRef = Utils::getvalueRef($valNode);
        $var->assignValue($this->resolver->getCurrentScope(), $valRef);
        return new Expr\Assign($node->var, $valNode);
    }

}
