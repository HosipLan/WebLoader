<?php

namespace WebLoader\Nette;

use Nette\Utils\Html;
use WebLoader\InvalidArgumentException;
use WebLoader\InvalidStateException;

/**
 * JavaScript loader
 *
 * @author Jan Marek
 * @license MIT
 */
class JavaScriptLoader extends WebLoader
{

	/** @var array */
	private $variables = array();

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
		$this->variables[$name] = $value;
	}

	/**
	 * Get script element
	 *
	 * @param string $source
	 * @return Html
	 */
	public function getElement($source)
	{
		return Html::el("script")->type("text/javascript")->src($source);
	}

	public function render()
	{
		call_user_func_array('parent::render', func_get_args());
		if (!empty($this->variables)) {
			echo $this->getVariablesHolder(), PHP_EOL;
		}
	}

	/**
	 * @throws \WebLoader\InvalidStateException
	 * @return Html
	 */
	private function getVariablesHolder()
	{
		if (empty($this->variables)) {
			throw new InvalidStateException('There are no variables.');
		}
		$el = Html::el("script")->type("text/javascript");
		$el->class = 'jsVariables';

		$dataAttributes = array();
		foreach ($this->variables as $name => $value) {
			$dataAttributes['data-' . $name] = $value;
		}
		$el->addAttributes($dataAttributes);
		return $el;
	}

}
