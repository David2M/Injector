<?php
namespace David2M\Syringe;

class InstanceDefinition
{

    /* @var ClassDefinition */
    protected $class;

    /* @var string */
    protected $name;

    /* @var bool */
    protected $singleton = true;

    /* @var MethodDefinition[] */
    protected $methodDefs = [];

    /* @var object */
    protected $instance = null;

    public function __construct(ClassDefinition $class, $name = 'default')
    {
        $this->class = $class;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->class->getClassName();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
        if (isset($this->methodDefs[$methodName])) {
            return $this->methodDefs[$methodName];
        }

        $method = new MethodDefinition($this, $methodName);
        $this->methodDefs[$methodName] = $method;

        return $method;
    }

    /**
     * @return MethodDefinition[]
     */
    public function getMethods()
    {
        return $this->methodDefs;
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