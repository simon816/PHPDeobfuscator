<?php

require 'vendor/autoload.php';

$virtualPath = '/var/www/html/testcase.php';

error_reporting(E_ALL);

$testdir = dirname(__FILE__) . '/tests';

$d = opendir($testdir);

while ($testfile = readdir($d)) {
    if ($testfile === '.' || $testfile === '..') {
        continue;
    }
    $f = fopen($testdir . '/' . $testfile, 'r');
    if (!$f) {
        exit(1);
    }
    $tests = array();
    $curTest = array('input' => array(), 'output' => array());
    $lines = null;
    while (!feof($f)) {
        $line = fgets($f);
        if (trim($line) === 'INPUT') {
            if ($lines !== null) {
                $tests[] = $curTest;
                $curTest = array('input' => array(), 'output' => array());
            }
            $lines = &$curTest['input'];
            continue;
        } elseif (trim($line) === 'OUTPUT') {
            $lines = &$curTest['output'];
            continue;
        }
        if ($lines !== null) {
            $lines[] = $line;
        }
    }
    if ($lines !== null) {
        $tests[] = $curTest;
    }
    fclose($f);
    foreach ($tests as $i => $test) {
        $name = $testfile . '/' . ($i + 1);
        $code = "<?php\n" . trim(implode('', $test['input']));
        $deobf = new \PHPDeobfuscator\Deobfuscator();
        $deobf->getFilesystem()->write($virtualPath, $code);
        $deobf->setCurrentFilename($virtualPath);
        try {
            $out = $deobf->prettyPrint($deobf->deobfuscate($deobf->parse($code)));
        } catch (\Exception | \Error $e) {
            echo "Test $name failed:\n";
            echo "Exception: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            continue;
        }
        $expect = "<?php\n\n" . trim(implode('', $test['output']));
        if ($out !== $expect) {
            echo "Test $name failed:\n";
            echo "Expected:\n";
            echo implode("\n", array_map(function($l) { return "[]: $l"; }, explode("\n", $expect)));
            echo "\n";
            echo "Got:\n";
            echo implode("\n", array_map(function($l) { return "[]: $l"; }, explode("\n", $out)));
            echo "\n";
        } else {
            echo "Test $name pass\n";
        }
    }
}

closedir($d);
