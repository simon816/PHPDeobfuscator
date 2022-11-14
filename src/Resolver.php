<?php

namespace PHPDeobfuscator;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;

use PHPDeobfuscator\ValRef\ByReference;
use PHPDeobfuscator\ValRef\GlobalVarArray;
use PHPDeobfuscator\ValRef\ScalarValue;
use PHPDeobfuscator\VarRef\ArrayAccessVariable;
use PHPDeobfuscator\VarRef\ListVarRef;
use PHPDeobfuscator\VarRef\LiteralName;
use PHPDeobfuscator\VarRef\FutureVarRef;
use PHPDeobfuscator\VarRef\PropertyAccessVariable;
use PHPDeobfuscator\VarRef\UnknownVarRef;

class Resolver extends \PhpParser\NodeVisitorAbstract
{

    private $scope = null;
    private $globalScope;
    private $nameScope;
    private $constants;

    public function __construct()
    {
        // TODO This was in beforeTraverse but we want to share globals
        // between calls to eval so can't reset on each traversal.
        // Maybe make an option to share certain things in eval
        $this->scope = null;
        $this->newScope('global');
        $this->scope->setSuperGlobal('GLOBALS', new GlobalVarArray($this));
        $this->globalScope = $this->scope;
        $this->nameScope = array(
            'function' => '',
            'namespace' => '',
            'class' => '',
            'method' => '',
            'trait' => ''
        );
        $this->constants = array(
            'PHP_EOL' => "\n"
        );
    }

    public function enterNode(Node $node)
    {
        if ($node->getAttribute('enterMutableContext')) {
            $this->setCurrentVarsMutable();
        }
        $this->updateNameScope($node, true);
        if ($this->changesScope($node)) {
            $this->newScope($this->nameForScope($node));
            // Inherit variables from the use clause
            if ($node instanceof Expr\Closure) {
                foreach ($node->uses as $use) {
                    $var = new LiteralName($use->var->name);
                    $parentScope = $this->scope->getParent();
                    if ($use->byRef) {
                        $val = new ByReference($var, $parentScope);
                    } else {
                        $val = $var->getValue($parentScope);
                    }
                    // Only assign if variable is known
                    if ($val !== null) {
                        $var->assignValue($this->scope, $val);
                    }
                }
            }
        }
        // Transform AssignOp into the longer form BinaryOp
        if ($node instanceof Expr\AssignOp) {
            $op = str_replace('AssignOp', 'BinaryOp', get_class($node));
            return new Expr\Assign($node->var, new $op($node->var, $node->expr));
        }

        if ($node instanceof Stmt\For_) {
            // Everything except the init expression
            $this->setNodesInMutableContext($node->cond);
            $this->setNodesInMutableContext($node->loop);
            $this->setNodesInMutableContext($node->stmts);
        }
        if ($node instanceof Stmt\Foreach_) {
            $this->setNodesInMutableContext($node->stmts);
        }
        if ($node instanceof Stmt\While_
            || $node instanceof Stmt\Do_
            || $node instanceof Stmt\Case_
            || $node instanceof Stmt\Label) {
            $this->setCurrentVarsMutable();
        }
    }

    private function setNodesInMutableContext(array $nodes)
    {
        foreach ($nodes as $node) {
            $node->setAttribute('enterMutableContext', true);
            return;
        }
    }

    private function nodeCanBranch(Node $node)
    {
        return $node instanceof Stmt\If_
            || $node instanceof Stmt\For_
            || $node instanceof Stmt\Foreach_
            || $node instanceof Stmt\While_
            || $node instanceof Stmt\Do_
            || $node instanceof Stmt\Switch_;
    }

    public function leaveNode(Node $node)
    {
        $retNode = null;
        $this->updateNameScope($node, false);
        if ($this->changesScope($node)) {
            $this->leaveScope();
        }
        if ($node instanceof Expr\Assign) {
            $this->onAssign($node);
            // Try to transform BinaryOp back into AssignOp
            if ($node->expr instanceof Expr\BinaryOp) {
                $op = str_replace('BinaryOp', 'AssignOp', get_class($node->expr));
                if (class_exists($op)) {
                    $varRef = $this->resolveVariable($node->var);
                    $leftVar = $this->resolveVariable($node->expr->left);
                    $isVarRef = !($leftVar instanceof UnknownVarRef) || !$leftVar->notAVarRef();
                    // If they are the same reference then we can combine
                    if ($isVarRef && $varRef == $leftVar) {
                        $retNode = new $op($node->expr->left, $node->expr->right);
                    }
                }
            }
        }
        if ($node instanceof Expr\AssignRef) {
            $this->onAssignRef($node);
        }
        if ($node instanceof Stmt\Unset_) {
            $this->onUnset($node);
        }
        if ($node instanceof Stmt\Global_) {
            foreach ($node->vars as $var) {
                $var = $this->resolveVariable($var);
                $val = $var->getValue($this->getGlobalScope());
                $this->assign($var, $val);
            }
        }
        if ($node instanceof Expr\FuncCall) {
            $this->onFuncCall($node);
        }
        if ($this->nodeCanBranch($node)) {
            $this->setCurrentVarsMutable();
        }
        return $retNode;
    }

