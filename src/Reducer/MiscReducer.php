<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node;
use PHPDeobfuscator\Utils;
use PHPDeobfuscator\Exceptions;

class MiscReducer extends AbstractReducer
{
    public function reduceEncapsedString(Node\Scalar\Encapsed $node)
    {
        $newString = '';
        foreach ($node->parts as $part) {
            if ($part instanceof Node\Scalar\EncapsedStringPart) {
                $newString .= $part->value;
            } else {
                try {
                    $newString .= Utils::getValue($part);
                } catch (\InvalidArgumentException $e) {
                    return null;
                }
            }
        }
        return Utils::scalarToNode($newString);
    }

    public function reduceTernary(Node\Expr\Ternary $node)
    {
        return Utils::scalarToNode(Utils::getValue($node->cond) ? Utils::getValue($node->if) : Utils::getValue($node->else));
    }

    public function reduceEcho(Node\Stmt\Echo_ $node)
    {
        $exprs = array();
        foreach ($node->exprs as $expr) {
            try {
                $exprs[] = Utils::scalarToNode(Utils::getValue($expr));
            } catch (Exceptions\UnknownValueException $e) {
                $exprs[] = $expr;
            }
        }
        return new Node\Stmt\Echo_($exprs);
    }

    public function reducePrint(Node\Expr\Print_ $node)
    {
        return new Node\Expr\Print_(Utils::scalarToNode(Utils::getValue($node->expr)));
    }

    public function reduceReturn(Node\Stmt\Return_ $node)
    {
        if ($node->expr === null) {
            return;
        }
        return new Node\Stmt\Return_(Utils::scalarToNode(Utils::getValue($node->expr)));
    }
}
