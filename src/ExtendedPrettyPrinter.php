<?php

namespace PHPDeobfuscator;

use PhpParser\PrettyPrinter\Standard;

class ExtendedPrettyPrinter extends Standard
{
    protected function pExpr_EvalBlock(EvalBlock $block)
    {
        return 'eval /* PHPDeobfuscator eval output */ {' . $this->pStmts($block->stmts) . $this->nl . "}";
    }

    // Escape all non-printable characters
    // The parent printer already handles the 00-1F range
    protected function escapeString($string, $quote) {
        return preg_replace_callback('/([\x7F\x80-\xFF])/', function ($matches) {
            return '\\x' . bin2hex($matches[1]);
        }, parent::escapeString($string, $quote));
    }


}