    private function setCurrentVarsMutable()
    {
        foreach ($this->scope->getVariables() as $var) {
            $var->setMutable(true);
        }
    }

    private function changesScope(Node $node)
    {
        return $node instanceof Stmt\Function_
            || $node instanceof Stmt\ClassMethod
            || $node instanceof Expr\Closure;
    }

    private function nameForScope(Node $node)
    {
        if ($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod) {
            return $this->nameScope['function'];
        }
        if ($node instanceof Expr\Closure) {
            return 'closure';
        }
        return $node->getType();
    }

    private function newScope($name)
    {
        $this->scope = new Scope($name, $this->scope);
    }

    private function updateNameScope(Node $node, $isEnter)
    {
        $key = null;
        if ($node instanceof Stmt\Namespace_) {
            $key = 'namespace';
        } elseif ($node instanceof Stmt\Function_) {
            $key = 'function';
        } elseif ($node instanceof Stmt\Class_) {
            $key = 'class';
        } elseif ($node instanceof Stmt\ClassMethod) {
            $key = 'method';
        } elseif ($node instanceof Stmt\Trait_) {
            $key = 'trait';
        } else {
            return;
        }
        if ($isEnter) {
            // name is either Name or Identifier, both have toString
            $name = $node->name ? $node->name->toString() : '';
            if ($key == 'method') {
                // function is set to the name of the method
                $this->nameScope['function'] = $name;
                $parentName = $this->nameScope['class'] . $this->nameScope['trait'];
                if ($parentName) {
                    $name = $parentName . '::' . $name;
                }
            }
            // If we've entered into a trait, the class can't be known
            if ($key == 'trait') {
                $this->nameScope['class'] = null;
            }
            if (in_array($key, array('class', 'trait', 'function')) && $this->nameScope['namespace']) {
                $name = $this->nameScope['namespace'] . '\\' . $name;
            }
            if ($key == 'function') {
                $this->nameScope['method'] = $name;
            }
            $this->nameScope[$key] = $name;
        } else {
            $this->nameScope[$key] = '';
            if ($key == 'method') {
                $this->nameScope['function'] = '';
            }
            if ($key == 'function') {
                $this->nameScope['method'] = '';
            }
            if ($key == 'trait') {
                $this->nameScope['class'] = '';
            }
        }
    }

    private function leaveScope()
    {
        $this->scope = $this->scope->getParent();
    }

    public function getConstant($name)
    {
        if (isset($this->constants[$name])) {
            return new ScalarValue($this->constants[$name]);
        }
        // PHP assumes a string of the name of the constant
        return new ScalarValue($name);
    }

    public function getCurrentScope()
    {
        return $this->scope;
    }

    public function getGlobalScope()
    {
        return $this->globalScope;
    }

    public function cloneScope()
    {
        // TODO nameScope and constants
        return clone $this->scope;
    }

    public function resetScope(Scope $scope)
    {
        $this->scope = clone $scope;
        // Reset globalScope to ensure correct reference
        do {
            $this->globalScope = $scope;
            $scope = $scope->getParent();
        } while ($scope != null);
    }

    public function currentClass()
    {
        return $this->nameScope['class'];
    }

    public function currentFunction()
    {
        return $this->nameScope['function'];
    }

    public function currentMethod()
    {
        return $this->nameScope['method'];
    }

    public function currentNamespace()
    {
        return $this->nameScope['namespace'];
    }

    public function currentTrait()
    {
        return $this->nameScope['trait'];
    }

    private function onFuncCall(Expr\FuncCall $expr)
    {
        $name = null;
        if ($expr->name instanceof Node\Name) {
            $name = $expr->name->toString();
        } else {
            $nameRef = $this->resolveValue($expr->name);
            if ($nameRef !== null && !$nameRef->isMutable()) {
                $name = $nameRef->getValue();
            }
        }
        if ($name === null) {
            return;
        }
        $argCount = count($expr->args);
        // Should set vars mutable here if name is null due to the chance that
        // it's really parse_str or extract, but that's unlikely so don't ruin all variables
        // for something very unlikely
        switch ($name) {
        case 'parse_str':
            if ($argCount > 1) {
                break;
            }
        case 'extract':
            $this->setCurrentVarsMutable();
            break;
        case 'define':
            if ($argCount >= 2) {
                $this->onDefine($expr->args[0]->value, $expr->args[1]->value);
            }
            break;
        }
    }

    private function onDefine(Expr $name, Expr $value)
    {
        $nameRef = $this->resolveValue($name);
        if ($nameRef === null || $nameRef->isMutable()) {
            return;
        }
        $valRef = $this->resolveValue($value);
        if ($valRef === null || $valRef->isMutable() || !($valRef instanceof ScalarValue)) {
            return;
        }
        $constName = $nameRef->getValue();
        if (array_key_exists($constName, $this->constants)) {
            return; // PHP won't override existing constants
        }
        $this->constants[$constName] = $valRef->getValue();
    }

