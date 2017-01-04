<?php

include(dirname(__FILE__) . "/vendor/autoload.php");

/**
 * Class Foo
 */
class Foo {

	/**
	 * You can use basic types and we'll guess at the format.
	 *
	 * @var string
	 */
	public $lorem;

	/**
	 * You can use arrays.
	 *
	 * @var array<int>
	 */
	public $ipsum;

	/**
	 * Or arrays in another format.
	 * And you can specify the count of items to generate.
	 *
	 * @var array string
	 * @\IainConnor\MockingJay\Annotations\Count(count=3)
	 */
	public $dolor;

	/**
	 * Or arrays in yet another format.
	 * And you can specify the range of items to generate.
	 *
	 * @var float[]
	 * @\IainConnor\MockingJay\Annotations\Count(min=0, max=3)
	 */
	public $sit;

	/**
	 * You can provide a custom callback to generate the value.
	 *
	 * @\IainConnor\MockingJay\Annotations\Mock(callback="generateAmit")
	 * @var string
	 */
	public $amit;

	/**
	 * You can use any provider from https://github.com/fzaninotto/Faker#formatters.
	 *
	 * @\IainConnor\MockingJay\Annotations\Mock(fakerProvider="name")
	 * @var string
	 */
	public $consectetur;

	/**
	 * You can use another Object.
	 *
	 * @var Bar
	 */
	public $adipiscing;

	/**
	 * And, of course, combine and mix these features.
	 *
	 * @var Bar[]
	 * @\IainConnor\MockingJay\Annotations\Count(count=4)
	 */
	public $lacinia;

	/**
	 * You can ignore certain properties.
	 *
	 * @\IainConnor\MockingJay\Annotations\IgnoreMock()
	 * @var int
	 */
	public $elit;

	/**
	 * Properties with default values will be left alone.
	 *
	 * @var string
	 */
	public $donec = "Donec";

	public function generateAmit() {

		return "AMIT!";
	}
}

/**
 * Class Bar
 * If a class is annotated with `Whitelist`, only the properties specifically annotated with `Mock` will be included.
 *
 * @\IainConnor\MockingJay\Annotations\Whitelist()
 */
class Bar {

	/**
	 * @var boolean
	 */
	public $lorem;

	/**
	 * @\IainConnor\MockingJay\Annotations\Mock()
	 * @var string
	 */
	public $ipsum;
}

// You should always set an AnnotationReader to improve performance.
// @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
\IainConnor\MockingJay\MockingJay::setAnnotationReader(
	new \IainConnor\MockingJay\CachedReader(
		new \IainConnor\MockingJay\AnnotationReader(),
		new \Doctrine\Common\Cache\ArrayCache()
	));

// Mock an instance of `Foo` and dump it out.
var_dump(\IainConnor\MockingJay\MockingJay::mock(Foo::class));

// Create a copy of `Foo` and mock the unset properties and dump it out.
$foo = new Foo();
$foo->lorem = "Lorem";
var_dump(\IainConnor\MockingJay\MockingJay::mockInstance($foo));