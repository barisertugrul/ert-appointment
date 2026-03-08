<?php

declare(strict_types=1);

namespace ERTAppointment;

use Closure;
use RuntimeException;
use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;

/**
 * Lightweight PSR-11-inspired dependency injection container.
 *
 * Features:
 *  - bind()      : new instance on every resolve (transient)
 *  - singleton() : one shared instance per key
 *  - instance()  : register a pre-built object
 *  - make()      : resolve with auto-wiring via reflection
 *
 * This avoids pulling in a full framework while still enabling clean
 * constructor injection throughout the codebase.
 */
final class Container {

	/** @var array<string, Closure> Transient factory closures. */
	private array $bindings = array();

	/** @var array<string, Closure> Singleton factory closures. */
	private array $singletons = array();

	/** @var array<string, object> Already-resolved singleton instances. */
	private array $resolved = array();

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Registers a transient binding. Each call to make() returns a new instance.
	 *
	 * @param string              $abstract  Interface or class name.
	 * @param string|Closure|null $concrete  Concrete class name, factory closure, or null to use $abstract.
	 */
	public function bind( string $abstract, string|Closure|null $concrete = null ): void {
		$concrete ??= $abstract;

		if ( is_string( $concrete ) ) {
			$concreteClass = $concrete;
			$concrete      = fn( Container $c ) => $c->build( $concreteClass );
		}

		$this->bindings[ $abstract ] = $concrete;
	}

	/**
	 * Registers a singleton. Only the first call to make() creates the object;
	 * subsequent calls return the cached instance.
	 *
	 * @param string              $abstract
	 * @param string|Closure|null $concrete
	 */
	public function singleton( string $abstract, string|Closure|null $concrete = null ): void {
		$concrete ??= $abstract;

		if ( is_string( $concrete ) ) {
			$concreteClass = $concrete;
			$concrete      = fn( Container $c ) => $c->build( $concreteClass );
		}

		$this->singletons[ $abstract ] = $concrete;
	}

	/**
	 * Registers an already-constructed object as a singleton.
	 */
	public function instance( string $abstract, object $instance ): void {
		$this->resolved[ $abstract ] = $instance;
	}

	// -------------------------------------------------------------------------
	// Resolution
	// -------------------------------------------------------------------------

	/**
	 * Resolves a binding from the container.
	 * Resolution order: resolved cache → singleton factory → transient factory → auto-wire.
	 *
	 * @template T of object
	 * @param class-string<T> $abstract
	 * @return T
	 */
	public function make( string $abstract ): object {
		// 1. Already resolved singleton.
		if ( isset( $this->resolved[ $abstract ] ) ) {
			/** @var T */
			return $this->resolved[ $abstract ];
		}

		// 2. Singleton factory — resolve and cache.
		if ( isset( $this->singletons[ $abstract ] ) ) {
			$instance                    = ( $this->singletons[ $abstract ] )( $this );
			$this->resolved[ $abstract ] = $instance;

			/** @var T */
			return $instance;
		}

		// 3. Transient factory.
		if ( isset( $this->bindings[ $abstract ] ) ) {
			/** @var T */
			return ( $this->bindings[ $abstract ] )( $this );
		}

		// 4. Attempt auto-wiring for concrete classes.
		/** @var T */
		return $this->build( $abstract );
	}

	/**
	 * Returns true if the container has a registration for the given abstract.
	 */
	public function has( string $abstract ): bool {
		return isset( $this->resolved[ $abstract ] )
			|| isset( $this->singletons[ $abstract ] )
			|| isset( $this->bindings[ $abstract ] );
	}

	// -------------------------------------------------------------------------
	// Auto-wiring
	// -------------------------------------------------------------------------

	/**
	 * Instantiates a concrete class, recursively resolving constructor dependencies.
	 *
	 * @throws RuntimeException When the class cannot be instantiated.
	 */
	public function build( string $class ): object {
		try {
			$reflector = new ReflectionClass( $class );
		} catch ( \ReflectionException $e ) {
			$class_name = sanitize_text_field( $class );
			$error_text = sanitize_text_field( $e->getMessage() );
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are not rendered as HTML output.
			throw new RuntimeException(
				sprintf( 'Cannot reflect class [%s]: %s', $class_name, $error_text ),
				0,
				$e
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! $reflector->isInstantiable() ) {
			$class_name = sanitize_text_field( $class );
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are not rendered as HTML output.
			throw new RuntimeException(
				sprintf( 'Class [%s] is not instantiable. ', $class_name )
				. 'Did you forget to register a concrete binding for an interface?'
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$constructor = $reflector->getConstructor();

		if ( $constructor === null ) {
			return new $class();
		}

		$dependencies = array_map(
			fn( ReflectionParameter $param ) => $this->resolveDependency( $param, $class ),
			$constructor->getParameters()
		);

		return $reflector->newInstanceArgs( $dependencies );
	}

	/**
	 * Resolves a single constructor parameter.
	 */
	private function resolveDependency( ReflectionParameter $param, string $class ): mixed {
		$type = $param->getType();

		if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
			return $this->make( $type->getName() );
		}

		if ( $param->isDefaultValueAvailable() ) {
			return $param->getDefaultValue();
		}

		$param_name = sanitize_text_field( $param->getName() );
		$class_name = sanitize_text_field( $class );
		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are not rendered as HTML output.
		throw new RuntimeException(
			sprintf(
				'Cannot resolve primitive parameter [$%s] in class [%s]. Please use a factory closure for this binding.',
				$param_name,
				$class_name
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}
