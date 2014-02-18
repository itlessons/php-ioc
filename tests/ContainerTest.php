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
}

class ContainerSomeClass
{
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
