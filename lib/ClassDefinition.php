<?php
namespace David2M\Injector;

class ClassDefinition
{
    /* @var string */
    protected $className;

    /* @var bool */
    protected $singleton = true;

    /* @var MethodDefinition[] */
    protected $methods = [];

    /* @var object */
    protected $instance = null;

    /**
     * @param string $className
     */
    public function __construct($className)
    {
        $this->className = $className;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param bool $singleton
     *
     * @return ClassDefinition
     */
    public function singleton($singleton = true)
    {
        $this->singleton = (bool) $singleton;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSingleton()
    {
        return $this->singleton;
    }

    /**
     * @param string $methodName
     *
     * @return MethodDefinition
     */
    public function getMethod($methodName)
    {
        if (isset($this->methods[$methodName])) {
            return $this->methods[$methodName];
        }

        $method = new MethodDefinition($methodName);
        $this->methods[$methodName] = $method;

        return $method;
    }

    /**
     * @return MethodDefinition[]
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * @return MethodDefinition
     */
    public function getConstructor()
    {
        return $this->getMethod('__construct');
    }

    /**
     * @param object $instance
     *
     * @return ClassDefinition
     */
    public function setInstance($instance)
    {
        $this->instance = $instance;
        $this->singleton();

        return $this;
    }

    /**
     * @return object|null
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @return bool
     */
    public function hasInstance()
    {
        return ($this->instance !== null);
    }
}