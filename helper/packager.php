<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\skeleton\helper;

use phpbb\config\config;
use phpbb\di\service_collection;
use phpbb\template\context;
use phpbb\template\twig\environment;
use phpbb\template\twig\loader;
use phpbb\template\twig\twig;
use phpbb\user;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class packager
{
	protected $data = array();

	/** @var user */
	protected $user;

	/** @var ContainerInterface */
	protected $phpbb_container;

	/** @var service_collection */
	protected $collection;

	/**
	 * Constructor
	 *
	 * @param user $user User instance (mostly for translation)
	 * @param ContainerInterface $phpbb_container
	 * @param service_collection $collection
	 * @param string $root_path
	 */
	public function __construct(user $user, ContainerInterface $phpbb_container, service_collection $collection, $root_path)
	{
		$this->user = $user;
		$this->phpbb_container = $phpbb_container;
		$this->collection = $collection;
		$this->root_path = $root_path;
	}

	/**
	 * @return array
	 */
	public function get_composer_dialog_values()
	{
		return array(
			'author' => array(
				'author_name' => null,
				'author_email' => null,
				'author_homepage' => null,
				'author_role' => null,
			),
			'extension' => array(
				'vendor_name' => null,
				'extension_display_name' => null,
				'extension_name' => null,
				'extension_description' => null,
				'extension_version' => '1.0.0-dev',
				'extension_homepage' => null,
				'extension_time' => date('Y-m-d'),
			),
			'requirements' => array(
				'php_version' => '>=5.3.3',
				'phpbb_version_min' => '>=3.1.4',
				'phpbb_version_max' => '<3.2.0@dev',
			)
		);
	}

	/**
	 * @return array
	 */
	public function get_component_dialog_values()
	{
		$components = array();
		foreach ($this->collection as $service)
		{
			/** @var \phpbb\skeleton\skeleton $service */
			$components[$service->get_name()] = array(
				'default' => $service->get_default(),
				'dependencies' => $service->get_dependencies(),
				'files' => $service->get_files(),
			);
		}

		return $components;
	}

	/**
	 * @param array $data
	 */
	public function create_extension($data)
	{
		$ext_path = $this->root_path . 'store/tmp-ext/' . "{$data['extension']['vendor_name']}/{$data['extension']['extension_name']}/";
		$filesystem = new Filesystem();
		$filesystem->remove($this->root_path . 'store/tmp-ext');
		$filesystem->mkdir($ext_path);

		$config = new config(array(
			'load_tplcompile' => true,
			'tpl_allow_php' => false,
			'assets_version' => null,
		));

		if (phpbb_version_compare(PHPBB_VERSION, '3.2.0-dev', '<'))
		{
			$template_engine = new twig(
				$this->phpbb_container->get('path_helper'),
				$config,
				$this->user,
				new context()
			);
		}
		else
		{
			// Work-around for bug in phpBB 3.2.0-dev-3.2.0-b2
			$cache_dir = ($this->phpbb_container->hasParameter('core.cache_dir')) ? $this->phpbb_container->getParameter('core.cache_dir') : $this->phpbb_container->getParameter('core.root_path') . 'cache/';

			$template_engine = new twig(
				$this->phpbb_container->get('path_helper'),
				$config,
				new context(),
				new environment(
					$config,
					$this->phpbb_container->get('filesystem'),
					$this->phpbb_container->get('path_helper'),
					$this->phpbb_container,
					$cache_dir,
					$this->phpbb_container->get('ext.manager'),
					new loader(
						new \phpbb\filesystem\filesystem()
					)
				),
				$cache_dir,
				$this->phpbb_container->get('user')
			);
		}

		$template_engine->set_custom_style('skeletonextension', $this->root_path . 'ext/phpbb/skeleton/skeleton');

		$template_engine->assign_vars(array(
			'COMPONENT' => $data['components'],
			'EXTENSION' => $data['extension'],
			'REQUIREMENTS' => $data['requirements'],
			'AUTHORS' => $data['authors'],
		));

		$component_data = $this->get_component_dialog_values();
		$skeleton_files = array(
			'license.txt',
			'README.md',
		);

		foreach ($data['components'] as $component => $selected)
		{
			if ($selected && !empty($component_data[$component]['files']))
			{
				$skeleton_files = array_merge($skeleton_files, $component_data[$component]['files']);
			}
		}

		foreach ($skeleton_files as $file)
		{
			$template_engine->set_filenames(array('body' => $file . '.twig'));
			$body = $template_engine->assign_display('body');
			$filesystem->dumpFile($ext_path . $file, trim($body) . "\n");
		}

		$filesystem->dumpFile($ext_path . 'composer.json', $this->get_composer_json_from_data($data));
	}

	/**
	 * @param array $data
	 * @return string
	 */
	public function create_zip($data)
	{
		$zip_path = $this->root_path . 'store/tmp-ext/' . "{$data['extension']['vendor_name']}_{$data['extension']['extension_name']}-{$data['extension']['extension_version']}.zip";
		$ext_path = $this->root_path . 'store/tmp-ext/' . "{$data['extension']['vendor_name']}/{$data['extension']['extension_name']}/";

		$zip_archive = new \ZipArchive();
		$zip_archive->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		$finder = new Finder();
		$finder->ignoreDotFiles(false)
			->ignoreVCS(false)
			->files()
			->in($ext_path);

		foreach ($finder as $file)
		{
			$zip_archive->addFile(
				$file->getRealPath(),
				"{$data['extension']['vendor_name']}/{$data['extension']['extension_name']}/" . $file->getRelativePath() . '/' . $file->getFilename()
			);
		}

		$zip_archive->close();

		return $zip_path;
	}

	/**
	 * @param array $data
	 * @return string
	 */
	public function get_composer_json_from_data($data)
	{
		$composer = array(
			'name' => "{$data['extension']['vendor_name']}/{$data['extension']['extension_name']}",
			'type' => 'phpbb-extension',
			'description' => "{$data['extension']['extension_description']}",
			'homepage' => "{$data['extension']['extension_homepage']}",
			'version' => "{$data['extension']['extension_version']}",
			'time' => "{$data['extension']['extension_time']}",
			'license' => 'GPL-2.0',
			'authors' => array(),
			'require' => array(
				'php' => "{$data['requirements']['php_version']}",
			),
			'extra' => array(
				'display-name' => "{$data['extension']['extension_display_name']}",
				'soft-require' => array(
					'phpbb/phpbb' => "{$data['requirements']['phpbb_version_min']},{$data['requirements']['phpbb_version_max']}",
				),
			),
		);

		if (!empty($data['components']['build']))
		{
			$composer['require-dev']['phing/phing'] = '2.4.*';
		}

		foreach ($data['authors'] as $i => $author_data)
		{
			if ($data['authors'][$i]['author_name'] !== '')
			{
				$composer['authors'][] = array(
					'name' => "{$data['authors'][$i]['author_name']}",
					'email' => "{$data['authors'][$i]['author_email']}",
					'homepage' => "{$data['authors'][$i]['author_homepage']}",
					'role' => "{$data['authors'][$i]['author_role']}",
				);
			}
		}

		$body = json_encode($composer, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
		$body = str_replace('&lt;', '<', $body);
		$body = str_replace('&gt;', '>', $body);

		return $body;
	}
}
