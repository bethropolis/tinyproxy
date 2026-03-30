<?php

declare(strict_types=1);

namespace TinyProxy;

use Psr\Container\ContainerInterface;
use TinyProxy\Exception\ConfigException;
use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;

/**
 * Simple PSR-11 compliant dependency injection container
 */
class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];

    /**
     * Bind an interface to an implementation
     */
    public function bind(string $abstract, string|callable $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = $concrete;
        
        if ($singleton) {
            $this->singletons[$abstract] = true;
        }
    }

    /**
     * Bind a singleton
     */
    public function singleton(string $abstract, string|callable $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Check if a binding exists
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || 
               isset($this->instances[$id]) || 
               class_exists($id);
    }

    /**
     * Resolve a dependency from the container
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * Make an instance of the given class
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // Return existing instance if it's a singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get the concrete implementation
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // Build the instance
        $object = $this->build($concrete, $parameters);

        // Store as singleton if needed
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Build an instance of the given class
     */
    private function build(string|callable $concrete, array $parameters = []): mixed
    {
        // Handle callable
        if (is_callable($concrete)) {
            return $concrete($this, $parameters);
        }

        // Handle class string
        if (!class_exists($concrete)) {
            throw new ConfigException("Class {$concrete} does not exist");
        }

        $reflection = new ReflectionClass($concrete);

        // Check if class is instantiable
        if (!$reflection->isInstantiable()) {
            throw new ConfigException("Class {$concrete} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        // No constructor, just instantiate
        if ($constructor === null) {
            return new $concrete();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve method dependencies
     */
    private function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Check if primitive value was provided
            if (isset($primitives[$name])) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            // Try to resolve by type
            $type = $parameter->getType();
            
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
                continue;
            }

            // Try to use default value
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Cannot resolve
            throw new ConfigException(
                "Cannot resolve parameter '{$name}' for class"
            );
        }

        return $dependencies;
    }

    /**
     * Call a method with dependency injection
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            $reflection = new \ReflectionMethod($class, $method);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $dependencies = $this->resolveDependencies(
            $reflection->getParameters(),
            $parameters
        );

        return $callback(...$dependencies);
    }
}
