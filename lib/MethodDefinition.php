<?php
namespace David2M\Injector;

class MethodDefinition
{

    /* @var InstanceDefinition */
    protected $instanceDef;

    /* @var string */
    protected $methodName;

    /* @var array[string]mixed */
    protected $params = [];

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
    public function setParam($name, $value)
    {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * @param array[string]mixed $params
     *
     * @return MethodDefinition
     */
    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param array[string]mixed $params
     *
     * @return MethodDefinition
     */
    public function addParams(array $params)
    {
        $this->params = ($this->hasParams()) ? array_merge($this->params, $params) : $params;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasParam($name)
    {
        return isset($this->params[$name]);
    }

    /**
     * @return bool
     */
    public function hasParams()
    {
        return !empty($this->params);
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getParam($name) {
        return ($this->hasParam($name)) ? $this->params[$name] : null;
    }

    /**
     * @return array[string]mixed
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array[string]mixed $params
     *
     * @return MethodDefinition
     */
    public function addCall(array $params = [])
    {
        $this->calls[] = $params;
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