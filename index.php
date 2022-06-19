<?php

require 'vendor/autoload.php';

ini_set('xdebug.var_display_max_depth', -1);
ini_set('memory_limit', '512M');
ini_set('xdebug.max_nesting_level', 1000);

function deobfuscate($code, $filename, $dumpOrig) {
    $deobf = new \PHPDeobfuscator\Deobfuscator($dumpOrig);
    $cwd = '/var/www/html/';
    $virtualPath = $cwd . basename($filename);
    $deobf->getFilesystem()->write($virtualPath, $code);
    $deobf->setCurrentFilename($virtualPath);
    $tree = $deobf->parse($code);
    $tree = $deobf->deobfuscate($tree);
    $newCode = $deobf->prettyPrint($tree);
    return array($tree, $newCode);
}

$nodeDumper = new PhpParser\NodeDumper();
if (php_sapi_name() == 'cli') {
    $opts = getopt('tof:');
    if (!isset($opts['f'])) {
        die("Missing required parameter -f\n");
    }
    $filename = $opts['f'];
    $orig = isset($opts['o']);
    list($tree, $code) = deobfuscate(file_get_contents($filename), $filename, $orig);
    echo $code, "\n";
    if (isset($opts['t'])) {
        echo $nodeDumper->dump($tree), "\n";
    }
} else {
    if (isset($_POST['phpdata'])) {
        $orig = array_key_exists('orig', $_GET);
        $php = $_POST['phpdata'];
        header('Content-Type: text/plain');
        list($tree, $code) = deobfuscate($php, 'input.php', $orig);
        echo $code, "\n\n";
        if (array_key_exists('tree', $_GET)) {
            echo '======== Tree =======', "\n";
            echo $nodeDumper->dump($tree), "\n";
        }
    } else {
        echo <<<HTML
<html>
<body>
<form action="index.php" method="POST">
<textarea name="phpdata" rows=40 cols=180></textarea>
<br>
<input type="submit" value="Deobfuscate">
</form>
</body>
</html>
HTML;
    }
}
