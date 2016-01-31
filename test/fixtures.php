<?php
namespace
{
    class NoConstructor
    {

    }

    class EmptyConstructor
    {
        public function __construct()
        {

        }
    }

    class ConstructorOneParam
    {
        private $param;

        public function __construct($param)
        {
            $this->param = $param;
        }
    }

    class ConstructorOptionalParam
    {
        private $param;

        public function __construct($param = '')
        {
            $this->param = $param;
        }
    }

    class User
    {
        private $name;

        public function __construct($name = null)
        {
            $this->name = $name;
        }

        public function setName($name) { $this->name = $name; }

        public function getName() { return $this->name; }
    }

    class Product
    {
        public static function getPrice()
        {
            return 19.99;
        }
    }

    interface DatabaseAdapterInterface
    {
    }

    class PdoAdapter implements DatabaseAdapterInterface
    {

    }

    class UserMapper
    {
        private $adapter;

        public function __construct(DatabaseAdapterInterface $adapter)
        {
            $this->adapter = $adapter;
        }

    }

    class Counter
    {
        private $number;

        public function __construct($number = 0)
        {
            $this->number = $number;
        }

        public function increment()
        {
            $this->number++;
        }

        public function decrement()
        {
            $this->number--;
        }
    }

    class ServiceFactory
    {
        public function create($className)
        {

        }

        public function build($instanceName)
        {

        }
    }

    function add($a, $b)
    {
        return $a + $b;
    }

    class CircularDependencyOne
    {
        public function __construct(CircularDependencyTwo $dependency)
        {
        }
    }

    class CircularDependencyTwo
    {
        public function __construct(CircularDependencyThree $dependency)
        {
        }
    }

    class CircularDependencyThree
    {
        public function __construct(CircularDependencyOne $dependency)
        {
        }
    }

    abstract class Animal
    {

    }

    class Dog extends Animal
    {

    }

    class A {
        public function __construct(Animal $animal)
        {
        }
    }

}

namespace Service
{
    class AuthService
    {

    }
}
