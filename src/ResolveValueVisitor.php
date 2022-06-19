<?php

namespace PHPDeobfuscator;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

use PHPDeobfuscator\ValRef\ArrayVal;
use PHPDeobfuscator\ValRef\ObjectVal;
use PHPDeobfuscator\ValRef\ScalarValue;
use PHPDeobfuscator\ValRef\UnknownValRef;

class ResolveValueVisitor extends \PhpParser\NodeVisitorAbstract
{
    private $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    private function getConstant($name)
    {
        $lower = strtolower($name);
        if ($lower === 'null') {
            return new ScalarValue(null);
        }
        if ($lower === 'true') {
            return new ScalarValue(true);
        }
        if ($lower === 'false') {
            return new ScalarValue(false);
        }
        return $this->resolver->getConstant($name);
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Expr) {
            try {
                $this->eagerSetValue($node);
            } catch (Exceptions\BadValueException $e) {
            }
        }
    }

    private function eagerSetValue(Expr $expr)
    {
        if ($expr->hasAttribute(AttrName::VALUE)) {
            return;
        }
        $value = null;
        if ($expr instanceof Expr\ConstFetch) {
            $name = $expr->name->toString();
            $value = $this->getConstant($name);
        } elseif ($expr instanceof Expr\Array_) {
            $valArray = array();
            foreach ($expr->items as $item) {
                try {
                    $valRef = Utils::getValueRef($item->value);
                } catch (Exceptions\UnknownValueException $e) {
                    // Allow for partially known arrays (don't bomb out if
                    // there's an unknown, instead just mark as unknown)
                    $valRef = UnknownValRef::$INSTANCE;
                }
                if ($item->key === null) {
                    $valArray[] = $valRef;
                } else {
                    $valArray[Utils::getValue($item->key)] = $valRef;
                }
            }
            $value = new ArrayVal($valArray);
        } elseif ($expr instanceof Scalar\String_) {
            $value = new ScalarValue($expr->value);
        } elseif ($expr instanceof Scalar\DNumber) {
            $value = new ScalarValue($expr->value);
        } elseif ($expr instanceof Scalar\LNumber) {
            $value = new ScalarValue($expr->value);
        } elseif ($expr instanceof Expr\New_) {
            $class = null;
            if ($expr->class instanceof Expr) {
                $nameRef = Utils::getValueRef($expr->class);
                if ($nameRef !== null && !$nameRef->isMutable()) {
                    $name = $nameRef->getValue();
                }
            } else {
                $class = $expr->class->toString();
            }
            if ($class != null) {
                if (strtolower($class) === 'stdclass') {
                    $value = new ObjectVal();
                }
            }
        } elseif ($expr instanceof Expr\ErrorSuppress) {
            $value = Utils::getValueRef($expr->expr);
        }
        if ($value === null) {
            // Try resolving any variable references
            $varRef = $this->resolver->resolveVariable($expr);
            $value = $varRef->getValue($this->resolver->getCurrentScope());
        }
        if ($value !== null) {
            $expr->setAttribute(AttrName::VALUE, $value);
        }
    }

}
