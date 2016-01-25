<?php
use David2M\Injector\Injector;
use David2M\Injector\ClassDefinition;

require('fixtures.php');

class InjectorTest extends PHPUnit_Framework_TestCase
{
    /* @var Injector */
    private $injector;

    public function setUp()
    {
        $this->injector = new Injector();
    }

    public function testResolveAliasToClassName()
    {
        $alias = 'Injector';
        $className = 'David2M\Injector\Injector';

        $this->injector->setAlias($alias, $className);

        $this->assertSame($className, $this->injector->resolveAlias($alias));
    }

    public function testGetClassDefinitionUsingAlias()
    {
        $alias = 'P';
        $className = 'Product';

        $this->injector->setAlias($alias, $className);
        $classDef = $this->injector->getClass($alias);

        $this->assertEquals(new ClassDefinition($className), $classDef);
    }

    public function testMakeObjectUsingAlias()
    {
        $alias = 'P';
        $className = 'Product';

        $this->injector->setAlias($alias, $className);

        $obj = $this->injector->make('P');

        $this->assertInstanceOf($className, $obj);
    }

    public function testInvokingGetClassMoreThanOnceWithSameClassNameReturnsSameInstance()
    {
        $class = 'My\Class';

        $obj1 = $this->injector->getClass($class);
        $obj2 = $this->injector->getClass($class);

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

        $this->setExpectedException('David2M\Injector\InjectorException', $message);
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

    public function testObjectsAreTheSameInstance()
    {
        $obj1 = $this->injector->make('User');
        $obj2 = $this->injector->make('User');

        $this->assertTrue($obj1 === $obj2);
    }

    public function testObjectsAreTheSameInstanceWhenClassIsExplicitlyDefinedAsSingleton()
    {
        $this->injector
            ->getClass('User')
            ->singleton(true);

        $obj1 = $this->injector->make('User');
        $obj2 = $this->injector->make('User');

        $this->assertTrue($obj1 === $obj2);
    }

    public function testObjectsAreNotTheSameInstanceWhenClassIsDefinedAsNotASingleton()
    {
        $this->injector
            ->getClass('User')
            ->singleton(false);

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
        $this->setExpectedException('David2M\Injector\InjectorException', sprintf(Injector::EX_PARAM_NOT_FOUND, 'InjectorTest', '{closure}', 'param'));

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
        $this->setExpectedException('David2M\Injector\InjectorException', sprintf(Injector::EX_UNMAPPED_INTERFACE, 'DatabaseAdapterInterface'));
        $this->injector->make('UserMapper');
    }

    public function testMapInterfaceToConcreteImplementation()
    {
        $this->injector->setMapping('DatabaseAdapterInterface', 'PdoAdapter');
        $mapper = $this->injector->make('UserMapper');

        $this->assertAttributeInstanceOf('PdoAdapter', 'adapter', $mapper);
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

    public function testShareAnObjectWhileMappingAnInterfaceToTheObject()
    {
        $adapter = new PdoAdapter();
        $this->injector->share(['DatabaseAdapterInterface' => $adapter]);

        $obj = $this->injector->make('UserMapper');

        $this->assertAttributeInstanceOf('PdoAdapter', 'adapter', $obj);
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

        $this->setExpectedException('David2M\Injector\InjectorException', sprintf(Injector::EX_PARAM_NOT_FOUND, $className, $methodName, $paramName));

        $this->injector
            ->getMethod($className, $methodName)
            ->addCall();

        $this->injector->make($className);
    }

    public function testAddCallToMethodWhichDoesNotExist()
    {
        $className = 'User';
        $methodName = 'setAge';

        $this->setExpectedException('David2M\Injector\InjectorException', sprintf(Injector::EX_METHOD_NOT_CALLABLE, $className, $methodName));

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

}
