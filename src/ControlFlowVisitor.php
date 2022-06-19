<?php

namespace PHPDeobfuscator;

use PhpParser\Node;
use PhpParser\Node\Stmt;

class LabelScope
{
    public $currentBlock;
    public $root;
    private $counter = 0;
    private $blockStack = array();
    private $blocks = array();

    public function __construct(CodeBlock $root)
    {
        $this->counter = 0;
        $this->blockStack = array();
        $this->blocks = array();
        $this->currentBlock = $this->root = $root;
        $root->setDefined();
        $this->blockStack[] = $root;
    }

    public function getBlock($label)
    {
        if (!array_key_exists($label, $this->blocks)) {
            $this->blocks[$label] = new CodeBlock($label);
        }
        return $this->blocks[$label];
    }

    private function mkname($key)
    {
        return $this->currentBlock->name . $key . $this->counter++;
    }

    public function makeNested($name)
    {
        $this->blockStack[] = $this->currentBlock;
        $nested = $this->getBlock($this->mkname($name));
        $nested->setDefined();
        $this->currentBlock->addNested($nested);
        $this->currentBlock = $nested;
        return $nested;
    }

    public function popStack()
    {
        $this->currentBlock = array_pop($this->blockStack);
    }

    public function getBlocks()
    {
        return $this->blocks;
    }
}

class ControlFlowVisitor extends \PhpParser\NodeVisitorAbstract
{
    private $scope;
    private $scopeStack = array();
    private $nestedTypes = array();

    public function __construct()
    {
        $this->defineNested('If_', 'stmts', array('elseifs', 'else'));
        $this->defineNested('ElseIf_', 'stmts');
        $this->defineNested('Else_', 'stmts');

        $this->defineNested('Switch_', 'cases');
        $this->defineNested('Case_', 'stmts');

        $this->defineNested('TryCatch', 'stmts', array('catches', 'finally'));
        $this->defineNested('Catch_', 'stmts');
        $this->defineNested('Finally_', 'stmts');

        $this->defineNested('For_', 'stmts');
        $this->defineNested('Foreach_', 'stmts');
        $this->defineNested('Do_', 'stmts');
        $this->defineNested('While_', 'stmts');

        $this->defineNested('Function_', 'stmts', array(), true);
        $this->defineNested('Trait_', 'stmts');
        $this->defineNested('Class_', 'stmts');
        $this->defineNested('ClassMethod', 'stmts', array(), true);
        $this->defineNested('Interface_', 'stmts');
        $this->defineNested('Namespace_', 'stmts');

        // Special case: Closure is an expression not a statement
        $this->nestedTypes[Node\Expr\Closure::class] = array('stmts', '_Closure', array(), true);
    }

    private function defineNested($class, $stmtAttr, array $subNodes = array(), $changeScope = false)
    {
        $this->nestedTypes[Stmt::class . '\\' . $class] = array($stmtAttr, '_' . $class, $subNodes, $changeScope);
    }

    public function beforeTraverse(array $nodes)
    {
        $this->scope = new LabelScope(new CodeBlock('<main>'));
        $this->scopeStack = array();
        $nodes[] = new Stmt\Return_(null, array(
            'comments' => array(new \PhpParser\Comment('// [PHPDeobfuscator] Implied script end')),
            'impliedReturn' => true
        ));
        return $nodes;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Stmt\Label) {
            $block = $this->scope->getBlock($node->name->name);
            $block->setDefined();
            if ($this->scope->currentBlock->isEmpty()) {
                $this->scope->currentBlock->setAlias($block);
            } else {
                // Add explicit goto from the implicit fall through
                $this->scope->currentBlock->append(new Stmt\Goto_($node->name, array(
                    'comments' => array(new \PhpParser\Comment('// [PHPDeobfuscator] Implied goto'))
                )));
                $this->moveInto($block);
            }
            $this->scope->currentBlock = $block->root();
        }
        // Stmt\Label done before attaching node
        $ignore = $node instanceof Stmt\Else_
                || $node instanceof Stmt\ElseIf_
                || $node instanceof Stmt\Catch_
                || $node instanceof Stmt\Finally_;
        if (!$ignore) {
            $this->scope->currentBlock->append($node);
        }

