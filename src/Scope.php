<?php

namespace PHPDeobfuscator;

class Scope
{
    private $name;
    private $superGlobals;
    private $parentScope;
    private $variables = array();

    public function __construct($name, Scope $parent = null)
    {
        $this->name = $name;
        $this->superGlobals = $parent ? $parent->getSuperGlobals() : array();
        $this->parentScope = $parent;
    }

    public function getParent()
    {
        return $this->parentScope;
    }

    public function getSuperGlobals()
    {
        return $this->superGlobals;
    }

    public function setSuperGlobal($name, ValRef $val)
    {
        if ($this->parentScope !== null) {
            throw new \LogicException("Must be global scope to set a super global");
        }
        $this->superGlobals[$name] = $val;
    }

    public function setVariable($name, ValRef $val)
    {
        if (isset($this->superGlobals[$name])) {
            // Superglobals can be reassigned. They simply take the value of whatever is given
            $this->superGlobals[$name] = $val;
        }
        $this->variables[$name] = $val;
    }

    public function getVariable($name)
    {
        if (isset($this->superGlobals[$name])) {
            return $this->superGlobals[$name];
        }
        if (isset($this->variables[$name])) {
            return $this->variables[$name];
        }
        return null;
    }

    public function unsetVariable($name)
    {
        unset($this->variables[$name]);
    }

    public function __clone()
    {
        if ($this->parentScope) {
            $this->parentScope = clone $this->parentScope;
        }
        foreach ($this->variables as $name => &$val) {
            $val = clone $val;
        }
        foreach ($this->superGlobals as $name => &$val) {
            $val = clone $val;
        }
    }

    public function &getVariables()
    {
        return $this->variables;
    }

    public function __toString()
    {
        return "{$this->parentScope}.{$this->name}";
    }

}
