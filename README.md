PHP Inversion of Control(IoC) Library
======================================

Very simple tool for managing class dependencies. Dependency injection is a method of removing hard-coded class dependencies.
Instead, the dependencies are injected at run-time, allowing for greater flexibility as dependency implementations
may be swapped easily.


Here is a simple example that shows how to register services and parameters:

    use IoC\Container;

    $c = new Container();

    $c->setParameter('cache', array(
        'host' => '127.0.0.1',
        'port' => 11211,
    ));

    $c->singleton('cache', function (Container $c) {
        $cache = new Memcached();
        $cache->addServer(
            $c->getParameter('cache.host'),
            $c->getParameter('cache.port')
        );
        return $cache;
    });

    // single instance of Memcached
    $value = $c->make('cache');

Resolving a class dependencies:

    use IoC\Container;

    $c = new Container();
    $c->singleton('cache', 'MemcachedCache');

    class MemcachedCache extends Cache
    {
        public function __construct(Memcached $cache)
        {
            $this->cache = $cache;
        }
    }

    $cache = $c->make('cache');

Resolving a simple class:

    use IoC\Container;

    $c = new Container();

    class FooBar
    {
        public function __construct(Container $c, Baz $baz)
        {
            $this->container = $c;
            $this->baz = $baz;
        }
    }

    $fooBar = $c->make('FooBar');

Binding an interface to an implementation:

    use IoC\Container;

    $c = new Container();
    $c->bind('UserRepositoryInterface', 'DbUserRepository');

    class UserController extends BaseController {

        public function __construct(UserRepositoryInterface $users)
        {
            $this->users = $users;
        }

    }

    $c->make('UserController');


Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/php-ioc/
    $ composer.phar install
    $ phpunit

Links
-----