        // Unwrap to get the expression node
        if ($node instanceof Stmt\Expression) {
            $node = $node->expr;
        }

        $className = get_class($node);

        if (array_key_exists($className, $this->nestedTypes)) {
            list($stmtAttr, $name, $subNodes, $changeScope) = $this->nestedTypes[$className];
            return $this->nestedNode($node, $name, $stmtAttr, $subNodes, $changeScope);
        }

        if ($node instanceof Stmt\Goto_) {
            $block = $this->scope->getBlock($node->name->name);
            $this->moveInto($block);
        }

        // Nodes that signal unreachability
        if ($node instanceof Stmt\Goto_
            || $node instanceof Node\Expr\Exit_
            || $node instanceof Stmt\Return_
            || $node instanceof Stmt\Continue_
            || $node instanceof Stmt\Break_
            || $node instanceof Stmt\Throw_
            || $node instanceof Stmt\HaltCompiler
        ) {
            $this->scope->currentBlock->setUnreachable();
        }
        return \PhpParser\NodeTraverser::DONT_TRAVERSE_CHILDREN;
    }

    private function nestedNode(Node $node, $name, $stmtAttr, array $subNodes = array(), $changeScope = false)
    {
        $nested = $this->scope->makeNested($name);
        if ($changeScope) {
            if (count($subNodes) !== 0) {
                throw new \Exception("Cannot have sub nodes on scope changing node");
            }
            $this->scopeStack[] = $this->scope;
            $this->scope = new LabelScope($nested);
            $node->setAttribute('changeScope', true);
        } else {
            $node->setAttribute('buildLater', $nested);
            $node->setAttribute('subNodes', $subNodes);
        }
        $node->setAttribute('stmtAttr', $stmtAttr);
        $node->setAttribute('isNested', true);
        return new WrappedNode($node, array_merge(array($stmtAttr), $subNodes));
    }

    private function moveInto(CodeBlock $to)
    {
        $from = $this->scope->currentBlock;
        // Only move if the from is reachable, otherwise $to will have a ghost enter point
        if (!$from->isUnreachable()) {
            $from->setExit($to);
            $to->enter($from);
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof WrappedNode) {
            $node = $node->unwrap();
        }
        if ($node instanceof Stmt\Function_
         || $node instanceof Stmt\ClassMethod
         || $node instanceof Node\Expr\Closure) {
            // Add implied return
            $this->scope->currentBlock->append(new Stmt\Return_(null, array(
                'comments' => array(new \PhpParser\Comment('// [PHPDeobfuscator] Implied return')),
                'impliedReturn' => true
            )));
        }
        if ($node->getAttribute('changeScope') === true) {
            $stmtAttr = $node->getAttribute('stmtAttr');
            $node->$stmtAttr = $this->rebuildAndTrim();
            $this->scope = array_pop($this->scopeStack);
        }
        if ($node->getAttribute('isNested') === true) {
            $this->scope->popStack();
        }
        return $node;
    }

    private function rebuildAndTrim()
    {
        $nodes = $this->scope->root->root()->rebuild();
        while ($nodes && $nodes[count($nodes) - 1]->hasAttribute('impliedReturn')) {
            array_pop($nodes);
        }
        return $nodes;
    }

    public function afterTraverse(array $nodes)
    {
        return $this->rebuildAndTrim();
    }

}

class WrappedNode implements Node
{
    public function __construct(Node $node, array $subNodes)
    {
        $this->node = $node;
        $this->subNodes = $subNodes;
        foreach ($subNodes as $name) {
            $this->$name = $node->$name;
        }
    }

