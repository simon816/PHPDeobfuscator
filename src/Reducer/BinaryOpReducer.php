<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\BinaryOp;
use PHPDeobfuscator\Utils;

class BinaryOpReducer extends AbstractReducer
{
    private function postProcess(BinaryOp $node, $result)
    {
        $attrs = array();
        if (is_string($result)) {
            $dbl = String_::KIND_DOUBLE_QUOTED;
            $lkind = $node->left instanceof String_ ? $node->left->getAttribute('kind', $dbl) : $dbl;
            $rkind = $node->right instanceof String_ ? $node->right->getAttribute('kind', $dbl) : $dbl;
            // Don't prefer single quotes
            if ($lkind === String_::KIND_SINGLE_QUOTED) {
                $kind = $rkind;
            } elseif ($rkind === String_::KIND_SINGLE_QUOTED) {
                $kind = $lkind;
            } else {
                $kind = $rkind;
            }
            $attrs['kind'] = $kind;
        }
        return Utils::scalarToNode($result, $attrs);
    }

    private function left(BinaryOp $node)
    {
        return Utils::getValue($node->left);
    }

    private function right(BinaryOp $node)
    {
        return Utils::getValue($node->right);
    }

    public function reduceBitwiseAnd(BinaryOp\BitwiseAnd $node)
    { return $this->postProcess($node, $this->left($node) & $this->right($node)); }

    public function reduceBitwiseOr(BinaryOp\BitwiseOr $node)
    { return $this->postProcess($node, $this->left($node) | $this->right($node)); }

    public function reduceBitwiseXor(BinaryOp\BitwiseXor $node)
    { return $this->postProcess($node, $this->left($node) ^ $this->right($node)); }

    public function reduceBooleanAnd(BinaryOp\BooleanAnd $node)
    { return $this->postProcess($node, $this->left($node) && $this->right($node)); }

    public function reduceBooleanOr(BinaryOp\BooleanOr $node)
    { return $this->postProcess($node, $this->left($node) || $this->right($node)); }

    public function reduceCoalesce(BinaryOp\Coalesce $node)
    { return $this->postProcess($node, $this->left($node) ?? $this->right($node)); }

    public function reduceConcat(BinaryOp\Concat $node)
    {
        $left = $this->left($node);
        $right = $this->right($node);
        if (is_array($left) || is_array($right)) {
            return null;
        }
        return $this->postProcess($node, $left . $right);
    }

    public function reduceDiv(BinaryOp\Div $node)
    {
        $left = $this->left($node);
        $right = $this->right($node);
        if ((float) $right == 0.0) {
            return null;
        }
        return $this->postProcess($node, $left / $right);
    }

    public function reduceEqual(BinaryOp\Equal $node)
    { return $this->postProcess($node, $this->left($node) == $this->right($node)); }

    public function reduceGreater(BinaryOp\Greater $node)
    { return $this->postProcess($node, $this->left($node) > $this->right($node)); }

    public function reduceGreaterOrEqual(BinaryOp\GreaterOrEqual $node)
    { return $this->postProcess($node, $this->left($node) >= $this->right($node)); }

    public function reduceIdentical(BinaryOp\Identical $node)
    { return $this->postProcess($node, $this->left($node) === $this->right($node)); }

    public function reduceLogicalAnd(BinaryOp\LogicalAnd $node)
    { return $this->postProcess($node, $this->left($node) and $this->right($node)); }

    public function reduceLogicalOr(BinaryOp\LogicalOr $node)
    { return $this->postProcess($node, $this->left($node) or $this->right($node)); }

    public function reduceLogicalXor(BinaryOp\LogicalXor $node)
    { return $this->postProcess($node, $this->left($node) xor $this->right($node)); }

    public function reduceMinus(BinaryOp\Minus $node)
    { return $this->postProcess($node, $this->left($node) - $this->right($node)); }

    public function reduceMod(BinaryOp\Mod $node)
    { return $this->postProcess($node, $this->left($node) % $this->right($node)); }

    public function reduceMul(BinaryOp\Mul $node)
    { return $this->postProcess($node, $this->left($node) * $this->right($node)); }

    public function reduceNotEqual(BinaryOp\NotEqual $node)
    { return $this->postProcess($node, $this->left($node) != $this->right($node)); }

    public function reduceNotIdentical(BinaryOp\NotIdentical $node)
    { return $this->postProcess($node, $this->left($node) !== $this->right($node)); }

    public function reducePlus(BinaryOp\Plus $node)
    { return $this->postProcess($node, $this->left($node) + $this->right($node)); }

    public function reducePow(BinaryOp\Pow $node)
    { return $this->postProcess($node, $this->left($node) ** $this->right($node)); }

    public function reduceShiftLeft(BinaryOp\ShiftLeft $node)
    { return $this->postProcess($node, $this->left($node) << $this->right($node)); }

    public function reduceShiftRight(BinaryOp\ShiftRight $node)
    { return $this->postProcess($node, $this->left($node) >> $this->right($node)); }

    public function reduceSmaller(BinaryOp\Smaller $node)
    { return $this->postProcess($node, $this->left($node) < $this->right($node)); }

    public function reduceSmallerOrEqual(BinaryOp\SmallerOrEqual $node)
    { return $this->postProcess($node, $this->left($node) <= $this->right($node)); }

    public function reduceSpaceship(BinaryOp\Spaceship $node)
    { return $this->postProcess($node, $this->left($node) <=> $this->right($node)); }

}
