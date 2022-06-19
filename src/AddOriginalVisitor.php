<?php

namespace PHPDeobfuscator;

use PhpParser\Node;

class AddOriginalVisitor extends \PhpParser\NodeVisitorAbstract
{
    private $deobfusator;

    public function __construct(Deobfuscator $deobfusator)
    {
        $this->deobfusator = $deobfusator;
    }

    public function enterNode(Node $node)
    {
        if (!($node instanceof Node\Scalar\EncapsedStringPart)) {
            $node->setAttribute('comments', array(new \PhpParser\Comment('/* ' . $this->deobfusator->prettyPrint(array($node), false) . ' */')));
        }
    }

}
