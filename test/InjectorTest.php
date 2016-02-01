<?php
use David2M\Syringe\Injector;

require('fixtures.php');

class InjectorTest extends PHPUnit_Framework_TestCase
{
    /* @var Injector */
    private $injector;

    public function setUp()
    {
        $this->injector = new Injector();
    }

    public function testInvokingGetInstanceDefMoreThanOnceWithSameClassNameReturnsSameInstance()
    {
        $class = 'My\Class';

        $obj1 = $this->injector->getInstanceDef($class);
        $obj2 = $this->injector->getInstanceDef($class);

        $this->assertTrue($obj1 === $obj2);
    }

    public function testInvokingGetMethodMoreThanOnceWithSameClassNameAndMethodNameReturnsSameInstance()
    {
        $class = 'My\Class';
        $method = 'myMethod';

        $obj1 = $this->injector->getMethod($class, $method);
        $obj2 = $this->injector->getMethod($class, $method);

        $this->assertTrue($obj1 === $obj2);
    }

    public function testInjectorExceptionThrownWhenClassNotFound()
    {
        $class = 'My\Fake\Class';
        $message = sprintf(Injector::EX_CLASS_NOT_FOUND, $class);

        $this->setExpectedException('David2M\Syringe\InjectorException', $message);
        $this->injector->make($class);
    }

    public function testMakeObjectNoConstructor()
    {
        $obj = $this->injector->make('NoConstructor');
        $this->assertInstanceOf('NoConstructor', $obj);
    }

    public function testMakeObjectEmptyConstructor()
    {
        $obj = $this->injector->make('EmptyConstructor');
        $this->assertInstanceOf('EmptyConstructor', $obj);
    }

    public function testSetConstructorParamBeforeMakingObject()
    {
        $value = 'Test Value';

        $this->injector
            ->getConstructor('ConstructorOneParam')
            ->setParam('param', $value);

        $obj = $this->injector->make('ConstructorOneParam');

        $this->assertAttributeSame($value, 'param', $obj);
    }

    public function testSetConstructorParamWhenMakingObject()
    {
        $value = 'Test Value';
        $obj = $this->injector->make('ConstructorOneParam', ['param' => $value]);

        $this->assertAttributeSame($value, 'param', $obj);
    }

    public function testConstructorOptionalParamDefaultValueUsedBecauseNoValueWasSupplied()
    {
        $obj = $this->injector->make('ConstructorOptionalParam');
        $this->assertAttributeSame('', 'param', $obj);
    }

    public function testConstructorOptionalParamOverwrite()
    {
        $value = 'My Value';
        $this->injector
            ->getConstructor('ConstructorOptionalParam')
            ->setParam('param', $value);

        $obj = $this->injector->make('ConstructorOptionalParam');

        $this->assertAttributeSame($value, 'param', $obj);
    }

    public function testStringParamSetForObjectParamGetsResolvedToAnObject()
    {
        $className = 'UserMapper';
        $this->injector
            ->getConstructor($className)
            ->setParam('adapter', 'PdoAdapter');

        $obj = $this->injector->make($className);

        $this->assertInstanceOf($className, $obj);
    }

    public function testMakeTwoDifferentInstancesOfTheSameClass()
    {
        $className = 'User';
        $classAndInstanceNameOne = $className . '#david';
        $classAndInstanceNameTwo = $className . '#bob';

        $david = $this->injector->make($classAndInstanceNameOne);
        $bob = $this->injector->make($classAndInstanceNameTwo);

        $this->assertFalse($david === $bob);
    }

    public function testMakeTwoDifferentInstancesOfTheSameClassWithDifferentParamsSetOnTheConstructor()
    {
        $className = 'User';
        $classAndInstanceNameOne = $className . '#david';
        $classAndInstanceNameTwo = $className . '#bob';

        $this->injector
            ->getConstructor($classAndInstanceNameOne)
            ->setParam('name', 'David');

        $this->injector
            ->getConstructor($classAndInstanceNameTwo)
            ->setParam('name', 'Bob');

        $david = $this->injector->make($classAndInstanceNameOne);
        $bob = $this->injector->make($classAndInstanceNameTwo);

        $this->assertFalse($david->getName() === $bob->getName());
    }

