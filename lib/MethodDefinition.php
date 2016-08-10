<?php
namespace David2M\Syringe;

class MethodDefinition
{

    /* @var InstanceDefinition */
    protected $instanceDef;

    /* @var string */
    protected $methodName;

    /* @var array[string]mixed */
    protected $arguments = [];

    /* @var array[string]mixed */
    protected $calls = [];

    public function __construct(InstanceDefinition $instanceDef, $methodName)
    {
        $this->instanceDef = $instanceDef;
        $this->methodName = $methodName;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->instanceDef->getClassName();
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return MethodDefinition
     */
    public function setArgument($name, $value)
    {
        $this->arguments[$name] = $value;
        return $this;
    }

    /**
     * @param array[string]mixed $arguments
     *
     * @return MethodDefinition
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @param array[string]mixed $arguments
     *
     * @return MethodDefinition
     */
    public function addArguments(array $arguments)
    {
        $this->arguments = ($this->hasArguments()) ? array_merge($this->arguments, $arguments) : $arguments;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasArgument($name)
    {
        return isset($this->arguments[$name]);
    }

    /**
     * @return bool
     */
    public function hasArguments()
    {
        return !empty($this->arguments);
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getArgument($name) {
        return ($this->hasArgument($name)) ? $this->arguments[$name] : null;
    }

    /**
     * @return array[string]mixed
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param array[string]mixed $arguments
     *
     * @return MethodDefinition
     */
    public function addCall(array $arguments = [])
    {
        $this->calls[] = $arguments;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasCalls()
    {
        return !empty($this->calls);
    }

    /**
     * @return array[string]mixed
     */
    public function getCalls()
    {
        return $this->calls;
    }
}