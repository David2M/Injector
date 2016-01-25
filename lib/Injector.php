<?php
namespace David2M\Injector;

class Injector implements Container
{

    const EX_CLASS_NOT_FOUND = 'The class %s cannot be found.';
    const EX_PARAM_NOT_FOUND = 'No parameter found for %s::%s()::$%s.';
    const EX_UNMAPPED_INTERFACE = 'The interface %s is not mapped to a concrete implementation.';
    const EX_METHOD_NOT_CALLABLE = 'The method %s::%s() is not callable.';

    /* @var string[] */
    protected $aliases = [];

    /* @var ClassDefinition[] */
    protected $classes = [];

    /**
     * Mappings of interfaces to concrete classes.
     *
     * @var string[]
     */
    protected $mappings = [];

    /* @var callable[] */
    protected $factories = [];

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

    public function getClass($className)
    {
        $className = (isset($this->aliases[$className])) ? $this->aliases[$className] : $className;

        if (isset($this->classes[$className])) {
            return $this->classes[$className];
        }

        $class = new ClassDefinition($className);
        $this->classes[$className] = $class;

        return $class;
    }

    public function getMethod($className, $methodName)
    {
        return $this->getClass($className)->getMethod($methodName);
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
            $this->getClass($className)->setInstance($object);

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
        // Get the class definition
        $class = $this->getClass($className);

        // Is the specified class defined as a singleton and does an instance of the class already exist?
        // If so, return the instance.
        if ($class->isSingleton() && $class->hasInstance()) {
            return $class->getInstance();
        }

        // If a factory exists for the specified class name then delegate the creation of the object to the factory.
        $className = $class->getClassName();
        if (($factory = $this->getFactory($className)) !== null) {
            $object = $this->invoke($factory, ['className' => $className]);
        }
        else {
            $object = $this->createObject($className, $params);
        }

        if ($class->isSingleton()) {
            $class->setInstance($object);
        }

        foreach ($class->getMethods() as $methodName => $method) {
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
     * @param string $className
     * @param array[string]mixed
     *
     * @return object
     *
     * @throws InjectorException
     */
    private function createObject($className, array $params)
    {
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
        $parameters = $this->getParameters($constructor->getParameters(), $params);

        return $reflectionClass->newInstanceArgs($parameters);
    }

    /**
     * @param \ReflectionParameter[] $reflectionParams
     * @param array[string]mixed $params
     *
     * @return mixed[]
     *
     * @throws InjectorException
     */
    private function getParameters(array $reflectionParams, array $params) {

        $parameters = [];
        foreach ($reflectionParams as $reflectionParam) {
            $paramName = $reflectionParam->getName();
            if (isset($params[$paramName])) {
                $parameters[] = $this->resolveParameter($params[$paramName], $reflectionParam);
            }
            else {
                $parameters[] = $this->getParameter($reflectionParam);
            }
        }

        return $parameters;
    }

    /**
     * @param \ReflectionParameter $reflectionParam
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function getParameter(\ReflectionParameter $reflectionParam)
    {
        $className = $reflectionParam->getDeclaringClass()->getName();
        $methodName = $reflectionParam->getDeclaringFunction()->getName();
        $paramName = $reflectionParam->getName();

        $method = $this->getClass($className)->getMethod($methodName);

        // Has a value been set for this parameter?
        if ($method->hasParam($paramName)) {
            $param = $method->getParam($paramName);
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

        throw new InjectorException(sprintf(self::EX_PARAM_NOT_FOUND, $className, $methodName, $paramName));
    }

    /**
     * Resolve a supplied parameter to its final value.
     * A supplied parameter may be a callable so therefore must be called to get its
     * final value.
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