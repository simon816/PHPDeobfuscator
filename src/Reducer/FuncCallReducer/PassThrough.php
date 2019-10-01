<?php
namespace Reducer\FuncCallReducer;

use PhpParser\Node\Expr\FuncCall;
use Utils;

class PassThrough implements FunctionReducer
{
    public function getSupportedNames()
    {
        return array(
            'base_convert',
            'base64_decode',
            'chr',
            'ceil',
            'dirname',
            'explode',
            'gmmktime',
            'gzinflate',
            'gzuncompress',
            'htmlspecialchars_decode',
            'implode',
            'intval',
            'ord',
            'rawurldecode',
            'sha1',
            'str_replace',
            'str_rot13',
            'strcmp',
            'strrev',
            'strtoupper',
            'strtr',
            'substr',
            'urldecode',
        );
    }

    public function execute($name, array $args, FuncCall $node)
    {
        return Utils::scalarToNode(call_user_func_array($name, Utils::refsToValues($args)));
    }
}
