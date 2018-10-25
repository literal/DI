# literal Dependency Injection Container

This is yet another Dependency Injection Container for PHP 7.1+.

In case you don't know what a Dependency Injection Container (also Inversion of Control Container) is, this article might be a good starting point: https://martinfowler.com/articles/injection.html


## What is Particular About this DI Container?

There are lots of great PHP DI Containers out there. So why did I write my own?

I wanted it to be simple and light-weight, and I wanted element qualifiers and container nesting. Both concepts are explained below. (I've come up with both of them myself, but I'm probably just ignorant of existing projects already implementing them.)


## Setting Up the Container

```PHP
use literal\DI\{Container, Factory};

$container = new Container();
$factory = new Factory($container);
$factory->setElementMap([ /* ... */ ]);
```


## Using the Container

```PHP
// Fetch or create a shared element specified by 'ElementKey'.
$element = $container->getSharedElement('ElementKey');

// Create a private element specified by 'ElementKey'. 
// The element is not registered in the container.
$element = $container->createPrivateElement('ElementKey');

// Set an externally created element to be shared through the container
$container->setSharedElement('ElementKey', $element);
```

The container has more public methods, but you will hardly ever need to call them directly.


## Basic Element Map Syntax

The _element map_ defines how _elements_ are created. (I call them _elements_ because the container is agnostic to what they are. They don't have to be object instances, although most times they are.)

The _element map_ is a hash (an associative array, if you prefer the PHP terminology) having _element aliases_ as keys and _element definitions_ (recipes for creating elements) as values.

_Element definitions_ may either provide a fully qualified class name for creating an object instance or an anonymous function creating the element.

The following two _element map_ entries are equivalent:

```PHP
// Declarative style element definition
'MyElementAlias' => [
    'args' => ['foo', 1234], // Arguments passed to the constructor
    'class' => My\Namespace\MyClass::class
]
```

```PHP
// Imperative style element definition
'MyElementAlias' => [
    'args' => ['foo', 1234], // Arguments passed to the creator function
    'creator' => function (string $foo, int $bar) {
        return new My\Namespace\MyClass($foo, $bar);
    }
]
```

Personally, I prefer the declarative syntax because it's more concise and I only use creator functions when I need to perform some initialisation of a created object (e.g. setter injection) or when the element is not at object:

```PHP
'MyObjectAlias' => [
    'args' => ['foo', 1234],
    'creator' => function (string $foo, int $bar) {
        $instance = new My\Namespace\MyClass($foo);
        $instance->setBar($bar);
        return $instance;
    }
]
```


## Dependencies

Let's see how dependencies are specified in the element map:

```PHP
[
    'FooAlias' => [
        // '@' as the first character of a string argument has a special meaning:
        // it means "treat the rest of this string as an element key and get the
        // shared element having this key".
        'args' => ['@BarAlias'],
        'class' => NS1\Foo::class
    ],
    
    'BarAlias' => [
        'class' => NS2\Bar::class
    ],
]
```

So when `getSharedElement('FooAlias')` is called on the container for the first time, the following happens internally:

- If the element with the key `BarAlias` does not yet exist, create it as a new instance of `NS2\Bar` and register it.
- Create a new instance of `NS1\Foo`, passing the previously created `NS2\Bar` instance to the constructor.
- Register the `NS1\Foo` instance as a shared element under the key `FooAlias`.

If `Foo` doesn't want to share its `Bar` instance with anyone else, simply replace the `@` in its constructor argument with `#`:

```PHP
'FooAlias' => [
    // '#' requests a private instance of 'BarAlias' which is
    // not registered as a shared element in the conatainer.
    'args' => ['#BarAlias'],
    'class' => NS1\Foo::class
]
```


## Element Qualifiers

The container allows for multiple elements to share the same _element alias_, distinguished by an _element qualifier_. When referring to an element, the _element alias_ and the _element qualifier_ are separated by a dot. Together (i.e. "Alias.qualifier") they form the _element key_.

As _element qualifiers_ are optional, the _element alias_ and _element key_ are often identical.

One common pattern is to use _element qualifiers_ to distinguish between multiple instances of the same class which are differently initialised according to their respective qualifier value.

```PHP
class MoneyFormatter
{
    /** @var string */
    private $languageTag;

    public function __construct(string $languageTag)
    {
        $this->languageTag = $languageTag;
    }

    // ... produces different results depending on the language tag.
}
```

And this is how an object map entry for the `MoneyFormatter` class might look:

```PHP
'MoneyFormatter' => [
    // The argument expression '$' has a special meaning.
    // It represents the requested Instance Qualifier (which
    // is passed to the constructor as sole argument here).
    'args' => ['$'],
    'class' => MoneyFormatter::class
]
```

We could now request the _element keys_ `MoneyFormatter.en` and `MoneyFormatter.fr` from the container and get two different instances created as `new MoneyFormatter('en')` and `new MoneyFormatter('fr')`.

The obvious advantage: we can add support for any number of languages (and accordingly configured instances of `MoneyFormatter`) without ever having to touch the _element map_.

As it's legal to include dots in the _element alias_, you can have entries overriding particular qualifier values:

```PHP
[
    'MoneyFormatter' => [
        'args' => ['$'],
        'class' => MoneyFormatter::class
    ],
    // When 'MoneyFormatter.cn' is requested, this entry has
    // precedence over the shorter alias 'MoneyFormatter' and
    // '.cn' is not treated as an element qualifier.
    'MoneyFormatter.cn' => [
        'class' => ChineseMoneyFormatter::class
    ]
]
```

Now requesting `MoneyFormatter.en` still yields an instance created as `new MoneyFormatter('en')` while `MoneyFormatter.cn` refers to an instance created as `new ChineseMoneyFormatter()`. (It's your responsibility to ensure these instances are compatible, though.)

But that's not all. You can also pass _element qualifiers_ on to dependencies:

```PHP
[
    'InvoiceView' => [
        // The trailing '.$' means "append the current qualifier to
        // the dependency's element key".
        // I.e. when 'InvoiceView.en' is requested from the Container, a
        // 'MoneyFormatter.en' is injected.
        'args' => ['@MoneyFormatter.$'],
        'class' => InvoiceView::class
    ],

    'MoneyFormatter' => [
        'args' => ['$'],
        'class' => MoneyFormatter::class
    ]
]
```

This way you can request separate `InvoiceView` instances known to the container as `InvoiceView.en` and `InvoiceView.fr` with matching `MoneyFormatter.en` and `MoneyFormatter.fr` instances automatically injected into their constructors.

Another common pattern for using _element qualifiers_ is this:

```PHP
[
    'Config' => [
        // ... here we define how to create an object having a get($key) method
        // for returning a single value from the application's configuration tree.
    ],

    'TemplateRenderer' => [
        'args' => ['@Config', '$'],
        'creator' => function(Config $config, string $qualifier) {
            $renderer = new TemplateRenderer();
            $renderer->setTemplatePath(
                $config->get('app.templatePaths.' . $qualifier)
            )
            return $renderer;
        }
    ]
]
```

When you request e.g. `TemplateRenderer.email` from the container, `get('app.templatePaths.email')` is called on the config object to get the template path to be set on the renderer.


## Nested Containers

Each entry in the element map may have its private _submap_. Such a _submap_ defines a set of elements only accessible locally - i.e. by the element map entry owning the _submap_ and by the _submap's_ entries themselves. A _submap's_ entry may itself have another _submap_ and so on.

So what are _submaps_ good for? Let's return to the first example for specifying dependencies:

```PHP
[
    'FooAlias' => [
        'args' => ['@BarAlias'],
        'class' => NS1\Foo::class
    ],
    
    'BarAlias' => [
        'class' => NS2\Bar::class
    ],
]
```

If `FooAlias` was the only one to ever use that particular instance of `NS2\Bar`, it would be a good idea to make this fact explicit and hide the dependency from the global scope of the element map like this:

```PHP
[
    'FooAlias' => [
        'args' => ['@BarAlias'],
        'class' => NS1\Foo::class,
        'submap' => [
            'BarAlias' => [
                'class' => NS2\Bar::class
            ]
        ]
    ]
]
```

Internally, a _submap_ leads to the creation of a child container. When a child container cannot resolve an element key, it passes the request on to its parent container:

```PHP
[
    'FooAlias' => [
        'args' => ['@BarAlias'],
        'class' => NS1\Foo::class,
        'submap' => [
            'BarAlias' => [
                // Yes, we can refer to an object defined in the parent scope.
                'args' => ['@QuuxAlias'],
                'class' => NS2\Bar::class
            ]
        ]
    ],

    'QuuxAlias' => [
        'class' => NS3\Quux::class
    ]
]
```


You will see another use case for container nesting below: the `Locator`.


## The Locator

Somewhere in every dependency-injected application you will have a piece of set-up code that must access the container directly.

But also other parts of applications where high-level branching and/or lazy-loading takes place often need access to the container, e.g. front controllers that delegate work to other more specific controllers and choose those controllers dynamically depending on incoming requests.

You probably agree that passing around the container to other objects (the service locator pattern) is not a great idea because it makes you loose control over dependencies and it makes stuff hard to test.

Enter the `Locator` class. It's a very simple wrapper for the container that limits access to what is necessary by providing read-only access to objects defined in its local submap:

```PHP
[
    'FrontController' => [
        'args' => ['@ControllerLocator'],
        'class' => NS1\FrontController::class
    ],

    'ControllerLocator' => [
        // 'Container' is a predefined element key that refers to the current container.
        // For objects having a submap this is the respective child container.
        'args' => ['@Container'],
        'class' => literal\DI\Locator::class,
        'submap' => [
            // Only these objects will be accessible through the locator.
            'FooController' => [ /* ... */ ],
            'BarController' => [ /* ... */ ]
        ]
    ]
]
```

(If no one else needs access to it, you could of course also define the `ControllerLocator` in the submap of `FrontController`.)

Now the front controller can access `FooController` and `BarController` like this:

```PHP
namespace NS1;

use literal\DI\Locator;

class FrontController
{
    /** @var Locator */
    private $controllerLocator;
    
    public function __construct(Locator $controllerLocator)
    {
        $this->controllerLocator = $controllerLocator;
    }

    // ...

    // Assuming all controllers have a common base class or interface 'Controller'
    private function getController(string $objectKey): Controller
    {
        if ($this->controllerLocator->has($objectKey)) {
            return $this->controllerLocator->get($objectKey);
        }
        else {
            // ...
        }
    }
}
```


## Class File Loading

For non-autoloaded legacy code the element map allows you to have a PHP file included before the element is created:

```PHP
'MyLegacyElementAlias' => [
    // Before this object is created, the following PHP file is loaded:
    'file' => '/path/to/LegacyClass.php',
    'class' => LegacyClass::class,
]
```


## Element Map Shorthand Syntax

When there's only a single constructor or creator function argument, the surrounding array is optional:

```PHP
'MyElementAlias' => [
    // Note the missing array brackets:
    'args' => '@DependencyAlias',
    'class' => Namespace\SomeClass::class,
]
```

When the element definition consists of a class name only, that class name may be provided as a string value:

```PHP
// No constructor arguments, no submap, no file to load - may be written like this:
'MyObjectAlias' => Namespace\SomeClass:class
```


## Known Issues

- Circular references are not detected.

- The element map is not yet validated upon being set. This violates the "fail fast and fail hard" principle.

- There isn't yet a way to escape characters with a special meaning in the element map's `args` element (`@`, `#` and `$`). In the rare cases where you would pass literal strings to constructors at all (let alone ones that might contain any of these characters), you could still resort to doing so inside a creator function instead.

