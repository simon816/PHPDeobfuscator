<?php

namespace PHPDeobfuscator;

use PhpParser\Node;
use PhpParser\NodeAbstract;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Name;

trait FakeTrait
{

    public function __construct($key, $realClass)
    {
        parent::__construct();
        $this->key = $key;
        $this->_realClass = $realClass;
    }

    public function getSubNodeNames() : array
    {
        return ['key'];
    }

    public function getRealClass()
    {
        return $this->_realClass;
    }

    public function getType() : string
    {
        return 'FakeNode';
    }

}

class FakeNode extends NodeAbstract
{
    use FakeTrait;
}

// Keep types similar to real types

class FakeNodeName extends Name
{
    use FakeTrait;

    public function __construct($key, $realType)
    {
        parent::__construct($key);
        $this->key = $key;
        $this->_realType = $realType;
    }
}

class FakeNodeExpr extends Expr
{
    use FakeTrait;
}

class FakeNodeStmt extends Stmt
{
    use FakeTrait;
}

class FakeNodeVar extends Expr\Variable
{
    use FakeTrait;

    public function __construct($key, $realType)
    {
        parent::__construct($key);
        $this->key = $key;
        $this->_realType = $realType;
    }
}

class FakePrinter extends ExtendedPrettyPrinter
{
    public function printNode(Node $node)
    {
        // Don't call handleMagicTokens here - the tokens are needed for later
        if ($node instanceof Stmt) {
            return ltrim($this->pStmts([$node], false));
        }
        return $this->p($node);
    }

    protected function pFakeNode(Node $node)
    {
        return "__NODE[{$node->key}]";
    }

    // Copied from PrettyPrinterAbstract
    // Deals with FakeNode to get the real precedence
    protected function pPrec(Node $node, int $parentPrecedence, int $parentAssociativity, int $childPosition) : string {
        $class = \get_class($node);
        if ($node->getType() === 'FakeNode') {
            $class = $node->getRealClass();
        }
        if (isset($this->precedenceMap[$class])) {
            $childPrecedence = $this->precedenceMap[$class][0];
            if ($childPrecedence > $parentPrecedence
                || ($parentPrecedence === $childPrecedence && $parentAssociativity !== $childPosition)
            ) {
                return '(' . $this->p($node) . ')';
            }
        }

        return $this->p($node);
    }
}

class MetadataVisitor extends \PhpParser\NodeVisitorAbstract
{
    private $printer;
    private $nodeStack = array();

    public function __construct(Deobfuscator $deobfuscator)
    {
        $this->printer = new FakePrinter();
    }

    public function enterNode(Node $node)
    {
        $this->nodeStack[] = [$node, nodeChildren($node)];
    }

    public function leaveNode(Node $newNode)
    {
        list($origNode, $origChildren) = array_pop($this->nodeStack);
        if (nodeChanged($origNode, $origChildren, $newNode)) {
            $this->processReduction($origNode, $origChildren, $newNode);
        }
    }

    private function processReduction($origNode, $origChildren, $newNode)
    {
        $p = $this->printer;
        $substituteNodes = [];
        $newNode->setAttribute('origClass', get_class($origNode));
        $origCurrentChildren = [];

        $evalBlockReplace = $newNode instanceof EvalBlock && $newNode->origStmts !== null;
        $evalExprReplace = $origNode instanceof Expr\Eval_ && !($newNode instanceof EvalBlock);
        if ($evalBlockReplace) {
            // Our "original node" becomes an EvalBlock with the original statements
            // That node's original node is the original Eval(String) node
            $replacedNode = new EvalBlock($newNode->stmts, null);
            $this->processReduction($origNode, $origChildren, $replacedNode);
            $origChildren = ['stmts' => $newNode->origStmts];
            $origNode = $replacedNode;
        }


        foreach ($origNode->getSubNodeNames() as $subName) {
            $childNode = $origNode->$subName;
            $origCurrentChildren[$subName] = $childNode;
            // Special case for FuncCallReducer
            if ($childNode instanceof Node && $childNode->hasAttribute('replaces')) {
                $repl = $childNode->getAttribute('replaces');
                $this->processReduction($repl, nodeChildren($repl), $childNode);
            }
            $substituteNodes[$subName] = getSub($origChildren[$subName], $childNode);
            $origNode->$subName = fake($substituteNodes[$subName], $subName);
        }

        $oldStr = $p->printNode($origNode);
        $sections = [['str', $oldStr]];
        foreach ($origNode->getSubNodeNames() as $subName) {
            $origNode->$subName = $origCurrentChildren[$subName];
            replaceFake($substituteNodes[$subName], $sections, $subName, $p);
        }

        $flatter = flattenSections($sections);
        $newStr = $p->printNode($newNode);

        $replace = null;
        if ($evalBlockReplace) {
            $evalReduced = $origNode->getAttribute(AttrName::REDUCED_FROM);
            $replace = $evalReduced['O'];
        }
        if ($evalExprReplace) {
            $newReduced = $newNode->getAttribute(AttrName::REDUCED_FROM);
            $replace = $flatter;
            $flatter = $newReduced['O'];
        }

        $oldDerrived = stringifyFlat($flatter);

        // No changes to "new" in this round - just use previous round
        if ($oldDerrived === $newStr) {
            if ($replace !== null) {
                $newNode->setAttribute(AttrName::REDUCED_FROM, ['O' => $flatter, 'R' => $replace]);
            } else {
                // P = passthrough, always removed by the flattener
                $newNode->setAttribute(AttrName::REDUCED_FROM, ['P' => $flatter]);
            }
            return;
        }
        $oldObj = $flatter;
        if (count($flatter) === 1) {
            $oldObj = $flatter[0];
        }
        $reduced = ['O' => $oldObj, 'N' => $newStr];
        if ($replace !== null) {
            $reduced['R'] = $replace;
        }
        $newNode->setAttribute(AttrName::REDUCED_FROM, $reduced);
    }

