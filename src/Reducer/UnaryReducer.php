<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node;
use PhpParser\Node\Expr\Cast;
use PHPDeobfuscator\Utils;

class UnaryReducer extends AbstractReducer
{

    public function reduceUnaryMinus(Node\Expr\UnaryMinus $node)
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

}
