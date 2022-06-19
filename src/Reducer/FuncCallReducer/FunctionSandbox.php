<?php

namespace PHPDeobfuscator\Reducer\FuncCallReducer;

use PhpParser\Node\Expr\FuncCall;
use PHPDeobfuscator\Utils;

class FunctionSandbox implements FunctionReducer
{

    public function getSupportedNames()
    {
        return array_map(function ($name) {
            return substr($name, 9); // strlen('_sandbox_') === 9
        }, array_filter(get_class_methods($this), function ($name) {
            return strpos($name, '_sandbox_') === 0;
        }));
    }

    public function execute($name, array $args, FuncCall $node)
    {
        return Utils::scalarToNode(call_user_func_array(array($this, "_sandbox_{$name}"), Utils::refsToValues($args)));
    }

}
