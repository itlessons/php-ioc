<?php

namespace IoC;

use ReflectionClass;
use ReflectionParameter;
use Closure;
use InvalidArgumentException;
use LogicException;

class Container
{
    private $parameters = array();
    private $bindings = array();
    private $instances = array();
    private $aliases = array();

    /**
     * Register a shared binding in the container.
     *
     * @param string $name
     * @param Closure|string|null $callback
     * @see bind
     */
    public function singleton($name, $callback = null)
    {
        return $this->bind($name, $callback, true);
    }

    /**
     * Register a binding with the container.
     *
     * @param string $name
     * @param Closure|string|null $callback
     * @param bool $shared
     * @throws \InvalidArgumentException
     */
    public function bind($name, $callback = null, $shared = false)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException(sprintf('Parameter "name" must be a string'));
        }

        if (null === $callback) {
            $callback = $name;
        } else if (is_string($callback)) {
            $this->aliases[$callback] = $name;
        }

        $this->bindings[$name] = compact('callback', 'shared');
    }

    /**
     * Check exist or not binds in container
     *
     * @param string $name
     * @return bool
     */
    public function exists($name)
    {
        return
            array_key_exists($name, $this->bindings) ||
            array_key_exists($name, $this->aliases) ||
            array_key_exists($name, $this->instances);
    }

    /**
     * Resolve the given type from the container.
     *
     * @param $name
     * @param array $parameters
     * @return object
     */
    public function make($name, $parameters = array())
    {
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $callback = $name;

        if (isset($this->bindings[$name])) {
            $callback = $this->bindings[$name]['callback'];
        }

        $object = $this->build($callback, $parameters);

        if ($this->isShared($name)) {
            $this->instances[$name] = $object;
        }

        return $object;
    }

    /**
     * Instantiate a concrete instance of the given type.
     *
     * @param Closure|string $callback
     * @param array $parameters
     * @return object
     * @throws \InvalidArgumentException
     */
    public function build($callback, $parameters = array())
    {
        if ($callback instanceof Closure) {
            return $callback($this, $parameters);
        }

        $reflector = new ReflectionClass($callback);

        if (!$reflector->isInstantiable()) {
            throw new InvalidArgumentException(sprintf('Target[%s] not instantiable', $callback));
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $callback;
        }

        return $reflector->newInstanceArgs(
            $this->getDependencies($constructor->getParameters(), $parameters)
        );
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param ReflectionParameter[] $parameters
     * @param [] $primitives
     * @return array
     * @throws LogicException
     */
    protected function getDependencies($parameters, $primitives = array())
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {

            $class = $parameter->getClass();


            if (array_key_exists($parameter->name, $primitives)) {
                $dependencies[] = $primitives[$parameter->name];
            } elseif ($this->isInstanceOfContainer($parameter)) {
                $dependencies[] = $this;
            } elseif (!is_null($class)) {
                $dependencies[] = $this->make($class->name);
            } elseif (($p = $this->getParameter($parameter->name))) {
                $dependencies[] = $p;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new LogicException(sprintf('Unresolvable dependency [%s].', $parameter));
            }
        }

        return $dependencies;
    }

    private function isShared($name)
    {
        if (isset($this->bindings[$name]['shared'])) {
            return $this->bindings[$name]['shared'];
        }

        return false;
    }

    private function isInstanceOfContainer(ReflectionParameter $parameter)
    {
        return $parameter->getClass() &&
        ($parameter->getClass()->isSubclassOf('\IoC\Container') ||
            $parameter->getClass()->getName() == 'IoC\Container');
    }

    /**
     * Register an existing instance as shared in the container.
     *
     * @param string|array $name
     * @param $instance
     */
    public function instance($name, $instance)
    {
        if (is_array($name)) {
            $names = $name;
            $name = $names[0];

            $this->instances[$name] = $instance;

            foreach ($names as $n) {
                $this->aliases[$n] = $name;
            }

            return;
        }
        $this->instances[$name] = $instance;
    }

    /**
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function getParameter($name, $default = null)
    {
        $array = $this->parameters;

        foreach (explode('.', $name) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function hasParameter($key)
    {
        return $this->getParameter($key) !== null;
    }

    public function setParameter($name, $value)
    {
        $array = &$this->parameters;

        $keys = explode('.', $name);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) or !is_array($array[$key])) {
                $array[$key] = array();
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }
}