    public function printFileReductions(array $stmts)
    {
        $p = $this->printer;
        $fileStr = $p->prettyPrintFile(fake($stmts, 'ROOT'));
        $sections = [['str', $fileStr]];
        replaceFake($stmts, $sections, 'ROOT', $p);
        // XXX: fix indent token
        return _realFixIndent("", flattenSections($sections), 'INDENT_TOK', true);
    }

}

function stringifyFlat(array $flatter)
{
    return implode('', array_map(function ($part) {
        if (is_array($part)) {
            if (isset($part['R']) && !isset($part['N'])) {
                if (is_array($part['O'])) {
                    return stringifyFlat($part['O']);
                }
                return $part['O'];
            }
            return $part['N'];
        }
        return $part;
    }, $flatter));
}

function getSub($origNode, $currNode)
{
    // Prefer current node if it has a reduced from attr
    if ($currNode instanceof Node && $currNode->hasAttribute(AttrName::REDUCED_FROM)) {
        return $currNode;
    } elseif (is_array($currNode)) {
        $arr = [];
        foreach ($currNode as $i => $elem) {
            $arr[] = getSub($origNode[$i], $elem);
        }
        return $arr;
    } else {
        // Fallback to original node
        return $origNode;
    }
}

// Flatten the sections slightly
function flattenSections($sections)
{
    $flatter = [];
    $canAppend = false;
    foreach($sections as $section) {
        list($type, $value) = $section;
        if ($type === 'str' || $type === 'node') {
            if ($canAppend) {
                $flatter[count($flatter) - 1] .= $value;
            } else {
                $flatter[] = $value;
                $canAppend = true;
            }
        } elseif ($type == 'reducedNode') {
            // passthrough
            if (isset($value['P'])) {
                foreach($value['P'] as $toMerge) {
                    if (gettype($toMerge) === 'string') {
                        if ($canAppend) {
                            $flatter[count($flatter) - 1] .= $toMerge;
                        } else {
                            $flatter[] = $toMerge;
                            $canAppend = true;
                        }
                    } else {
                        $flatter[] = $toMerge;
                        $canAppend = false;
                    }
                }
            } else {
                $flatter[] = $value;
                $canAppend = false;
            }
        }
    }
    return $flatter;
}

function replaceFake($val, &$sections, $name, $p)
{
    if (is_array($val)) {
        foreach($val as $i => $elem) {
            replaceFake($elem, $sections, "{$name}[{$i}]", $p);
        }
    } elseif ($val instanceof Node && !($val instanceof Node\Scalar\EncapsedStringPart)) {
        processSubstitutions($sections, $name, $val, $p);
    }

}

function fake($val, $name)
{
    if (is_array($val)) {
        $fakeArr = [];
        foreach($val as $i => $elem) {
            $fakeArr[] = fake($elem, "{$name}[{$i}]");
        }
        return $fakeArr;
    } elseif ($val instanceof Name) {
        return new FakeNodeName($name, $val->getAttribute('origClass'));
    } elseif ($val instanceof Node\Scalar\EncapsedStringPart) {
        return $val;
    } elseif ($val instanceof Expr\Variable) {
        return new FakeNodeVar($name, $val->getAttribute('origClass'));
    } elseif ($val instanceof Expr) {
        return new FakeNodeExpr($name, $val->getAttribute('origClass'));
    } elseif ($val instanceof Stmt) {
        return new FakeNodeStmt($name, $val->getAttribute('origClass'));
    } elseif ($val instanceof Node) {
        return new FakeNode($name, $val->getAttribute('origClass'));
    }
    return $val;
}

