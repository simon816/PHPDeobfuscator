<?php

use PhpParser\Node\Expr;

/**
 *  A fake node to represent an array of statements (from an eval) as an expression.
 */
class EvalBlock extends Expr
{
    public $stmts;

    public function __construct(array $stmts, array $attributes = array())
    {
        parent::__construct($attributes);
        $this->stmts = $stmts;
    }

    public function getSubNodeNames()
    {
        return array('stmts');
    }

    public function getType()
    {
        return 'Expr_EvalBlock';
    }
}
