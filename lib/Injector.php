<?php
namespace David2M\Injector;

class Injector implements Container
{

    const EX_CLASS_NOT_FOUND = 'The class %s cannot be found.';
    const EX_PARAM_NOT_FOUND = 'No parameter found for %s::%s()::$%s.';
    const EX_UNMAPPED_INTERFACE = 'The interface %s is not mapped to a concrete implementation.';
    const EX_METHOD_NOT_CALLABLE = 'The method %s::%s() is not callable.';
    const EX_CIRCULAR_DEPENDENCY = 'A circular dependency has been found: %s';

    /* @var string[] */
    protected $aliases = [];

    /* @var ClassDefinition[] */
    protected $classDefs = [];

    /**
     * Mappings of interfaces to concrete classes.
     *
     * @var string[]
     */
    protected $mappings = [];

    /* @var callable[] */
    protected $factories = [];

    /**
     * Class names of the objects currently being made.
     *
     * @var string[]
     */
    protected $currentlyMaking = [];

    public function setAlias($alias, $className)
    {
        $this->aliases[$alias] = $className;
        return $this;
    }

    public function addAliases(array $aliases)
    {
        $this->aliases = array_merge($this->aliases, $aliases);
        return $this;
    }

    public function resolveAlias($alias)
    {
        return (isset($this->aliases[$alias])) ? $this->aliases[$alias] : null;
    }

    public function getInstanceDef($className)
    {
        list($className, $instanceName) = $this->extractClassAndInstanceName($className);
        $className = (isset($this->aliases[$className])) ? $this->aliases[$className] : $className;

        if (isset($this->classDefs[$className])) {
            $classDef = $this->classDefs[$className];
        }
        else {
            $classDef = new ClassDefinition($className);
            $this->classDefs[$className] = $classDef;
        }

        return $classDef->getInstance($instanceName);
    }

    public function getMethod($className, $methodName)
    {
        return $this->getInstanceDef($className)->getMethod($methodName);
    }

    public function getConstructor($className)
    {
        return $this->getMethod($className, '__construct');
    }

    public function setMapping($interface, $className)
    {
        $this->mappings[$interface] = $className;
        return $this;
    }

    public function addMappings(array $mappings)
    {
        $this->mappings = array_merge($this->mappings, $mappings);
        return $this;
    }

    public function setFactory($pattern, callable $factory)
    {
        $this->factories[str_replace('\\', '\\\\', $pattern)] = $factory;
        return $this;
    }

    public function addFactories(array $factories)
    {
        foreach ($factories as $pattern => $factory) {
            $this->setFactory($pattern, $factory);
        }

        return $this;
    }

    public function share(array $objects)
    {
        foreach ($objects as $key => $object) {
            $className = get_class($object);
            $this->getInstanceDef($className)->setInstance($object);

            if (is_string($key)) {
                $this->setMapping($key, $className);
            }
        }

        return $this;
    }

    /**
     * @param string $className
     * @param array[string]mixed
     *
     * @return object
     *
     * @throws InjectorException
     */
    public function make($className, array $params = [])
    {
        // Get the instance definition
        $instanceDef = $this->getInstanceDef($className);

        // Is the specified instance defined as a singleton and does an instance of the instance definition already exist?
        // If so, return the instance.
        if ($instanceDef->isSingleton() && $instanceDef->hasInstance()) {
            return $instanceDef->getInstance();
        }

        // If a factory exists for the specified class name then delegate the creation of the object to the factory.
        $className = $instanceDef->getClassName();
        if (($factory = $this->getFactory($className)) !== null) {
            $object = $this->invoke($factory, ['className' => $className, 'instanceName' => $instanceDef->getName()]);
        }
        else {
            if (in_array($className, $this->currentlyMaking)) {
                throw new InjectorException(sprintf(self::EX_CIRCULAR_DEPENDENCY, implode(' -> ', $this->currentlyMaking) . ' -> ' . $className));
            }

            $object = $this->createObject($instanceDef, $params);
        }

        if ($instanceDef->isSingleton()) {
            $instanceDef->setInstance($object);
        }

        foreach ($instanceDef->getMethods() as $methodName => $method) {
            if (!is_callable([$object, $methodName])) {
                throw new InjectorException(sprintf(self::EX_METHOD_NOT_CALLABLE, $className, $methodName));
            }
            foreach ($method->getCalls() as $params) {
                $this->invoke([$object, $methodName], $params);
            }
        }

        return $object;
    }

    /**
     * @param callable $callable
     * @param array[string]mixed $params
     *
     * @return mixed The return type of the method/function being invoked.
     *
     * @throws InjectorException
     */
    public function invoke(callable $callable, array $params = [])
    {
        if (is_string($callable) && strpos($callable, '::') !== false) {
            $callable = explode('::', $callable);
        }

        if (is_array($callable)) {
            return $this->invokeMethod($callable[0], $callable[1], $params);
        }

        return $this->invokeFunction($callable, $params);
    }

    /**
     * @param string $className
     *
     * @return array
     */
    private function extractClassAndInstanceName($className)
    {
        if (substr_count($className, '#') !== 1) {
            return [$className, 'default'];
        }

        return explode('#', $className);
    }