    public function getSubNodeNames() : array { return $this->subNodes; }

    public function unwrap() { return $this->node; }

    public function getType() : string { return $this->node->getType(); }

    public function getLine() : int { return $this->node->getLine(); }

    public function getStartLine() : int { return $this->node->getStartLine(); }

    public function getEndLine() : int { return $this->node->getEndLine(); }

    public function getStartTokenPos() : int { return $this->node->getStartTokenPos(); }

    public function getEndTokenPos() : int { return $this->node->getEndTokenPos(); }

    public function getStartFilePos() : int { return $this->node->getStartFilePos(); }

    public function getEndFilePos() : int { return $this->node->getEndFilePos(); }

    public function getComments() : array { return $this->node->getComments(); }

    public function getDocComment() { return $this->node->getDocComment(); }

    public function setDocComment(\PhpParser\Comment\Doc $docComment) { $this->node->setDocComment($docComment); }

    public function setAttribute(string $key, $value) { $this->node->setAttribute($key, $value); }

    public function hasAttribute(string $key) : bool { return $this->node->hasAttribute($key); }

    public function getAttribute(string $key, $default = null) { return $this->node->getAttribute($key, $default); }

    public function getAttributes() : array { return $this->node->getAttributes(); }

    public function setAttributes(array $attributes) { $this->node->setAttributes($attributes); }
}

class CodeBlock
{
    public $name;
    private $exitBlock;
    // Blocks which enter our block (indexed by their name)
    private $enters = array();
    private $nested = array();
    private $unreachable;
    private $nodes = array();
    private $unreachableNodes = array();
    private $built = false;
    private $aliasOf;
    // For every Label we possess, keep track of the number of enters using this name
    private $entersPerName = array();
    // Map the name of an enter block (in $enters) to the name we saw it enter by
    private $enterByName = array();
    private $exitOrigName;
    private $defined = false;

    public function __construct($name)
    {
        $this->name = $name;
        $this->entersPerName[$name] = 0;
    }

    public function rebuild($removeLabel = null)
    {
        $this->checkAlias();
        if ($this->built || !$this->defined) {
            return array();
        }
        $this->built = true;
        $nodes = array();
        $removeGotoExit = false;
        if ($this->exitBlock !== null) {
            // Remove the jump to our exit block if it's not been built yet
            $removeGotoExit = !$this->exitBlock->built && $this->exitBlock->defined;
            // If our exit block has only one entrance (i.e. us), then skip outputting its label
            $removeExitLabel = $removeGotoExit && $this->exitBlock->entersPerName[$this->exitOrigName] === 1;
            $nodes = $this->exitBlock->rebuild($removeExitLabel ? $this->exitOrigName : null);

            // Add these nodes back on so we don't loose code when a block is undefined
            if (!$this->exitBlock->defined) {
                $this->nodes = array_merge($this->nodes, $this->unreachableNodes);
            }
        }
        $nodes = array_merge(array_filter(array_map(array($this, 'processNode'), $this->nodes),
            function($node) use ($removeLabel, $removeGotoExit) {
                if ($node instanceof Stmt\Label) {
                    // If no enters to label or label is $removeLabel, remove.
                    return $this->entersPerName[$node->name->name] !== 0 && (!$removeLabel || $node->name->name !== $removeLabel);
                }
                if ($removeGotoExit && $node instanceof Stmt\Goto_) {
                    return $node->name->name !== $this->exitOrigName;
                }
                return true;
            }), $nodes);
        return $nodes;
    }

    private function processNode(Node $node)
    {
        $subBlock = $node->getAttribute('buildLater');
        if ($subBlock !== null) {
            $stmtAttr = $node->getAttribute('stmtAttr');
            $node->$stmtAttr = $subBlock->rebuild();
            $subNodes = $node->getAttribute('subNodes');
            foreach ($subNodes as $name) {
                if (is_array($node->$name)) {
                    $node->$name = array_map(array($this, 'processNode'), $node->$name);
                } elseif (!is_null($node->$name)) {
                    $node->$name = $this->processNode($node->$name);
                }
            }
        }
        return $node;
    }

