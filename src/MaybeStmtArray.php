<?php

namespace PHPDeobfuscator;

use PhpParser\Node\Expr;

/**
 * A dummy node which should never make it out to the final tree.
 *
 * This node represents when an expression should be opportunistically upgraded
 * to an array of statements when possible (i.e. in an array of Stmt\Expression nodes).
 *
 * If it cannot be upgraded, fall back to $expr, otherwise use $stmts.
 *
 * Logic is implemented in ReducerVisitor.
 *
 */
class MaybeStmtArray extends Expr
{
    public $stmts;
    public $expr;

    public function __construct(array $stmts, Expr $expr)
    {
        $this->stmts = $stmts;
        $this->expr = $expr;
    }

    public function getSubNodeNames() : array
    {
        throw new \LogicException("Not a real node");
    }

    public function getType() : string
    {
        throw new \LogicException("Not a real node");
    }
}
