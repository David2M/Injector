<?php
namespace David2M\Syringe;

interface Container {

    /**
     * @param string $alias
     * @param string $className
     *
     * @return Container
     */
    public function setAlias($alias, $className);

    /**
     * @param array $aliases
     *
     * @return Container
     */
    public function addAliases(array $aliases);

    /**
     * @param string $alias
     *
     * @return string|null
     */
    public function resolveAlias($alias);

    /**
     * @param string $className
     *
     * @return InstanceDefinition
     */
    public function getInstanceDef($className);

    /**
     * @param string $className
     * @param string $methodName
     *
     * @return MethodDefinition
     */
    public function getMethod($className, $methodName);

    /**
     * @param string $className
     *
     * @return MethodDefinition
     */
    public function getConstructor($className);

    /**
     * @param string $interface
     * @param string $className
     *
     * @return Container
     */
    public function setMapping($interface, $className);

    /**
     * @param array[string]string $mappings
     *
     * @return Container
     */
    public function addMappings(array $mappings);

    /**
     * @param string $pattern
     * @param callable $factory
     *
     * @return Container
     */
    public function setFactory($pattern, callable $factory);

    /**
     * @param array[string]callable $factories
     *
     * @return Container
     */
    public function addFactories(array $factories);

    /**
     * @param object[] $objects
     *
     * @return Container
     */
    public function share(array $objects);

    /**
     * @param string $className
     * @param bool $singleton
     *
     * @return Container
     */
    public function singleton($className, $singleton = true);

}