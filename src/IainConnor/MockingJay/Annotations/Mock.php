<?php
namespace IainConnor\MockingJay\Annotations;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Mock
 *
 * @package IainConnor\MockingJay\Annotations
 * @Annotation
 * @Target("PROPERTY")
 */
class Mock {

	/**
	 * @var string
	 */
	public $fakerProvider;

	/**
	 * @var string
	 */
	public $callback;
}