<?php


namespace IainConnor\MockingJay;


use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use Faker\Factory;
use Faker\Generator;
use Iainconnor\MockingJay\Annotations\Count;
use IainConnor\MockingJay\Annotations\IgnoreMock;
use IainConnor\MockingJay\Annotations\Mock;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\MockingJay\Annotations\Whitelist;
use IainConnor\Cornucopia\CachedReader;

class MockingJay {

	/**
	 * Has the instance been booted yet.
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Faker providers for the basic types.
	 * @var array
	 */
	protected static $fakerProviders = [
		'string' => 'sentence',
		'int' => 'randomDigitNotNull',
		'float' => 'randomFloat',
		'bool' => 'boolean',
	];

	/**
	 * Doctrine Annotation reader.
	 * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
	 * @var CachedReader
	 */
	protected static $annotationReader;

	/**
	 * Faker generator.
	 * @var Generator
	 */
	protected static $faker;

	/**
	 * Boot and ensure requirements are filled.
	 */
	protected static function boot() {

		if (!static::$booted) {
			AnnotationRegistry::registerAutoloadNamespace('\IainConnor\MockingJay\Annotations', static::getSrcRoot());
			static::$booted = true;
		}

		if (static::$annotationReader == null) {
			static::$annotationReader = new CachedReader(
				new \IainConnor\MockingJay\AnnotationReader(),
				new ArrayCache(),
				false
			);
		}

		if (static::$faker == null) {
			static::$faker = Factory::create();
		}
	}

	/**
	 * Retrieves an instance of the given class with values mocked.
	 *
	 * @param $class
	 * @return object
	 */
	public static function mock($class) {

		$reflectedClass = new \ReflectionClass($class);
		$reflectedClassInstance = $reflectedClass->newInstance();

		return static::mockInstance($reflectedClassInstance);
	}

	/**
	 * Retrieves a copy of the given object with null values mocked.
	 *
	 * @param $instance
	 * @return object
	 */
	public static function mockInstance($instance) {

		static::boot();

		$reflectedClass = new \ReflectionClass($instance);

		$inWhiteListMode = static::$annotationReader->getClassAnnotation($reflectedClass, Whitelist::class) !== null;

		foreach ($reflectedClass->getProperties() as $reflectedProperty) {
			$reflectedProperty->setAccessible(true);

			if ( $reflectedProperty->getValue($instance) == null ) {
				foreach (static::$annotationReader->getPropertyAnnotations($reflectedProperty) as $propertyAnnotation) {
					if ($propertyAnnotation instanceof TypeHint) {
						/** @var $mockAnnotation \IainConnor\MockingJay\Annotations\Mock */
						$mockAnnotation = static::$annotationReader->getPropertyAnnotation($reflectedProperty, Mock::class);
						if (($inWhiteListMode && $mockAnnotation !== null) || (!$inWhiteListMode && static::$annotationReader->getPropertyAnnotation($reflectedProperty, IgnoreMock::class) === null)) {
							$wasMocked = false;
							$mockedValue = null;

							if ($mockAnnotation != null) {
								if ($mockAnnotation->fakerProvider != null) {
									$mockedValue = static::$faker->{$mockAnnotation->fakerProvider};
									$wasMocked = true;
								} else if ($mockAnnotation->callback != null) {
									$mockedValue = $instance->{$mockAnnotation->callback}();
									$wasMocked = true;
								}
							}

							if (!$wasMocked) {
								$mockedValue = static::generateMockValueForTypeHint($propertyAnnotation, static::$annotationReader->getPropertyAnnotation($reflectedProperty, Count::class));
								$wasMocked = true;
							}

							if ($wasMocked) {
								$reflectedProperty->setValue($instance, $mockedValue);
							}
						}
					}
				}
			}
		}

		return $instance;
	}

	/**
	 * Retrieves the mocked value for the given type hint.
	 *
	 * @param TypeHint $typeHint
	 * @param Count|null $count
	 * @return array|null|object
	 */
	protected static function generateMockValueForTypeHint(TypeHint $typeHint, Count $count = null) {

		$mockedValue = null;

		$type = $typeHint->types[0];

		if ($type->type == TypeHint::ARRAY_TYPE) {
			$mockedValue = [];
			$mockValueCount = $count == null ? null : $count->count;
			if ($mockValueCount == null) {
				if ($count != null && $count->min !== null && $count->max !== null) {
					$mockValueCount = rand($count->min, $count->max);
				} else {
					$mockValueCount = rand(0, 10);
				}
			}

			if ( $type->genericType ) {
				$genericTypeHint = new TypeHint([$type->genericType], $typeHint->variableName);
			} else {
				$genericTypeHint = new TypeHint(['string'], $typeHint->variableName);
			}

			for ($i = 0; $i < $mockValueCount; $i++) {
				$mockedValue[] = static::generateMockValueForTypeHint($genericTypeHint);
			}
		} else if (array_key_exists($type->type, static::$fakerProviders)) {
			$mockedValue = static::$faker->{static::$fakerProviders[$type->type]};
		} else {
			// Recurse.
			$mockedValue = static::mock($type->type);
		}

		return $mockedValue;
	}

	/**
	 * Set the array of Faker providers, where the keys are the types and the values are the providers.
	 *
	 * @param array $fakerProviders
	 */
	public static function setFakerProviders($fakerProviders) {

		self::$fakerProviders = $fakerProviders;
	}

	/**
	 * Add a Faker provider for the given type.
	 *
	 * @param $type
	 * @param $fakerProvider
	 */
	public static function addFakerProvider($type, $fakerProvider) {

		self::$fakerProviders[$type] = $fakerProvider;
	}

	/**
	 * Add an array of Faker providers, where the keys are the types and the values are the providers.
	 *
	 * @param $fakerProviders
	 */
	public static function addFakerProviders($fakerProviders) {

		self::$fakerProviders = array_merge(self::$fakerProviders, $fakerProviders);
	}

	/**
	 * Set the AnnotationReader.
	 *
	 * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
	 * @param CachedReader $annotationReader
	 */
	public static function setAnnotationReader($annotationReader) {

		self::$annotationReader = $annotationReader;
	}

	/**
	 * Set the Faker generator instance.
	 *
	 * @param Generator $faker
	 */
	public static function setFaker($faker) {

		self::$faker = $faker;
	}

	public static function getProjectRoot() {

		return MockingJay::getSrcRoot() . "/..";
	}

	public static function getSrcRoot() {

		$path = dirname(__FILE__);

		return $path . "/../..";
	}

	public static function getVendorRoot() {

		return MockingJay::getProjectRoot() . "/vendor";
	}

}