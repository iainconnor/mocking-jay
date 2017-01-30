<?php


namespace IainConnor\MockingJay;


use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use Faker\Factory;
use Faker\Generator;
use IainConnor\Cornucopia\AnnotationReader;
use IainConnor\Cornucopia\Type;
use Iainconnor\MockingJay\Annotations\Count;
use IainConnor\MockingJay\Annotations\IgnoreMock;
use IainConnor\MockingJay\Annotations\Mock;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\MockingJay\Annotations\Whitelist;
use IainConnor\Cornucopia\CachedReader;

class MockingJay {

    /**
     * @var MockingJay Current booted instance.
     */
    protected static $instance;

	/**
	 * @var array Faker providers for the basic types.
	 */
	protected $fakerProviders;

	/**
	 * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
	 * @var CachedReader Doctrine Annotation reader.
	 */
	protected $annotationReader;

	/**
	 * @var Generator Faker generator.
	 */
	protected $faker;

    /**
     * MockingJay constructor.
     * @param array $fakerProviders
     * @param $annotationReader
     * @param Generator $faker
     */
    public function __construct(array $fakerProviders, $annotationReader, Generator $faker)
    {
        $this->fakerProviders = $fakerProviders;
        $this->annotationReader = $annotationReader;
        $this->faker = $faker;
    }

    /**
     * @return MockingJay Get or boot the instance.
     */
    public static function instance() {
        if ( static::$instance == null ) {
            static::$instance = static::boot();
        }

        return static::$instance;
    }

    /**
	 * Boot and ensure requirements are filled.
     * @return MockingJay
	 */
	protected static function boot() {

        AnnotationRegistry::registerAutoloadNamespace('\IainConnor\MockingJay\Annotations', static::getSrcRoot());

        return new MockingJay(
            [
                'string' => 'sentence',
                'int' => 'randomDigitNotNull',
                'float' => 'randomFloat',
                'bool' => 'boolean',
            ],
            new CachedReader(
                new AnnotationReader(),
                new ArrayCache(),
                false
            ),
            Factory::create()
        );
	}

	/**
	 * Retrieves an instance of the given class with values mocked.
	 *
	 * @param $class
	 * @return object
	 */
	public function mock($class) {

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
	public function mockInstance($instance) {

		$reflectedClass = new \ReflectionClass($instance);

		$inWhiteListMode = $this->annotationReader->getClassAnnotation($reflectedClass, Whitelist::class) !== null;

		foreach ($reflectedClass->getProperties() as $reflectedProperty) {
			$reflectedProperty->setAccessible(true);

			if ( $reflectedProperty->getValue($instance) == null ) {
				foreach ($this->annotationReader->getPropertyAnnotations($reflectedProperty) as $propertyAnnotation) {
					if ($propertyAnnotation instanceof TypeHint) {
						/** @var $mockAnnotation \IainConnor\MockingJay\Annotations\Mock */
						$mockAnnotation = $this->annotationReader->getPropertyAnnotation($reflectedProperty, Mock::class);
						if (($inWhiteListMode && $mockAnnotation !== null) || (!$inWhiteListMode && $this->annotationReader->getPropertyAnnotation($reflectedProperty, IgnoreMock::class) === null)) {
							$wasMocked = false;
							$mockedValue = null;

							if ($mockAnnotation != null) {
								if ($mockAnnotation->fakerProvider != null) {
									$mockedValue = $this->faker->{$mockAnnotation->fakerProvider};
									$wasMocked = true;
								} else if ($mockAnnotation->callback != null) {
									$mockedValue = $instance->{$mockAnnotation->callback}();
									$wasMocked = true;
								}
							}

							if (!$wasMocked) {
								$mockedValue = $this->generateMockValueForTypeHint($propertyAnnotation, $this->annotationReader->getPropertyAnnotation($reflectedProperty, Count::class));
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
	protected function generateMockValueForTypeHint(TypeHint $typeHint, Count $count = null) {

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

			$genericType = new Type();

			if ( $type->genericType ) {
				$genericType->type = $type->genericType;
			} else {
				$genericType->type = 'string';
			}

			$genericTypeHint = new TypeHint([$genericType], $typeHint->variableName);

			for ($i = 0; $i < $mockValueCount; $i++) {
				$mockedValue[] = $this->generateMockValueForTypeHint($genericTypeHint);
			}
		} else if (array_key_exists($type->type, $this->fakerProviders)) {
			$mockedValue = $this->faker->{$this->fakerProviders[$type->type]};
		} else {
			// Recurse.
			$mockedValue = $this->mock($type->type);
		}

		return $mockedValue;
	}

	/**
	 * Set the array of Faker providers, where the keys are the types and the values are the providers.
	 *
	 * @param array $fakerProviders
	 */
	public function setFakerProviders($fakerProviders) {

		$this->fakerProviders = $fakerProviders;
	}

	/**
	 * Add a Faker provider for the given type.
	 *
	 * @param $type
	 * @param $fakerProvider
	 */
	public function addFakerProvider($type, $fakerProvider) {

        $this->fakerProviders[$type] = $fakerProvider;
	}

	/**
	 * Add an array of Faker providers, where the keys are the types and the values are the providers.
	 *
	 * @param $fakerProviders
	 */
	public function addFakerProviders($fakerProviders) {

		$this->fakerProviders = array_merge($this->fakerProviders, $fakerProviders);
	}

	/**
	 * Set the AnnotationReader.
	 *
	 * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
	 * @param CachedReader $annotationReader
	 */
	public function setAnnotationReader($annotationReader) {

		$this->annotationReader = $annotationReader;
	}

	/**
	 * Set the Faker generator instance.
	 *
	 * @param Generator $faker
	 */
	public function setFaker($faker) {

		$this->faker = $faker;
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