    public function setAlias(CodeBlock $other)
    {
        if ($this->aliasOf) {
            throw new \Exception("Block {$this->name} already has alias of {$this->aliasOf->name}, tried to set alias to {$other->name}");
        }
        if ($this->exitBlock) {
            throw new \Exception("Cannot alias if block exits somewhere. Tried to set alias {$other->name} but {$this->name} is exiting at {$this->exitBlock->name}");
        }
        if ($this->nested) {
            throw new \Exception("Cannot alias if block has nested blocks (block {$this->name})");
        }
        if (!$this->isEmpty()) {
            throw new \Exception("Cannot alias if block has nodes (block {$this->name})");
        }
        $this->aliasOf = $other;
        // Anywhere that enters our block will now enter the alias
        foreach ($this->enters as $block) {
            // It must enter the alias under the name it entered us by, to propagate
            // the referenced label name forward
            $other->enter($block, $this->enterByName[$block->name]);
            $block->exitBlock = $other;
        }
        $this->enters = array(); // ensure unreachable
        $this->enterByName = array();
        if ($this->nodes) {
            // Put our label(s) on the other block
            $other->nodes = array_merge($this->nodes, $other->nodes);
        }
        // Any unreferenced aliased names that we picked up should now be picked
        // up by the other block (referenced names already handled above)
        foreach (array_keys($this->entersPerName) as $ourName) {
            if (!array_key_exists($ourName, $other->entersPerName)) {
                $other->entersPerName[$ourName] = 0;
            }
        }
        $this->setUnreachable();
    }

    public function isEmpty()
    {
        // if just labels, then empty
        foreach ($this->nodes as $node) {
            if (!($node instanceof Stmt\Label)) {
                return false;
            }
        }
        return true;
    }

    public function setUnreachable()
    {
        $this->unreachable = true;
    }

    public function setDefined()
    {
        $this->defined = true;
    }

    private function checkAlias()
    {
        if ($this->aliasOf) {
            throw new \Exception("Block {$this->name} is alias!");
        }
    }

    public function root()
    {
        $block = $this;
        while ($block->aliasOf) {
            $block = $block->aliasOf;
        }
        return $block;
    }

    public function append(Node $node)
    {
        $this->checkAlias();
        if ($this->unreachable) {
            if ($this->exitBlock && !$this->exitBlock->defined) {
                $this->unreachableNodes[] = $node;
            }
            return;
        }
        $this->nodes[] = $node;
    }

    public function isUnreachable()
    {
        return $this->unreachable;
    }

    public function setExit(CodeBlock $block)
    {
        $this->checkAlias();
        $this->exitOrigName = $block->name;
        // If our exit block will be an alias, exit to the destination
        while ($block->aliasOf) {
            $block = $block->aliasOf;
        }
        if ($this->exitBlock !== null) {
            throw new \Exception("Cannot have multiple exits! Was '{$this->exitBlock->name}', attempted exit to '{$block->name}'");
        }
        $this->exitBlock = $block;
    }

    // $block enters our block, optionally using an alternate name
    public function enter(CodeBlock $block, $targetName = null)
    {
        $targetName = $targetName ?: $this->name;
        if ($this->aliasOf) {
            $this->aliasOf->enter($block, $targetName);
            return;
        }
        $this->enters[$block->name] = $block;
        $this->enterByName[$block->name] = $targetName;
        // Increment the refcount of the effective name that entered us
        if (!isset($this->entersPerName[$targetName])) {
            $this->entersPerName[$targetName] = 0;
        }
        $this->entersPerName[$targetName]++;
    }

    public function addNested(CodeBlock $inner)
    {
        $this->checkAlias();
        $this->nested[] = $inner;
    }

}
