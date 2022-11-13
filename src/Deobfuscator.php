<?php

namespace PHPDeobfuscator;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

class Deobfuscator
{
    private $parser;
    private $prettyPrinter;

    private $firstPass;
    private $secondPass;

    private $fileSystem;
    private $filename;

    public function __construct($dumpOrig = false, $annotateReductions = false)
    {
        $this->parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7);
        $this->prettyPrinter = new ExtendedPrettyPrinter();

        $this->firstPass = new \PhpParser\NodeTraverser;
        $this->secondPass = new \PhpParser\NodeTraverser;

        $this->firstPass->addVisitor(new ControlFlowVisitor());

        if ($dumpOrig) {
            $this->secondPass->addVisitor(new AddOriginalVisitor($this));
        }
        $resolver = new Resolver();
        $this->secondPass->addVisitor($resolver);
        $this->secondPass->addVisitor(new ResolveValueVisitor($resolver));

        $this->fileSystem = new Filesystem(new InMemoryFilesystemAdapter());

        $evalReducer = new Reducer\EvalReducer($this);

        $funcCallReducer = new Reducer\FuncCallReducer();
        $funcCallReducer->addReducer(new Reducer\FuncCallReducer\FunctionSandbox());
        $funcCallReducer->addReducer(new Reducer\FuncCallReducer\FileSystemCall($this->fileSystem));
        $funcCallReducer->addReducer(new Reducer\FuncCallReducer\MiscFunctions($evalReducer, $resolver));
        $funcCallReducer->addReducer(new Reducer\FuncCallReducer\PassThrough());

        $reducer = new ReducerVisitor();
        $reducer->addReducer(new Reducer\BinaryOpReducer());
        $reducer->addReducer($evalReducer);
        $reducer->addReducer($funcCallReducer);
        $reducer->addReducer(new Reducer\MagicReducer($this, $resolver));
        $reducer->addReducer(new Reducer\UnaryReducer($resolver));
        $reducer->addReducer(new Reducer\MiscReducer());

        $this->secondPass->addVisitor($reducer);

        if ($annotateReductions) {
            $this->metaVisitor = new MetadataVisitor($this);
            $this->secondPass->addVisitor($this->metaVisitor);
        } else {
            $this->metaVisitor = null;
        }
    }

    public function getFilesystem()
    {
        return $this->fileSystem;
    }

    public function getCurrentFilename()
    {
        return $this->filename;
    }

    public function setCurrentFilename($filename)
    {
        $this->filename = $filename;
    }

    public function parse($phpCode)
    {
        $phpCode = str_ireplace('<?=', '<?php echo ', $phpCode);
        $phpCode = str_ireplace('<?', '<?php ', $phpCode);
        $phpCode = str_ireplace('<?php php', '<?php', $phpCode);
        return $this->parser->parse($phpCode);
    }

    public function prettyPrint(array $tree, $file = true)
    {
        if ($file) {
            return $this->prettyPrinter->prettyPrintFile($tree);
        } else {
            return $this->prettyPrinter->prettyPrint($tree);
        }
    }

    public function printFileReductions(array $stmts)
    {
        if ($this->metaVisitor === null) {
            throw new \LogicException("annotateReductions was not set on construction");
        }
        return $this->metaVisitor->printFileReductions($stmts);
    }

    public function deobfuscate(array $tree)
    {
        $tree = $this->firstPass->traverse($tree);
        $tree = $this->secondPass->traverse($tree);
        return $tree;
    }

}
