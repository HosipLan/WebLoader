<?php

namespace WebLoader\Nette;

use Nette\Utils\Html;
use WebLoader\InvalidArgumentException;

/**
 * JavaScript loader
 *
 * @author Jan Marek
 * @license MIT
 */
class JavaScriptLoader extends WebLoader
{

	/** @var array */
	private $dataAttributes = array();

	/**
	 * @param string $name
	 * @param mixed $value
	 * @throws \WebLoader\InvalidArgumentException
	 */
	public function setVariable($name, $value)
	{
		if (!preg_match('#^[-a-zA-Z0-9_:.]+$#', $name)) {
			throw new InvalidArgumentException("Invalid variable name given: $name");
		}
		$this->dataAttributes['data-' . $name] = $value;
	}

	/**
	 * Get script element
	 * @param string $source
	 * @return Html
	 */
	public function getElement($source)
	{
		$el = Html::el("script")->type("text/javascript")->src($source);
		if (!empty($this->dataAttributes)) {
			$el->class = 'jsLoader';
			$el->addAttributes($this->dataAttributes);
		}
		return $el;
	}

}