function valIdentifier($value)
{
    if (gettype($value) === 'object') {
        return spl_object_hash($value);
    }
    if (is_array($value)) {
        return array_map('valIdentifier', $value);
    }
    return $value;
}

function nodeChildren(Node $node)
{
    $originalChildren = [];
    foreach($node->getSubNodeNames() as $subName) {
        $originalChildren[$subName] = $node->$subName;
    }
    return $originalChildren;
}

function nodeChanged(Node $oldNode, array $oldChildren, Node $newNode) {
    if ($oldNode !== $newNode) {
        return true;
    }
    if(nodeChildren($newNode) !== $oldChildren) {
        return true;
    }
    foreach ($oldChildren as $name => $childNode) {
        if (childIsReduced($childNode)) {
            return true;
        }
    }
    return false;
}

function childIsReduced($childNode)
{
    if (is_array($childNode)) {
        foreach ($childNode as $elem) {
            if (childIsReduced($elem)) {
                return true;
            }
        }
        return false;
    }
    if ($childNode instanceof Node) {
        return $childNode->hasAttribute(AttrName::REDUCED_FROM);
    }
    return false;
}

function getIndent($str)
{
    $lastLinePos = strrpos($str, "\n", -1);
    if ($lastLinePos === false) {
        $lastLinePos = 0;
    } else {
        $lastLinePos += 1;
    }
    $indentSize = strspn($str, " ", $lastLinePos);
    // Have spaces on line but there's stuff after it
    if ($indentSize + $lastLinePos != strlen($str)) {
        return 0;
    }
    return $indentSize;
}

function fixIdent($indent, $value, $noIndentToken)
{
    if ($indent === 0) {
        return $value;
    }
    return _fixNodeIndent(str_repeat(" ", $indent), $value, $noIndentToken);
}

function _fixNodeIndent($indent, $value, $noIndentToken, $stripNoIndent = false)
{
    if (is_array($value)) {
        $rebuilt = [];
        foreach($value as $key => $subVal) {
            $rebuilt[$key] = _realFixIndent($indent, $subVal, $noIndentToken, $stripNoIndent);
        }
        return $rebuilt;
    }
    return _realFixIndent($indent, $value, $noIndentToken, $stripNoIndent);
}

function _realFixIndent($indent, $value, $noIndentToken, $stripNoIndent = false)
{
    if (is_array($value)) {
        return array_map(function($entry) use ($indent, $noIndentToken, $stripNoIndent) {
            return _fixNodeIndent($indent, $entry, $noIndentToken, $stripNoIndent);
        }, $value);
    }
    $ret = preg_replace('/\n(?!$|' . $noIndentToken . ')/', "\n$indent", $value);
    if ($stripNoIndent) {
        $ret = str_replace($noIndentToken, '', $ret);
    }
    return $ret;
}

function processSubstitutions(array &$sections, $nodeName, Node $node, FakePrinter $p)
{
    $newSections = [];
    foreach ($sections as $section) {
        list($type, $value) = $section;
        if ($type === 'str') {
            $split = explode("__NODE[$nodeName]", $value);
            $c = count($split);
            if ($c != 1) {
                for ($i = 0; $i < $c; $i++) {
                    $s = $split[$i];
                    // Skip over empty strings
                    if ($s !== '') {
                        $newSections[] = ['str', $s];
                    }
                    // If not last section
                    if ($i != $c - 1) {
                        $indent = getIndent($s);
                        if ($node->hasAttribute(AttrName::REDUCED_FROM)) {
                            $reducedFrom = $node->getAttribute(AttrName::REDUCED_FROM);
                            // XXX: Fix indent token
                            $newSections[] = ['reducedNode', fixIdent($indent, $reducedFrom, 'INDENT_TOK')];
                        } else {
                            // This could just be 'str' but don't bother
                            // running replacements on it so make it a different type
                            // XXX: fix indent token
                            $newSections[] = ['node', fixIdent($indent, $p->printNode($node), 'INDENT_TOK')];
                        }
                    }
                }
                continue;
            }
        }
        $newSections[] = $section;
    }
    $sections = $newSections;
}

