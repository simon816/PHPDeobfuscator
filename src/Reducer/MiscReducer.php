<?php
namespace Reducer;

use PhpParser\Node;
use Utils;

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
}
