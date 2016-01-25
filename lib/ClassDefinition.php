<?php
namespace David2M\Injector;

class ClassDefinition
{
    /* @var string */
    protected $className;

    /* @var InstanceDefinition[] */
    protected $instances = [];

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
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $instance = new InstanceDefinition($this, $name);
        $this->instances[$name] = $instance;

        return $instance;
    }

}