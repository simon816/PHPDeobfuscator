# PHPDeobfuscator

## Overview

This deobfuscator attempts to reverse common obfuscation techniques applied to PHP source code.

It is implemented in PHP with the help of [PHP-Parser](https://github.com/nikic/PHP-Parser).

## Features

- Reduces all constant expressions e.g. `1 + 2` is replaced by `3`
- Safely run whitelisted PHP functions e.g. `base64_decode`
- Deobfuscate `eval` expressions
- Unwrap deeply nested obfuscation
- Filesystem virtualization
- Variable resolver (e.g. `$var1 = 10; $var2 = &$var1; $var2 = 20;` can determine `$var1` equals `20`)
- Rewrite control flow obfuscation

## Installation

PHP Deobfuscator uses [Composer](https://getcomposer.org/) to manage its dependencies. Make sure Composer is installed first.

Run `composer install` in the root of this project to fetch dependencies.

## Usage

### CLI

```
php index.php [-f filename] [-t] [-o]

required arguments:

-f    The obfuscated PHP file

optional arguments:

-t    Dump the output node tree for debugging
-o    Output comments next to each expression with the original code
```

The deobfuscated output is printed to STDOUT.

### Web Server

`index.php` outputs a simple textarea to paste the PHP code into. Deobfuscated code is printed when the form is submitted

## Examples

#### Input
```php
<?php
eval(base64_decode("ZWNobyAnSGVsbG8gV29ybGQnOwo="));
```
#### Output
```php
<?php

eval /* PHPDeobfuscator eval output */ {
    echo "Hello World";
};
```

#### Input
```php
<?
$f = fopen(__FILE__, 'r');
$str = fread($f, 200);
list(,, $payload) = explode('?>', $str);
eval($payload . '');
?>
if ($doBadThing) {
    evil_payload();
}
```

#### Output
```php
<?php

$f = fopen("/var/www/html/input.php", 'r');
$str = "<?\n\$f = fopen(__FILE__, 'r');\n\$str = fread(\$f, 200);\nlist(,, \$payload) = explode('?>', \$str);\neval(\$payload . '');\n?>\nif (\$doBadThing) {\n    evil_payload();\n}\n";
list(, , $payload) = array(0 => "<?\n\$f = fopen(__FILE__, 'r');\n\$str = fread(\$f, 200);\nlist(,, \$payload) = explode('", 1 => "', \$str);\neval(\$payload . '');\n", 2 => "\nif (\$doBadThing) {\n    evil_payload();\n}\n");
eval /* PHPDeobfuscator eval output */ {
    if ($doBadThing) {
        evil_payload();
    }
};
?>
if ($doBadThing) {
    evil_payload();
}
```

#### Input
```php
<?php
$x = 'y';
$$x = 10;
echo $y * 2;
```

#### Output
```php
<?php

$x = 'y';
$y = 10;
echo 20;
```

#### Input
```php
<?php
goto label4;
label1:
func4();
exit;
label2:
func3();
goto label1;
label3:
func2();
goto label2;
label4:
func1();
goto label3;
```

#### Output
```php
<?php

func1();
func2();
func3();
func4();
exit;
```
