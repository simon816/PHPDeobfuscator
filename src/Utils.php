<?php

namespace PHPDeobfuscator;

use League\Flysystem\Filesystem;
use League\Flysystem\PathTraversalDetected;
use PhpParser\Node;
use PhpParser\Node\Scalar;

use PHPDeobfuscator\ValRef\ArrayVal;
use PHPDeobfuscator\ValRef\ScalarValue;

class Utils
{
    public static function scalarToNode($value, $attrs = array())
    {
        if (!is_array($value)) { // Do this for arrays later
            $attrs[AttrName::VALUE] = new ScalarValue($value);
        }
        if (is_int($value)) {
            return new Scalar\LNumber($value, $attrs);
        }
        if (is_float($value)) {
            return new Scalar\DNumber($value, $attrs);
        }
        if (is_string($value)) {
            return new Scalar\String_($value, array_merge(array('kind' => Scalar\String_::KIND_DOUBLE_QUOTED), $attrs));
        }
        if (is_null($value)) {
            return new Node\Expr\ConstFetch(new Node\Name('null'), $attrs);
        }
        if (is_bool($value)) {
            return new Node\Expr\ConstFetch(new Node\Name($value ? 'true' : 'false'), $attrs);
        }
        if (is_array($value)) {
            $items = array();
            $valArray = array();
            foreach ($value as $key => $val) {
                $valNode = self::scalarToNode($val);
                $keyNode = self::scalarToNode($key);
                $items[] = new Node\Expr\ArrayItem($valNode, $keyNode);
                $valArray[self::getValue($keyNode)] = self::getValueRef($valNode);
            }
            $attrs[AttrName::VALUE] = new ArrayVal($valArray);
            return new Node\Expr\Array_($items, $attrs);
        }
        throw new \Exception("Unknown value type");
    }

    public static function getValueRef(Node $node)
    {
        $valRef = $node->getAttribute(AttrName::VALUE);
        if ($valRef === null) {
            throw new Exceptions\UnknownValueException("Cannot determine value of node");
        }
        return $valRef;
    }

    public static function getValue(Node $node)
    {
        return self::getValueRef($node)->getValue();
    }

    public static function refsToValues(array $refs)
    {
        $values = array();
        foreach ($refs as $ref) {
            $values[] = $ref->getValue();
        }
        return $values;
    }

    public static function safeFileExists(Filesystem $fileSystem, $path)
    {
        try {
            return $fileSystem->fileExists($path);
        } catch (PathTraversalDetected $e) {
            return false;
        }
    }
}

