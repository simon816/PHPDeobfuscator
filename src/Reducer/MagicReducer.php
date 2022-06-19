<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node;
use PhpParser\Node\Scalar\MagicConst;
use PHPDeobfuscator\Deobfuscator;
use PHPDeobfuscator\Resolver;
use PHPDeobfuscator\Utils;

class MagicReducer extends AbstractReducer
{
    private $deobf;
    private $resolver;

    public function __construct(Deobfuscator $deobf, Resolver $resolver)
    {
        $this->deobf = $deobf;
        $this->resolver = $resolver;
    }

    private static function nodeOrNull($value)
    {
        return $value === null ? null : Utils::scalarToNode($value);
    }

    public function reduceClass(MagicConst\Class_ $node)
    {
        return self::nodeOrNull($this->resolver->currentClass());
    }

    public function reduceDir(MagicConst\Dir $node)
    {
        return self::nodeOrNull(dirname($this->deobf->getCurrentFilename()));
    }

    public function reduceFile(MagicConst\File $node)
    {
        return self::nodeOrNull($this->deobf->getCurrentFilename());
    }

    public function reduceFunction(MagicConst\Function_ $node)
    {
        return self::nodeOrNull($this->resolver->currentFunction());
    }

    public function reduceLine(MagicConst\Line $node)
    {
        if ($node->hasAttribute('startLine')) {
            return self::nodeOrNull($node->getAttribute('startLine'));
        }
    }

    public function reduceMethod(MagicConst\Method $node)
    {
        return self::nodeOrNull($this->resolver->currentMethod());
    }

    public function reduceNamespace(MagicConst\Namespace_ $node)
    {
        return self::nodeOrNull($this->resolver->currentNamespace());
    }

    public function reduceTrait(MagicConst\Trait_ $node)
    {
        return self::nodeOrNull($this->resolver->currentTrait());
    }
}