    public function testObjectsAreTheSameInstance()
    {
        $obj1 = $this->injector->make('User');
        $obj2 = $this->injector->make('User');

        $this->assertTrue($obj1 === $obj2);
    }

    public function testObjectsAreTheSameInstanceWhenInstanceIsExplicitlyDefinedAsSingleton()
    {
        $this->injector->singleton('User', true);

        $obj1 = $this->injector->make('User');
        $obj2 = $this->injector->make('User');

        $this->assertTrue($obj1 === $obj2);
    }

    public function testObjectsAreNotTheSameInstanceWhenInstanceIsDefinedAsNotASingleton()
    {
        $this->injector->singleton('User', false);

        $obj1 = $this->injector->make('User');
        $obj2 = $this->injector->make('User');

        $this->assertFalse($obj1 === $obj2);
    }

    public function testDelegateObjectCreationToFactory()
    {
        $this->injector->setFactory('^User$', function () {
            return new User('John');
        });

        $obj = $this->injector->make('User');

        $this->assertEquals(new User('John'), $obj);
    }

    public function testInvokeFunction()
    {
        $a = 5;
        $b = 6;

        $actual = $this->injector->invoke('add', [
            'a' => $a,
            'b' => $b
        ]);

        $this->assertSame($a + $b, $actual);
    }

    public function testInvokeClosureWithNoParams()
    {
        $actual = $this->injector->invoke(function () {
            return 100;
        });

        $this->assertSame(100, $actual);
    }

    public function testInvokeClosureWithoutSupplyingValueForParam()
    {
        $this->setExpectedException('David2M\Syringe\InjectorException', sprintf(Injector::EX_PARAM_NOT_FOUND, 'InjectorTest', '{closure}', 'param'));

        $this->injector->invoke(function ($param) {
        });
    }

    public function testInvokeClosureWithParamSuppliedAtInvokeTime()
    {
        $expected = 100;
        $actual = $this->injector->invoke(function ($param) {
            return $param;
        }, ['param' => $expected]);

        $this->assertSame($expected, $actual);
    }

    public function testInvokeStaticMethodUsingStringDeclaration()
    {
        $price = $this->injector->invoke('Product::getPrice');
        $this->assertSame($price, 19.99);
    }

    public function testInvokeStaticMethodUsingArrayDeclaration()
    {
        $price = $this->injector->invoke(['Product', 'getPrice']);
        $this->assertSame($price, 19.99);
    }

    public function testInvokeMethodWithNoParams()
    {
        $name = 'David';
        $user = $this->injector->make('User');
        $user->setName($name);

        $actual = $this->injector->invoke([$user, 'getName']);

        $this->assertSame($name, $actual);
    }

    public function testInterfaceNotMappedToConcreteImplementation()
    {
        $this->setExpectedException('David2M\Syringe\InjectorException', sprintf(Injector::EX_UNMAPPED_INTERFACE, 'DatabaseAdapterInterface'));
        $this->injector->make('UserMapper');
    }

    public function testAbstractClassNotMappedToConcreteImplementation()
    {
        $this->setExpectedException('David2M\Syringe\InjectorException', sprintf(Injector::EX_UNMAPPED_ABSTRACT_CLASS, 'Animal'));
        $this->injector->make('A');
    }

    public function testMapInterfaceToConcreteImplementation()
    {
        $this->injector->setMapping('DatabaseAdapterInterface', 'PdoAdapter');
        $mapper = $this->injector->make('UserMapper');

        $this->assertAttributeInstanceOf('PdoAdapter', 'adapter', $mapper);
    }

    public function testMapAbstractClassToConcreteImplementation()
    {
        $this->injector->setMapping('Animal', 'Dog');
        $a = $this->injector->make('A');

        $this->assertAttributeInstanceOf('Dog', 'animal', $a);
    }

