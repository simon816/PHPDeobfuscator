<?php

use PhpParser\PrettyPrinter\Standard;

class ExtendedPrettyPrinter extends Standard
{
    protected function pExpr_EvalBlock(EvalBlock $block)
    {
        return 'eval /* PHPDeobfuscator eval output */ {' . $this->pStmts($block->stmts) . "\n}";
    }
}
