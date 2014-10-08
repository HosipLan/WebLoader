<?php

namespace WebLoader\Nette;

use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Config\Helpers;
use Nette\DI\ContainerBuilder;
use Nette\Utils\Finder;
use Nette;
use WebLoader\FileNotFoundException;

if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
	class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
	class_alias('Nette\Config\Helpers', 'Nette\DI\Config\Helpers');
}

if (!class_exists('Nette\Configurator')) {
	class_alias('Nette\Config\Configurator', 'Nette\Configurator');
}


/**
 * @author Jan Marek
 */
class Extension extends CompilerExtension
{

	const DEFAULT_TEMP_PATH = 'webtemp';
	const EXTENSION_NAME = 'webloader';

	public function getDefaultConfig()
	{
		return array(
			'jsDefaults' => array(
				'sourceDir' => '%wwwDir%/js',
				'tempDir' => '%wwwDir%/' . self::DEFAULT_TEMP_PATH,
				'tempPath' => self::DEFAULT_TEMP_PATH,
				'files' => array(),
				'remoteFiles' => array(),
				'filters' => array(),
				'fileFilters' => array(),
				'joinFiles' => TRUE,
				'namingConvention' => '@' . $this->prefix('jsNamingConvention'),
			),
			'cssDefaults' => array(
				'sourceDir' => '%wwwDir%/css',
				'tempDir' => '%wwwDir%/' . self::DEFAULT_TEMP_PATH,
				'tempPath' => self::DEFAULT_TEMP_PATH,
				'files' => array(),
				'remoteFiles' => array(),
				'filters' => array(),
				'fileFilters' => array(),
				'joinFiles' => TRUE,
				'namingConvention' => '@' . $this->prefix('cssNamingConvention'),
			),
			'js' => array(

			),
			'css' => array(

			),
		);
	}

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->getDefaultConfig());

		$builder->addDefinition($this->prefix('cssNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createCssConvention');

		$builder->addDefinition($this->prefix('jsNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createJsConvention');

		$builder->parameters['webloader'] = $config;

		$loaderFactoryTempPaths = array();

		foreach (array('css', 'js') as $type) {
			$defaultConfig = $config[$type . 'Defaults'];
			foreach ($config[$type] as $name => $wlConfig) {
				if (Helpers::isInheriting($wlConfig)) {
					$parent = Helpers::takeParent($wlConfig);
					if (!isset($config[$type][$parent])) {
						throw new CompilationException(sprintf(
							'The section %s.%s.%s inherits from %s.%s.%s, but the parent section is not defined.',
							$this->name, $type, $name,
							$this->name, $type, $parent
						));
					}

					$parentConfig = Helpers::merge($defaultConfig, $config[$type][$parent]);

					$mergedConfig = Helpers::merge($parentConfig, $wlConfig);
					foreach ($mergedConfig as $key => $val) {
						if (is_array($val) && isset($wlConfig[$key])) {
							// the Helpers::merge puts the added files to the beginning, but we want them at the end
							$mergedConfig[$key] = array_merge($parentConfig[$key], $wlConfig[$key]);
						}
					}

					$wlConfig = $mergedConfig;

				} else {
					$wlConfig = Helpers::merge($wlConfig, $defaultConfig);
				}

				$this->addWebLoader($builder, $type . ucfirst($name), $wlConfig);
				$loaderFactoryTempPaths[strtolower($name)] = $wlConfig['tempPath'];

				if (!is_dir($wlConfig['tempDir']) || !is_writable($wlConfig['tempDir'])) {
					throw new CompilationException(sprintf("You must create a writable directory '%s'", $wlConfig['tempDir']));
				}
			}
		}

		$builder->addDefinition($this->prefix('factory'))
			->setClass('WebLoader\Nette\LoaderFactory', array($loaderFactoryTempPaths));
	}

	private function addWebLoader(ContainerBuilder $builder, $name, $config)
	{
		$filesServiceName = $this->prefix($name . 'Files');

		$files = $builder->addDefinition($filesServiceName)
			->setClass('WebLoader\FileCollection')
			->setArguments(array($config['sourceDir']));

		foreach ($config['files'] as $file) {
			// finder support
			if (is_array($file) && isset($file['files']) && (isset($file['in']) || isset($file['from']))) {
				$finder = Finder::findFiles($file['files']);

				if (isset($file['exclude'])) {
					$finder->exclude($file['exclude']);
				}

				if (isset($file['in'])) {
					$finder->in(is_dir($file['in']) ? $file['in'] : $config['sourceDir'] . DIRECTORY_SEPARATOR . $file['in']);
				} else {
					$finder->from(is_dir($file['from']) ? $file['from'] : $config['sourceDir'] . DIRECTORY_SEPARATOR . $file['from']);
				}

				foreach ($finder as $foundFile) {
					/** @var \SplFileInfo $foundFile */
					$files->addSetup('addFile', array($foundFile->getPathname()));
				}

			} else {
				$this->checkFileExists($file, $config['sourceDir']);
				$files->addSetup('addFile', array($file));
			}
		}

		$files->addSetup('addRemoteFiles', array($config['remoteFiles']));

		$compiler = $builder->addDefinition($this->prefix($name . 'Compiler'))
			->setClass('WebLoader\Compiler')
			->setArguments(array(
				'@' . $filesServiceName,
				$config['namingConvention'],
				$config['tempDir'],
			));

		$compiler->addSetup('setJoinFiles', array($config['joinFiles']));

		foreach ($config['filters'] as $filter) {
			$compiler->addSetup('addFilter', array($filter));
		}

		foreach ($config['fileFilters'] as $filter) {
			$compiler->addSetup('addFileFilter', array($filter));
		}

		// todo css media
	}

	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$meta = $class->properties['meta'];
		$meta->value['types']['webloader\\loaderfactory'] = $meta->value['types']['webloader\\nette\\loaderfactory'];

		$init = $class->methods['initialize'];
		$init->addBody('if (!class_exists(?, ?)) class_alias(?, ?);', array('WebLoader\\LoaderFactory', FALSE, 'WebLoader\\Nette\\LoaderFactory', 'WebLoader\\LoaderFactory'));
	}

	public function install(Configurator $configurator)
	{
		$self = $this;
		$configurator->onCompile[] = function ($configurator, Compiler $compiler) use ($self) {
			$compiler->addExtension($self::EXTENSION_NAME, $self);
		};
	}

	/**
	 * @param string $file
	 * @param string $sourceDir
	 * @throws FileNotFoundException
	 */
	protected function checkFileExists($file, $sourceDir)
	{
		if (!file_exists($file)) {
			$tmp = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . $file;
			if (!file_exists($tmp)) {
				throw new FileNotFoundException(sprintf("Neither '%s' or '%s' was found", $file, $tmp));
			}
		}
	}

}
