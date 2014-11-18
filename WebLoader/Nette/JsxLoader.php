<?php

namespace Damejidlo\WebLoader;

use WebLoader\Nette\JavaScriptLoader;

/**
 * JsxScript loader
 *
 * @author Jan Marek
 * @license MIT
 */
class JsxLoader extends JavaScriptLoader
{

	public function getElement($source)
	{
		return parent::getElement($source)->type('text/jsx');
	}

}
