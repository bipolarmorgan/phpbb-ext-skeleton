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

use phpbb\exception\runtime_exception;
use phpbb\language\language;

class validator
{
	/** @var language */
	protected $language;

	/**
	 * Constructor
	 *
	 * @param language $language
	 */
	public function __construct(language $language)
	{
		$this->language = $language;
	}

	/**
	 * Validate the number of authors
	 * Should be between 0 and 20
	 *
	 * @param string $value The value to validate
	 * @throws runtime_exception
	 * @return string The valid value
	 */
	public function validate_num_authors($value)
	{
		if ($value > 0 && $value <= 20 && ctype_digit($value))
		{
			return $value;
		}

		throw new runtime_exception($this->language->lang('SKELETON_INVALID_NUM_AUTHORS'));
	}

	/**
	 * Validate the extension name
	 *
	 * @param string $value The value to validate
	 * @throws runtime_exception
	 * @return string The valid value
	 */
	public function validate_extension_name($value)
	{
		if (preg_match('#^[a-z][a-z0-9]*$#', $value))
		{
			return $value;
		}

		throw new runtime_exception($this->language->lang('SKELETON_INVALID_PACKAGE_NAME'));
	}

	/**
	 * Validate the extension display name
	 *
	 * @param string $value The value to validate
	 * @throws runtime_exception
	 * @return string The valid value
	 */
	public function validate_extension_display_name($value)
	{
		if ($value !== '' && strpos($value, '&quot;') === false)
		{
			return htmlspecialchars_decode($value, ENT_NOQUOTES);
		}

		throw new runtime_exception($this->language->lang('SKELETON_INVALID_DISPLAY_NAME'));
	}

	/**
	 * Validate the extension date/time
	 *
	 * @param string $value The value to validate
	 * @throws runtime_exception
	 * @return string The valid value
	 */
	public function validate_extension_time($value)
	{
		if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $value))
		{
			return $value;
		}

		throw new runtime_exception($this->language->lang('SKELETON_INVALID_EXTENSION_TIME'));
	}

	/**
	 * Validate the extension version number
	 *
	 * @param string $value The value to validate
	 * @throws runtime_exception
	 * @return string The valid value
	 */
	public function validate_extension_version($value)
	{
		if (preg_match('#^\d+(\.\d){1,3}(-(((?:a|b|RC|pl)\d+)|dev))?$#', $value))
		{
			return $value;
		}

		throw new runtime_exception($this->language->lang('SKELETON_INVALID_EXTENSION_VERSION'));
	}

	/**
	 * Validate the extension vendor name
	 *
	 * @param string $value The value to validate
	 * @throws runtime_exception
	 * @return string The valid value
	 */
	public function validate_vendor_name($value)
	{
		if ($value !== 'core' && preg_match('#^[a-z][a-z0-9]*$#', $value))
		{
			return $value;
		}

		throw new runtime_exception($this->language->lang('SKELETON_INVALID_VENDOR_NAME'));
	}
}
