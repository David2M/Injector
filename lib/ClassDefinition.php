<?php
namespace David2M\Injector;

class ClassDefinition
{

    /* @var string */
    protected $className;

    /* @var InstanceDefinition[] */
    protected $instanceDefs = [];

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
     * @param string $name
     *
     * @return InstanceDefinition
     */
    public function getInstance($name = 'default')
    {
        if (isset($this->instanceDefs[$name])) {
            return $this->instanceDefs[$name];
        }

        $instance = new InstanceDefinition($this, $name);
        $this->instanceDefs[$name] = $instance;

        return $instance;
    }

}