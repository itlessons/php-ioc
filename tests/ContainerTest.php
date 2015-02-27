<?php

use IoC\Container;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testParameters()
    {
        $c = new Container();
        $c->setParameter('foo', 'bar');
        $c->setParameter('cache', array('type' => 'apc'));

        $this->assertEquals($c->getParameter('foo'), 'bar');
        $this->assertEquals($c->getParameter('undefined'), null);
        $this->assertEquals($c->getParameter('undefined', 'q'), 'q');
        $this->assertEquals($c->getParameter('cache.type'), 'apc');

        $c->setParameter('cache.type', 'files');
        $this->assertEquals($c->getParameter('cache.type'), 'files');

        $this->assertFalse($c->hasParameter('undefined'));
        $this->assertTrue($c->hasParameter('foo'));
        $this->assertTrue($c->hasParameter('cache.type'));

        $this->assertNull($c->getParameter('session.type'));
    }

    public function testClosure()
    {
        $c = new Container();
        $c->bind('name', function () {
            return 'Vaso';
        });
        $this->assertEquals('Vaso', $c->make('name'));
    }

    public function testSharedClosure()
    {
        $c = new Container();
        $class = new stdClass;
        $c->singleton('class', function () use ($class) {
            return $class;
        });
        $this->assertTrue($class === $c->make('class'));
    }

    public function testAutoBuild()
    {
        $c = new Container();
        $this->assertTrue($c->make('ContainerSomeClass') instanceof ContainerSomeClass);
    }

    public function testSharedBuild()
    {
        $c = new Container();
        $c->singleton('ContainerSomeClass');

        $var1 = $c->make('ContainerSomeClass');
        $var2 = $c->make('ContainerSomeClass');
        $this->assertTrue($var1 === $var2);
    }

    public function testContainerIsPassedToResolvers()
    {
        $container = new Container();
        $container->bind('something', function ($c) {
            return $c;
        });
        $c = $container->make('something');
        $this->assertTrue($c === $container);
    }

    public function testParametersCanBePassedThroughToClosure()
    {
        $container = new Container();
        $container->bind('foo', function ($c, $parameters) {
            return $parameters;
        });

        $this->assertEquals(array(1, 2, 3), $container->make('foo', array(1, 2, 3)));
    }

    public function testResolutionOfDefaultParameters()
    {
        $c = new Container();
        $instance = $c->make('ContainerSomeClass2');
        $this->assertInstanceOf('ContainerSomeClass', $instance->cls);
        $this->assertEquals('boris', $instance->default);
    }

    public function testAliasesAndSharedObjects()
    {
        $c = new Container();
        $c->bind('b2', 'ContainerSomeClass', true);

        $instance1 = $c->make('ContainerSomeClass2');
        $instance2 = $c->make('ContainerSomeClass2');

        $this->assertInstanceOf('ContainerSomeClass', $instance1->cls);
        $this->assertInstanceOf('ContainerSomeClass', $instance2->cls);
        $this->assertTrue($instance1->cls === $instance2->cls);

        $instance3 = $c->make('ContainerSomeClass3');
        $instance4 = $c->make('ContainerSomeClass3');
        $this->assertInstanceOf('ContainerSomeClass3', $instance3);
        $this->assertInstanceOf('ContainerSomeClass2', $instance3->cls);
        $this->assertFalse($instance3->cls === $instance4->cls);
    }

    public function testInstanceMethod()
    {
        $obj = new ContainerSomeClass();

        $c = new Container();
        $c->instance(array('b1', 'b2', 'b3'), $obj);

        $obj1 = $c->make('b1');
        $obj2 = $c->make('b2');
        $obj3 = $c->make('b3');

        $this->assertTrue($obj === $obj1 && $obj === $obj2 && $obj === $obj3);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessageRegExp /Unresolvable dependency/
     */
    public function testMakeParametersException()
    {
        $c = new Container();
        $c->make('ContainerSomeClass4');
    }

    public function testMakeParameters()
    {
        $c = new Container();
        $obj = $c->make('ContainerSomeClass4', ['host' => 'localhost']);

        $this->assertInstanceOf('ContainerSomeClass4', $obj);
        $this->assertEquals($obj->host, 'localhost');
    }

    public function testMakeWithParametersFromContainer()
    {
        $c = new Container();
        $c->setParameter('charset', 'UTF-8');
        $obj = $c->make('ContainerSomeClass5');
        $this->assertEquals($obj->charset, 'UTF-8');
    }

    public function testExists()
    {
        $c = new Container();
        $this->assertFalse($c->exists('ContainerSomeClass4'));
        $c->bind('ContainerSomeClass4', 'ContainerSomeClass3');
        $this->assertTrue($c->exists('ContainerSomeClass4'));
    }

    public function testExtendedBindings()
    {
        $c = new Container();
        $c->bind('twig', 'ContainerSomeClass', true);
        $c->extend('twig', function (ContainerSomeClass $cls) {
            $cls->t = 555;
            return $cls;
        });

        $result = $c->make('twig');

        $this->assertEquals(555, $c->make('twig')->t);
        $this->assertSame($result, $c->make('twig'));

        $c->singleton('mailer', function () {
            return new ContainerSomeClass();
        });

        $c->extend('mailer', function (ContainerSomeClass $cls) {
            $cls->t = 777;
            return $cls;
        });

        $result = $c->make('mailer');

        $this->assertEquals(777, $c->make('mailer')->t);
        $this->assertSame($result, $c->make('mailer'));

        $c->singleton('q', 'ContainerSomeClass');
        $cls = $c->make('q');
        $this->assertEquals(1, $cls->t);
        $c->extend('q', function (ContainerSomeClass $cls) {
            $cls->t = 888;
            return $cls;
        });
        $this->assertEquals(888, $cls->t);
        $this->assertSame($cls, $c->make('q'));
    }

    public function testCallWithDependencies()
    {
        $c = new Container();
        $result = $c->call(function (StdClass $foo, $bar = array()) {
            return func_get_args();
        });
        $this->assertInstanceOf('stdClass', $result[0]);
        $this->assertEquals([], $result[1]);

        $result = $c->call(function (StdClass $foo, $bar = array()) {
            return func_get_args();
        }, ['bar' => 'jack']);
        $this->assertInstanceOf('stdClass', $result[0]);
        $this->assertEquals('jack', $result[1]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCallWithException()
    {
        $c = new Container;
        $c->call('ContainerTestCall');
    }

    public function testCallWithStaticMethodNameString()
    {
        $container = new Container;
        $result = $container->call('ContainerTestCall::injectS');
        $this->assertInstanceOf('ContainerSomeClass', $result[0]);
        $this->assertEquals('jack', $result[1]);
    }

    public function testCallWithGlobalMethodName()
    {
        $container = new Container;
        $result = $container->call('containerTestInject');
        $this->assertInstanceOf('ContainerSomeClass', $result[0]);
        $this->assertEquals('jack', $result[1]);
    }

    public function testCallWithCallableArray()
    {
        $container = new Container;
        $stub = new ContainerTestCall();
        $result = $container->call([$stub, 'work'], ['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testCallWithAtSignBasedClassReferences()
    {
        $container = new Container;
        $result = $container->call('ContainerTestCall:work', ['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $result);

        $container = new Container;
        $result = $container->call('ContainerTestCall:inject');
        $this->assertInstanceOf('ContainerSomeClass', $result[0]);
        $this->assertEquals('jack', $result[1]);
    }
}

class ContainerSomeClass
{
    public $t = 1;
}

class ContainerSomeClass2
{
    public $cls;
    public $default;

    public function __construct(ContainerSomeClass $cls, $default = 'boris')
    {
        $this->cls = $cls;
        $this->default = $default;
    }
}

class ContainerSomeClass3
{
    public $cls;

    public function __construct(ContainerSomeClass2 $cls)
    {
        $this->cls = $cls;
    }
}


class ContainerSomeClass4
{
    public $cls;
    public $host;

    public function __construct($host, ContainerSomeClass2 $cls)
    {
        $this->cls = $cls;
        $this->host = $host;
    }
}

class ContainerSomeClass5
{
    public function __construct($charset)
    {
        $this->charset = $charset;
    }
}

class ContainerTestCall
{
    public function work()
    {
        return func_get_args();
    }

    public function inject(ContainerSomeClass $stub, $default = 'jack')
    {
        return func_get_args();
    }

    public static function injectS(ContainerSomeClass $stub, $default = 'jack')
    {
        return func_get_args();
    }
}

function containerTestInject(ContainerSomeClass $stub, $default = 'jack')
{
    return func_get_args();
}