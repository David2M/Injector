# PHP Dependency Injector #

## Instantiating an Object ##

For the sake of simplicity (this being the first example and all), say we want to instantiate an object which has no dependencies:

```php
class UserMapper
{
}
```

```php
$mapper = $injector->make('UserMapper');
```

## Recursively Resolving Parameters ##
```php
class PdoAdapter
{
}
```

The `UserMapper` now depends on a `PdoAdapter`

```php
// UserMapper.php
public function __construct(PdoAdapter $pdoAdapter)
{
}
```

```php
$mapper = $this->make('UserMapper');
```
When making a `UserMapper` the injector discovers that it depends on a `PdoAdapter` so it creates a `PdoAdapter` object first and injects it into the `UserMapper`.

## Unresolvable Parameters ##
Two scenarios exist where it is impossible to automatically resolve a parameter:

1. No type hint exists - In this situation you must explicitly tell the injector what the parameter is. See [setting parameters](#setting-parameters).
2. The type hint is an interface - See [mapping to concrete implementations](#mapping-to-concrete-implementations).

### Setting Parameters ###

```php
class PdoAdapter
{
  public function __construct($host, $user, $password, $schema)
  {
  }
}
```

```php
$constructor = $injector->getConstructor('PdoAdapter');

// Setting one parameter
$constructor->setParam('host', 'localhost');

// Setting multiple parameters
$constructor->addParams([
  'host' => 'localhost',
  'user' => 'david'
]);
```

You can set the parameters of any method, not just the constructor.

```php
$injector
  ->getMethod('PdoAdapter', 'someMethod')
  ->setParam('name', 'value');
```

You can also set parameters on the fly:

```php
$injector->make('PdoAdapter', [
  'host' => 'localhost',
  'user' => 'david'
]);
```

Parameters set on the fly always take precedence over parameters set prior to calling `make()`.

This is useful when [adding calls](#calling-a-method-after-instantiation) to a method or using the [invoke()](#invoking-a-method-or-function) method.

#### Callable Parameters ####

Callable parameters get invoked and their return value gets passed into the method.

```php
$injector
  ->getConstructor('PdoAdapter')
  ->setParam('user', function()
  {
    return 'bob';
  });
```

**If the type hint of the parameter is callable and the set parameter is callable then the parameter will NOT be invoked before passing it into the method.**

#### String Parameter Resolved to an Object ####
You can set a class name for a parameter which has a class or interface type hint.

```php
class UserMapper
{
  public function __construct(PdoAdapter $pdoAdapter)
  {
  }
}
```

```php
$injector
  ->getConstructor('UserMapper')
  ->setParam('pdoAdapter', 'PdoAdapter');
```

When the injector is resolving the parameter it will notice it is a string but the type hint is a class. It will then resolve the string parameter (class name) into an object.

This technique is especially powerful when you need to use [multiple instances of the same class](#multiple-instances-of-the-same-class). Say you have two databases, a local and a remote one and both obviously have different connection details. The `UserMapper` connects to the local database and the `Logger` connects to a remote database.

```php
class UserMapper
{
  public function __construct(PdoAdapter $pdoAdapter)
  {
  }
}
```

```php
class Logger
{
  public function __construct(PdoAdapter $pdoAdapter)
  {
  }
}
```

Both objects need a different instance of a `PdoAdapter`, one which can connect to the local database and another which can connect to the remote database.

```php
$injector
  ->getConstructor('PdoAdapter')
  ->addParams([
      'host' => 'localhost',
      'user' => 'local_user',
      'password' => 'local_password',
      'schema' => 'local_database_name'
    ]);

$injector
  ->getConstructor('PdoAdapter#remote')
  ->addParams([
      'host' => '103.243.0.78',
      'user' => 'remote_user',
      'password' => 'remote_password',
      'schema' => 'remote_database_name'
    ]);

$injector
  ->getConstructor('Logger')
  ->setParam('pdoAdapter', 'PdoAdapter#remote');

$mapper = $injector->make('UserMapper');
$logger = $injector->make('Logger');
```

The `UserMapper` will get the `#default` instance of the `PdoAdapter` and the `Logger` will get the `#remote` instance of the `PdoAdapter`. For more information about dealing with multiple instances click [here](#multiple-instances-of-the-same-class).

### Mapping to Concrete Implementations ###

```php
interface DatabaseAdapterInterface
{
}
```

```php
class PdoAdapter implements DatabaseAdapterInterface
{
}
```

```php
// UserMapper.php
public function __construct(DatabaseAdapterInterface $dbAdapter)
{
}
```

When trying to make a `UserMapper` the injector will throw an `InjectorException` because it cannot resolve `DatabaseAdapterInterface` to a concrete implementation. To prevent this you simply map the interface to a concrete implementation i.e. a class which implements the interface.

```php
$injector->setMapping('DatabaseAdapterInterface', 'PdoAdapter');

// You can also add multiple mappings at once
$injector->addMappings([
  'DatabaseAdapterInterface' => 'PdoAdapter',
  'AnotherInterface' => 'AnotherConcreteImplementation'
]);
```

## Calling a Method After Instantiation ##
```php
// PdoAdapter.php
public function connect()
{
}
```

```php
$injector
  ->getMethod('PdoAdapter', 'connect')
  ->addCall();

$pdoAdapter = $injector->make('PdoAdapter');
```

Once the `PdoAdapter` has been instantiated the `connect()` method will be called before the object is returned. If the method being called has any parameters they will be resolved and passed into the method. If the method has any [unresolvable parameters](#unresolvable-parameters) you must [set them](#setting-parameters) before you make the object or else pass them to the `addCall()` method.

## Using Factories (Delegating Instantiation) ##
A factory constist of a regular expression and a callable. The regular expression is matched against the name of the class being made, if there is a match then the instantiation of the object is delegated to the factory.

```php
// The regular expression ^PdoAdapter$ will match the classname PdoAdapter
$injector->setFactory('^PdoAdapter$', function()
{
  return new PdoAdapter('localhost', 'david', 'mypassword', 'my_database_name');
});

$pdoAdapter = $injector->make('PdoAdapter'); // Factory instantiates the object.
```

### Advanced Factory Usage ###
Let's say you have some services in your application which reside in the `Service` namespace. For some reason you don't want the injector to instantiate any of the services and want to delegate that job to your own `ServiceFactory`.

```php
namespace Service;

class Recognition
{
}

class Shopping
{
}
```

```php
class ServiceFactory
{
  public function create($className)
  {
    // Instantiate and return the object.
  }
}
```

```php
$injector->setFactory('^Service\\', function($className, ServiceFactory $serviceFactory)
{
  return $serviceFactory->create($className);
});

$recognition = $injector->make('Service\Recognition');
$shopping = $injector->make('Service\Shopping');
```

The instantiation of any class name that begins with `Service\` is delegated to the callable factory. If the callable factory has a parameter named `$className` then the injector will pass the full name of the class which matched the regular expression of the factory.

## Multiple Instances of the Same Class ##
So far we have only dealt with a single instance of the same class. Sometimes you may want multiple instances of the same class. Such a use case would be dealing with multiple databases. Your application may need to connect to a local database and a remote database.

When making an object, setting its parameters or adding calls to its methods you can specify which instance of the object you are referring to by putting a `#instance-name` after the class name. If you do not supply an instance name then you are dealing with the `#default` instance of the class.

```php
$injector->make('PdoAdapter');

// Is the same as
$injector->make('PdoAdapter#default');
```

Two different instances of the same class:

```php
$injector
  ->getConstructor('PdoAdapter')
  ->addParams([
      'host' => 'localhost',
      'user' => 'local_user',
      'password' => 'local_password',
      'schema' => 'local_database_name'
    ]);

$injector
  ->getConstructor('PdoAdapter#remote')
  ->addParams([
      'host' => '103.243.0.78',
      'user' => 'remote_user',
      'password' => 'remote_password',
      'schema' => 'remote_database_name'
    ]);

$localPdoAdapter = $injector->make('PdoAdapter');
$remotePdoAdapter = $injector->make('PdoAdapter#remote');

var_dump($localPdoAdapter === $remotePdoAdapter); // bool(false)
```

## Sharing Instances ##
By default, if an object is created by the injector it is stored and used every time an instance of that object is needed.
```php
$userOne = $injector->make('Entity\User');
$userTwo = $injector->make('Entity\User');

var_dump($userOne === $userTwo); // bool(true)
```

You can tell the injector to always create an new instance of an object:

```php
$injector
  ->getInstanceDef('Entity\User')
  ->singleton(false);

$userOne = $injector->make('Entity\User');
$userTwo = $injector->make('Entity\User');

var_dump($userOne === $userTwo); // bool(false)
```

## Invoking a Method or Function ##
The `invoke()` method accepts any valid PHP [callable](https://secure.php.net/manual/en/language.types.callable.php) and an optional second argument which contains parameters you want to pass into the method/function when it is invoked.