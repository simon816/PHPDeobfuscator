<?php

namespace PHPDeobfuscator;

use PhpParser\Node\Expr;

/**
 *  A fake node to represent an array of statements (from an eval) as an expression.
 */
class EvalBlock extends Expr
{
    public $stmts;

    public function __construct(array $stmts, array $origStmts = null, array $attributes = array())
    {
        parent::__construct($attributes);
        $this->stmts = $stmts;
        $this->origStmts = $origStmts;
    }

    public function getSubNodeNames() : array
    {
        return array('stmts');
    }

    public function getType() : string
    {
        return 'Expr_EvalBlock';
    }
}
