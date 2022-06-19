<?php

namespace PHPDeobfuscator\Reducer\FuncCallReducer;

use PhpParser\Node\Expr\FuncCall;

interface FunctionReducer
{
    public function getSupportedNames();

    public function execute($name, array $args, FuncCall $node);
}
