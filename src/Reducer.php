<?php

use PhpParser\Node;

interface Reducer
{
    public function getNodeClasses();

    public function reduce(Node $node);

}
