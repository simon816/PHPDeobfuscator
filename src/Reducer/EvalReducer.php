<?php

namespace PHPDeobfuscator\Reducer;

use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;

use PHPDeobfuscator\Deobfuscator;
use PHPDeobfuscator\EvalBlock;
use PHPDeobfuscator\Utils;

class EvalReducer extends AbstractReducer
{
    private $deobfuscator;
    private $outputAsEvalStr;

    public function __construct(Deobfuscator $deobfuscator, $outputAsEvalStr = false)
    {
        $this->deobfuscator = $deobfuscator;
        $this->outputAsEvalStr = $outputAsEvalStr;
    }

    public function reduceEval(Expr\Eval_ $node)
    {
        $expr = Utils::getValue($node->expr);
        if (!is_string($expr)) {
            return null;
        }
        $newExpr = $this->tryRunEval($expr);
        return $newExpr;
    }

    public function reduceInclude(Expr\Include_ $node)
    {
        // TODO $node->type
        // TODO should this replace the include with an eval or should it just export the symbols?
        // need to handle recursive includes
        // One of Include_::(TYPE_INCLUDE, TYPE_INCLUDE_ONCE, TYPE_REQUIRE, TYPE_REQUIRE_ONCE)
        $file = Utils::getValue($node->expr);
        $fileSystem = $this->deobfuscator->getFilesystem();
        if (!Utils::safeFileExists($fileSystem, $file)) {
            return;
        }
        $code = $fileSystem->read($file);
        return $this->tryRunEval($code);
    }

    private function tryRunEval($code)
    {
        try {
            return $this->runEval($code);
        } catch (\Exception $e) {
            print "Error traversing". PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            echo $e->getTraceAsString() . PHP_EOL;
            return null;
        }
    }

    public function runEval($code)
    {
        $origTree = $this->parseCode($code);
        $tree = $this->deobfTree($origTree);
        // If it's just a single expression, return directly
        // XXX this is not semantically correct because eval does not return
        // anything by default
        if (count($tree) === 1 && $tree[0] instanceof Stmt\Expression) {
            return $tree[0]->expr;
        }
        if (count($tree) === 1 && $tree[0] instanceof Stmt\Return_) {
            return $tree[0]->expr;
        }
        if ($this->outputAsEvalStr) {
            $expr = new Expr\Eval_(new String_($this->deobfuscator->prettyPrint($tree, false), array(
                'kind' => String_::KIND_NOWDOC, 'docLabel' => 'EVAL' . rand()
            ))) ;
        } else {
            $expr = new EvalBlock($tree, $origTree);
        }
        return $expr;
    }

    private function parseCode($code)
    {
        /* Convert ?> into <? */
        if (substr($code, 0, 2) == '?>' && $code[2] != '<') {
            $code[0] = '<';
            $code[1] = '?';
        }
        $prefix = substr($code, 0, 2) == '<?' ? '' : '<?php ';
        return $this->deobfuscator->parse("{$prefix}{$code}");
    }

    private function deobfTree($tree)
    {
        return $this->deobfuscator->deobfuscate($tree);
    }

    public function runEvalTree($code)
    {
        return $this->deobfTree($this->parseCode($code));
    }

}