    private function onAssign(Expr\Assign $expr)
    {
        $varRef = $this->resolveVariable($expr->var);
        $valRef = $this->resolveValue($expr->expr);
        $this->assign($varRef, $valRef);
    }

    private function onAssignRef(Expr\AssignRef $expr)
    {
        $var = $this->resolveVariable($expr->var);
        $ref = $this->resolveVariable($expr->expr);
        if (!($ref instanceof UnknownVarRef) || !$ref->notAVarRef()) {
            $val = new ByReference($ref, $this->scope);
        } else {
            // Possible assignment to a non-variable - just a normal assignment
            $val = $this->resolveValue($expr->expr);
        }
        $this->assign($var, $val);
    }

    private function assign(VarRef $var, ValRef $val = null)
    {
        $didAssign = false;
        if ($val !== null) {
            while (($oldValue = $var->getValue($this->scope)) instanceof ByReference) {
                $var = $oldValue->getVariable();
            }
            $didAssign = $var->assignValue($this->scope, $val);
        }
        if (!$didAssign) {
            if ($var instanceof UnknownVarRef) {
                if ($var->getContext() === null) {
                    // If this was an unknown variable assignment with no parent context, all bets are off
                    $this->setCurrentVarsMutable();
                } else {
                    // Otherwise, only the parent needs to be set mutable
                    $var = $var->getContext();
                }
            }
            if ($var instanceof ListVarRef) {
                foreach ($var->getVars() as $listVar) {
                    if ($listVar === null) {
                        continue;
                    }
                    $oldValue = $listVar->getValue($this->scope);
                    if ($oldValue !== null) {
                        $oldValue->setMutable(true);
                    }
                }
            } else {
                $oldValue = $var->getValue($this->scope);
                if ($oldValue !== null) {
                    $oldValue->setMutable(true);
                }
            }
        }
    }

    private function onUnset(Stmt\Unset_ $stmt)
    {
        foreach ($stmt->vars as $expr) {
            $var = $this->resolveVariable($expr);
            $var->unsetVar($this->scope);
        }
    }

    private function resolveValue(Expr $expr, $tryUnknownVar = false)
    {
        try {
            return Utils::getValueRef($expr);
        } catch (Exceptions\UnknownValueException $e) {
            if ($tryUnknownVar) {
                return $this->resolveVariable($expr)->getValue($this->scope);
            }
            return null;
        }
    }

    // See FutureVarRef for why $tryUnknownVar is needed
    public function resolveVariable(Expr $var, $tryUnknownVar = false)
    {
        if ($var instanceof Expr\Variable) {
            $varName = $var->name;
            if (is_string($varName)) {
                return new LiteralName($varName);
            } else {
                $nameRef = $this->resolveValue($varName, $tryUnknownVar);
                if ($nameRef !== null && !$nameRef->isMutable()) {
                    // Replace name in tree
                    $var->name = $nameRef->getValue();
                    return new LiteralName($nameRef->getValue());
                }
                return UnknownVarRef::$ANY;
            }
        } elseif ($var instanceof Expr\List_) {
            $vars = array();
            foreach ($var->items as $item) {
                if ($item === null) {
                    $vars[] = null;
                    continue;
                }
                if($item->key !== null || $item->byRef) {
                    throw new \Exception("Don't know how to handle element in list()");
                }
                $varExpr = $item->value;
                $varRef = $this->resolveVariable($varExpr, $tryUnknownVar);
                if ($varRef instanceof UnknownVarRef) {
                    $varRef = new FutureVarRef($varExpr, $this);
                }
                $vars[] = $varRef;
            }
            return new ListVarRef($vars);
        } elseif ($var instanceof Expr\ArrayDimFetch) {
            $arrVar = $this->resolveVariable($var->var, $tryUnknownVar);
            if ($arrVar instanceof UnknownVarRef) {
                return $arrVar;
            }
            if ($var->dim === null) { // e.g. $arr[] = 1;
                $dim = new ScalarValue(null);
            } else {
                $dim = $this->resolveValue($var->dim, $tryUnknownVar);
            }
            if ($dim !== null && !$dim->isMutable()) {
                return new ArrayAccessVariable($arrVar, $dim->getValue());
            }
            return new UnknownVarRef($arrVar);
        } elseif ($var instanceof Expr\PropertyFetch) {
            $objVar = $this->resolveVariable($var->var, $tryUnknownVar);
            if ($objVar instanceof UnknownVarRef) {
                return $objVar;
            }
            if ($var->name instanceof Expr) {
                $nameVal = $this->resolveValue($var->name, $tryUnknownVar);
                if ($nameVal !== null && !$nameVal->isMutable()) {
                    $name = $nameVal->getValue();
                } else {
                    $name = null;
                }
            } else {
                $name = $var->name->name;
            }
            if ($name !== null) {
                return new PropertyAccessVariable($objVar, $name);
            }
            return new UnknownVarRef($objVar);
        } elseif ($var instanceof Expr\StaticPropertyFetch) {
            // TODO
        }
        return UnknownVarRef::$NOT_A_VAR_REF;
    }
}
