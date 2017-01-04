<?php
namespace IainConnor\MockingJay\Annotations;

/**
 * Class Count
 *
 * @package IainConnor\MockingJay\Annotations
 * @Annotation
 * @Target("PROPERTY")
 */
class Count {

	/**
	 * @var int
	 */
	public $count;

	/**
	 * @var int
	 */
	public $min;

	/**
	 * @var int
	 */
	public $max;
}