    public function testIfClosureParamIsCalledBeforePassingItIntoTheConstructor()
    {
        $this->injector
            ->getConstructor('User')
            ->setParam('name', function() {return 'David'; });

        $user = $this->injector->make('User');

        $this->assertAttributeSame('David', 'name', $user);
    }

    public function testShareAnObject()
    {
        $user = new User('John');
        $this->injector->share([$user]);

        $obj = $this->injector->make('User');

        $this->assertTrue($user === $obj);
    }

    public function testShareAnObjectWhileSettingTheInstanceName()
    {
        $adapter = new PdoAdapter();
        $this->injector->share(['remote' => $adapter]);

        $obj = $this->injector->make('PdoAdapter#remote');

        $this->assertTrue($adapter === $obj);
    }

    public function testMethodGetsCalledOnceAfterMakingObject()
    {
        $this->injector
            ->getMethod('Counter', 'increment')
            ->addCall();

        $obj = $this->injector->make('Counter');

        $this->assertAttributeSame(1, 'number', $obj);
    }

    public function testMethodGetsCalledTwiceAfterObjectIsMade()
    {
        $this->injector
            ->getMethod('Counter', 'increment')
            ->addCall()
            ->addCall();

        $obj = $this->injector->make('Counter');

        $this->assertAttributeSame(2, 'number', $obj);
    }

    public function testTwoMethodsGetCalledOnceAfterObjectIsMade()
    {
        $this->injector
            ->getMethod('Counter', 'increment')
            ->addCall();

        $this->injector
            ->getMethod('Counter', 'decrement')
            ->addCall();

        $obj = $this->injector->make('Counter');

        $this->assertAttributeSame(0, 'number', $obj);
    }

    public function testAddCallToMethodWithoutSupplyingRequiredParam()
    {
        $className = 'User';
        $methodName = 'setName';
        $paramName = 'name';

        $this->setExpectedException('David2M\Syringe\InjectorException', sprintf(Injector::EX_PARAM_NOT_FOUND, $className, $methodName, $paramName));

        $this->injector
            ->getMethod($className, $methodName)
            ->addCall();

        $this->injector->make($className);
    }

    public function testAddCallToMethodWhichDoesNotExist()
    {
        $className = 'User';
        $methodName = 'setAge';

        $this->setExpectedException('David2M\Syringe\InjectorException', sprintf(Injector::EX_METHOD_NOT_CALLABLE, $className, $methodName));

        $this->injector
            ->getMethod($className, $methodName)
            ->addCall();

        $this->injector->make($className);
    }

    public function testIfFactoryGetsPassedTheFullNameOfTheClassIfItHasAParamCalledClassName()
    {
        $className = 'Service\AuthService';

        $mockServiceFactory = $this->getMockBuilder('ServiceFactory')->getMock();
        $mockServiceFactory
            ->expects($this->once())
            ->method('create')
            ->with($className);

        $this->injector->setFactory('Service$', [$mockServiceFactory, 'create']);

        $this->injector->make($className);
    }

    public function testIfFactoryGetsPassedTheInstanceNameOfTheClassIfItHasAParamCalledInstanceName()
    {
        $className = 'Service\AuthService';
        $instanceName = '3rd-party';

        $mockServiceFactory = $this->getMockBuilder('ServiceFactory')->getMock();
        $mockServiceFactory
            ->expects($this->once())
            ->method('build')
            ->with($instanceName);

        $this->injector->setFactory('Service$', [$mockServiceFactory, 'build']);

        $this->injector->make($className . '#' . $instanceName);
    }

    public function testMakingAnObjectWithACircularDependencyThrowsAnException()
    {
        $this->setExpectedException('David2M\Syringe\InjectorException', sprintf(Injector::EX_CIRCULAR_DEPENDENCY, 'CircularDependencyOne -> CircularDependencyTwo -> CircularDependencyThree -> CircularDependencyOne'));
        $this->injector->make('CircularDependencyOne');
    }

}
