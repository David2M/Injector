<?php
namespace David2M\Syringe;

class Injector implements Container
{

    const EX_CLASS_NOT_FOUND = 'The class %s cannot be found.';
    const EX_ARGUMENT_NOT_FOUND = 'No argument found for %s::%s()::$%s.';
    const EX_UNMAPPED_INTERFACE = 'The interface %s is not mapped to a concrete implementation.';
    const EX_UNMAPPED_ABSTRACT_CLASS = 'The abstract class %s is not mapped to a concrete implementation.';
    const EX_METHOD_NOT_CALLABLE = 'The method %s::%s() is not callable.';
    const EX_CIRCULAR_DEPENDENCY = 'A circular dependency has been found: %s';

    /* @var ClassDefinition[] */
    protected $classDefs = [];

    /**
     * Mappings of interfaces and abstract classes to concrete implementations.
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

    public function getInstanceDef($className)
    {
        list($className, $instanceName) = $this->extractClassAndInstanceName($className);

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
            if (is_string($key)) {
                $className .= '#' . $key;
            }
            $this->getInstanceDef($className)->setInstance($object);
        }

        return $this;
    }

    public function singleton($className, $singleton = true)
    {
        $this->getInstanceDef($className)->singleton($singleton);
        return $this;
    }

    /**
     * @param string $className
     * @param array[string]mixed $arguments
     *
     * @return object
     *
     * @throws InjectorException
     */
    public function make($className, array $arguments = [])
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
            $object = $this->createObject($instanceDef, $arguments);
        }

        if ($instanceDef->isSingleton()) {
            $instanceDef->setInstance($object);
        }

        foreach ($instanceDef->getMethods() as $methodName => $method) {
            if (!is_callable([$object, $methodName])) {
                throw new InjectorException(sprintf(self::EX_METHOD_NOT_CALLABLE, $className, $methodName));
            }
            foreach ($method->getCalls() as $arguments) {
                $this->invoke([$object, $methodName], $arguments);
            }
        }

        return $object;
    }

    /**
     * @param callable $callable
     * @param array[string]mixed $arguments
     *
     * @return mixed The return type of the method/function being invoked.
     *
     * @throws InjectorException
     */
    public function invoke(callable $callable, array $arguments = [])
    {
        if (is_string($callable) && strpos($callable, '::') !== false) {
            $callable = explode('::', $callable);
        }

        if (is_array($callable)) {
            return $this->invokeMethod($callable[0], $callable[1], $arguments);
        }

        return $this->invokeFunction($callable, $arguments);
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
     * @param array[string]mixed $arguments
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function invokeMethod($target, $methodName, array $arguments)
    {
        $reflectionMethod = (new \ReflectionClass($target))->getMethod($methodName);
        $arguments = $this->getArguments($reflectionMethod->getParameters(), $arguments);
        $object = (is_object($target)) ? $target : null;

        return $reflectionMethod->invokeArgs($object, $arguments);
    }

    /**
     * @param string|\Closure $function Name of the function or an instance of \Closure.
     * @param array[string]mixed $arguments
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function invokeFunction($function, array $arguments)
    {
        $reflectionFunction = new \ReflectionFunction($function);
        $arguments = $this->getArguments($reflectionFunction->getParameters(), $arguments);

        return $reflectionFunction->invokeArgs($arguments);
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
     * @param array[string]mixed $arguments
     *
     * @return object
     *
     * @throws InjectorException
     */
    private function createObject(InstanceDefinition $instanceDef, array $arguments)
    {
        $className = $instanceDef->getClassName();

        if (in_array($className, $this->currentlyMaking)) {
            throw new InjectorException(sprintf(self::EX_CIRCULAR_DEPENDENCY, implode(' -> ', $this->currentlyMaking) . ' -> ' . $className));
        }

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
        $arguments = $this->getArguments($constructor->getParameters(), $arguments, $methodDef);

        array_pop($this->currentlyMaking);

        return $reflectionClass->newInstanceArgs($arguments);
    }

    /**
     * @param \ReflectionParameter[] $parameters
     * @param array[string]mixed $args
     * @param MethodDefinition $methodDef
     *
     * @return mixed[]
     *
     * @throws InjectorException
     */
    private function getArguments(array $parameters, array $args, MethodDefinition $methodDef = null) {

        $arguments = [];
        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            if (isset($args[$paramName])) {
                $arguments[] = $this->resolveArgument($args[$paramName], $parameter);
            }
            else {
                $arguments[] = $this->getArgument($parameter, $methodDef);
            }
        }

        return $arguments;
    }

    /**
     * @param \ReflectionParameter $parameter
     * @param MethodDefinition $methodDef
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function getArgument(\ReflectionParameter $parameter, MethodDefinition $methodDef = null)
    {
        $paramName = $parameter->getName();

        // Has a value been set for this parameter?
        if ($methodDef !== null && $methodDef->hasArgument($paramName)) {
            $argument = $methodDef->getArgument($paramName);
            return $this->resolveArgument($argument, $parameter);
        }

        // Is the argument an object?
        if (($reflectionClass = $parameter->getClass()) !== null) {
            try {
                $className = $reflectionClass->getName();
                if ($reflectionClass->isInterface() || $reflectionClass->isAbstract()) {
                    $className = (isset($this->mappings[$className])) ? $this->mappings[$className] : null;
                    if ($className !== null) {
                        return $this->make($className);
                    }
                    if ($reflectionClass->isInterface()) {
                        throw new InjectorException(sprintf(self::EX_UNMAPPED_INTERFACE, $reflectionClass->getName()));
                    }
                    if ($reflectionClass->isAbstract()) {
                        throw new InjectorException(sprintf(self::EX_UNMAPPED_ABSTRACT_CLASS, $reflectionClass->getName()));
                    }
                }

                return $this->make($className);
            }
            catch (InjectorException $ex) {
                // If this parameter has a default value return it.
                if ($parameter->isOptional()) {
                    return $parameter->getDefaultValue();
                }

                throw $ex;
            }
        }

        // No argument value has been found for this parameter and it cannot be automatically
        // resolved because it's not an object so if it has a default value then return it.
        if ($parameter->isOptional()) {
            return $parameter->getDefaultValue();
        }

        $className = $parameter->getDeclaringClass()->getName();
        $methodName = $parameter->getDeclaringFunction()->getName();

        throw new InjectorException(sprintf(self::EX_ARGUMENT_NOT_FOUND, $className, $methodName, $paramName));
    }

    /**
     * Resolve a supplied argument to its final value.
     *
     * A supplied argument may be:
     * 1. A string but the actual required argument is an object so therefore the string(class name)
     * must be resolved into the actual required object.
     *
     * 2. A callable so therefore must be invoked to get its final value.
     *
     * @param mixed $argument
     * @param \ReflectionParameter $parameter
     *
     * @return mixed
     *
     * @throws InjectorException
     */
    private function resolveArgument($argument, \ReflectionParameter $parameter)
    {
        if (is_string($argument) && $parameter->getClass() !== null) {
            return $this->make($argument);
        }

        return (is_callable($argument) && !$parameter->isCallable()) ? $this->invoke($argument) : $argument;
    }

}