    /**
     * @param string|object $target
     * @param string $methodName
     * @param array[string]mixed $params
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function invokeMethod($target, $methodName, array $params)
    {
        $reflectionMethod = (new \ReflectionClass($target))->getMethod($methodName);
        $parameters = $this->getParameters($reflectionMethod->getParameters(), $params);
        $object = (is_object($target)) ? $target : null;

        return $reflectionMethod->invokeArgs($object, $parameters);
    }

    /**
     * @param string|\Closure $function Name of the function or an instance of \Closure.
     * @param array[string]mixed $params
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function invokeFunction($function, array $params)
    {
        $reflectionFunction = new \ReflectionFunction($function);
        $parameters = $this->getParameters($reflectionFunction->getParameters(), $params);

        return $reflectionFunction->invokeArgs($parameters);
    }

    /**
     * @param string $className
     *
     * @return callable|null
     */
    private function getFactory($className)
    {
        foreach ($this->factories as $pattern => $factory) {
            if (preg_match('/' . $pattern . '/', $className)) {
                return $factory;
            }
        }

        return null;
    }

    /**
     * @param InstanceDefinition $instanceDef
     * @param array[string]mixed
     *
     * @return object
     *
     * @throws InjectorException
     */
    private function createObject(InstanceDefinition $instanceDef, array $params)
    {
        $className = $instanceDef->getClassName();
        try {
            $reflectionClass = new \ReflectionClass($className);
        }
        catch (\ReflectionException $ex) {
            throw new InjectorException(sprintf(self::EX_CLASS_NOT_FOUND, $className));
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return $reflectionClass->newInstanceWithoutConstructor();
        }

        $this->currentlyMaking[] = $className;
        $methodDef = $instanceDef->getMethod('__construct');
        $parameters = $this->getParameters($constructor->getParameters(), $params, $methodDef);

        $i = array_search($className, $this->currentlyMaking);
        array_splice($this->currentlyMaking, $i, 1);

        return $reflectionClass->newInstanceArgs($parameters);
    }

    /**
     * @param \ReflectionParameter[] $reflectionParams
     * @param array[string]mixed $params
     * @param MethodDefinition $methodDef
     *
     * @return mixed[]
     *
     * @throws InjectorException
     */
    private function getParameters(array $reflectionParams, array $params, MethodDefinition $methodDef = null) {

        $parameters = [];
        foreach ($reflectionParams as $reflectionParam) {
            $paramName = $reflectionParam->getName();
            if (isset($params[$paramName])) {
                $parameters[] = $this->resolveParameter($params[$paramName], $reflectionParam);
            }
            else {
                $parameters[] = $this->getParameter($reflectionParam, $methodDef);
            }
        }

        return $parameters;
    }

    /**
     * @param \ReflectionParameter $reflectionParam
     * @param MethodDefinition $methodDef
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function getParameter(\ReflectionParameter $reflectionParam, MethodDefinition $methodDef = null)
    {
        $paramName = $reflectionParam->getName();

        // Has a value been set for this parameter?
        if ($methodDef !== null && $methodDef->hasParam($paramName)) {
            $param = $methodDef->getParam($paramName);
            return $this->resolveParameter($param, $reflectionParam);
        }

        // Is the parameter an object?
        if (($reflectionClass = $reflectionParam->getClass()) !== null) {
            try {
                $className = $reflectionClass->getName();
                if ($reflectionClass->isInterface()) {
                    $className = $this->resolveInterface($className);
                    if ($className === null) {
                        throw new InjectorException(sprintf(self::EX_UNMAPPED_INTERFACE, $reflectionClass->getName()));
                    }
                }

                return $this->make($className);
            }
            catch (InjectorException $ex) {
                // If this parameter has a default value return it.
                if ($reflectionParam->isOptional()) {
                    return $reflectionParam->getDefaultValue();
                }

                throw $ex;
            }
        }

        // No value has been found for this parameter and it cannot be automatically
        // resolved because it's not an object so if it has a default value then return it.
        if ($reflectionParam->isOptional()) {
            return $reflectionParam->getDefaultValue();
        }

        $className = $reflectionParam->getDeclaringClass()->getName();
        $methodName = $reflectionParam->getDeclaringFunction()->getName();

        throw new InjectorException(sprintf(self::EX_PARAM_NOT_FOUND, $className, $methodName, $paramName));
    }

    /**
     * Resolve a supplied parameter to its final value.
     *
     * A supplied parameter may be:
     * 1. A string but the actual required parameter is an object so therefore the string(class name)
     * must be resolved into the actual required object.
     *
     * 2. A callable so therefore must be invoked to get its final value.
     *
     * @param mixed $param
     * @param \ReflectionParameter $reflectionParam
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function resolveParameter($param, \ReflectionParameter $reflectionParam)
    {
        if (is_string($param) && $reflectionParam->getClass() !== null) {
            return $this->make($param);
        }

        return (is_callable($param) && !$reflectionParam->isCallable()) ? $this->invoke($param) : $param;
    }

    /**
     * Resolve an interface to a class name.
     *
     * @param string $interface
     *
     * @return string|null
     */
    private function resolveInterface($interface)
    {
        return (isset($this->mappings[$interface])) ? $this->mappings[$interface] : null;
    }

}