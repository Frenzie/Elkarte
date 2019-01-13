<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for the GD library
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Graphics;

class Gd2 extends AbstractManipulator
{
	public function __construct($image)
	{
		$this->setSource($image);

		try
		{
			$this->memoryCheck();
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Used to determine if the GD2 library is present.
	 *
	 * @return bool Whether or not GD is available.
	 */
	public static function canUse()
	{
		// Check to see if GD is installed and what version.
		if (($extensionFunctions = get_extension_funcs('gd')) === false)
		{
			return false;
		}

		return true;
	}

	/**
	 * Sets the internal GD image resource.
	 *
	 * @param resource $image
	 */
	protected function _setImage($image)
	{
		$this->_image = $image;

		// Get the image size via GD functions
		$this->_width = imagesx($image);
		$this->_height = imagesy($image);
	}

	public function setSource($source)
	{
		$this->_fileName = $source;
	}

	public function createImageFromFile()
	{
		$this->getSize();

		if (isset(Image::DEFAULT_FORMATS[$this->_sizes[2]]))
		{
			try
			{
				$imagecreatefrom = 'imagecreatefrom' . Image::DEFAULT_FORMATS[$this->_sizes[2]];

				$this->_image = $imagecreatefrom($this->_fileName);
			}
			catch (\Exception $e)
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		return true;
	}

	public function createImageFromWeb()
	{
		require_once(SUBSDIR . '/Package.subs.php');
		$this->_image = fetch_web_data($this->_fileName);

		$this->getSize('string');
		if (isset(Image::DEFAULT_FORMATS[$this->_sizes[2]]))
		{
			try
			{
				$this->_image = imagecreatefromstring($this->_image);
			}
			catch (\Exception $e)
			{
				unset($this->_image);

				return false;
			}
		}
		else
		{
			unset($this->_image);

			return false;
		}

		return true;
	}

	/**
	 * Resize an image.
	 *
	 * What it does:
	 *
	 * - Puts the resized image at the destination location.
	 * - The file would have the format preferred_format if possible,
	 * otherwise the default format is jpeg.
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param int $preferred_format Used by Imagick/resizeImage
	 * @param bool $strip Allow IM to remove exif data as GD always will
	 * @param bool $force_resize Always resize the image (force scale up)
	 *
	 * @return boolean Whether the thumbnail creation was successful.
	 * @throws \Exception
	 */
	public function resizeImageFile($max_width, $max_height, $preferred_format = 0, $strip = false, $force_resize = true)
	{
		if (!empty($this->_image))
		{
			return $this->resizeImage($max_width, $max_height, $force_resize, $preferred_format, $strip);
		}

		return false;
	}

	/**
	 * Resize an image proportionally to fit within the defined max_width and max_height limits
	 *
	 * What it does:
	 *
	 * - Will do nothing to the image if the file fits within the size limits
	 * - Saves the new image to destination_filename, in the preferred_format
	 * if possible, default is jpeg.
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $force_resize = false Whether to override defaults and resize it
	 * @param int $preferred_format - The preferred format
	 *   - 0 to use jpeg
	 *   - 1 for gif
	 *   - 2 to force jpeg
	 *   - 3 for png
	 *   - 6 for bmp
	 *   - 15 for wbmp
	 * @param bool $strip Whether to have IM strip EXIF data as GD will
	 *
	 * @return bool Whether resize was successful.
	 */
	protected function resizeImage($max_width, $max_height, $force_resize = false, $preferred_format = 0, $strip = false)
	{
		$src_width = $this->_width;
		$src_height = $this->_height;

		// It should never happen, but let's keep these two as a failsafe
		$max_width = $max_width === null ? $src_width : $max_width;
		$max_height = $max_height === null ? $src_height : $max_height;

		// Determine whether to resize to max width or to max height (depending on the limits.)
		if (!empty($max_width) || !empty($max_height))
		{
			$image_ratio = $this->_width / $this->_height;
			$requested_ratio = $max_width / $max_height;

			if ($requested_ratio > $image_ratio)
			{
				$dst_width = max(1, $max_height * $image_ratio);
				$dst_height = $max_height;
			}
			else
			{
				$dst_width = $max_width;
				$dst_height = max(1, $max_width / $image_ratio);
			}

			// Don't bother resizing if it's already smaller...
			if (!empty($dst_width) && !empty($dst_height) && ($dst_width < $src_width || $dst_height < $src_height || $force_resize))
			{
				// Make a true color image, because it just looks better for resizing.
				$dst_img = imagecreatetruecolor($dst_width, $dst_height);
				imagesavealpha($dst_img, true);
				$color = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
				imagefill($dst_img, 0, 0, $color);

				// Resize it!
				imagecopyresampled($dst_img, $this->_image, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
			}
			else
			{
				$dst_img = $this->_image;
			}
		}
		else
		{
			$dst_img = $this->_image;
		}

		// Update things to the converted image
		$this->_setImage($dst_img);
	}

	public function output($file_name, $preferred_format = null, $quality = 85)
	{
		if (empty($file_name))
		{
			// Dump to browser instead ??
			return false;
		}

		// Save the image as ...
		$success = false;
		if (!empty($preferred_format))
		{
			if ($preferred_format == IMAGETYPE_PNG && function_exists('imagepng'))
			{
				$success = imagepng($this->_image, $file_name);
			}
			elseif ($preferred_format == IMAGETYPE_GIF && function_exists('imagegif'))
			{
				$success = imagegif($this->_image, $file_name);
			}
		}
		elseif (function_exists('imagejpeg'))
		{
			$success = imagejpeg($this->_image, $file_name, $quality);
		}

		return $success;
	}

	public function getOrientation()
	{
		// Read the EXIF data
		$exif = function_exists('exif_read_data') ? @exif_read_data($this->_fileName) : array();

		$this->_orientation = isset($exif['Orientation']) ? $exif['Orientation'] : 0;

		// We're only interested in the exif orientation
		return $this->_orientation;
	}

	/**
	 * Autorotate an image based on its EXIF Orientation tag.
	 *
	 * What it does:
	 *
	 * - Checks exif data for orientation flag and rotates image so its proper
	 * - Does not update the orientation flag as GD also removes EXIF data
	 * - Only works with jpeg images, could add TIFF as well
	 * - Writes the updated image back to $image_name
	 *
	 * @return bool
	 * @throws \Exception
	 */
	function autoRotateImage()
	{
		if (!isset($this->_orientation))
		{
			$this->getOrientation();
		}

		// For now we only process jpeg images, so check that we have one
		$this->getSize();

		// Not a jpeg or not rotated, done!
		if ($this->_sizes[2] !== 2 || $this->_orientation === 0 || $this->memoryCheck() === false)
		{
			return false;
		}

		// Time to spin and mirror as needed
		switch ($this->_orientation)
		{
			// 0 & 1 Not set or Normal
			case 0:
			case 1:
				break;
			// 2 Mirror image, Normal orientation
			case 2:
				$this->flopImage();
				break;
			// 3 Normal image, rotated 180
			case 3:
				$this->rotateImage(180);
				break;
			// 4 Mirror image, rotated 180
			case 4:
				$this->flipImage();
				break;
			// 5 Mirror image, rotated 90 CCW
			case 5:
				$this->flopImage();
				$this->rotateImage(90);
				break;
			// 6 Normal image, rotated 90 CCW
			case 6:
				$this->rotateImage(-90);
				break;
			// 7 Mirror image, rotated 90 CW
			case 7:
				$this->flopImage();
				$this->rotateImage(-90);
				break;
			// 8 Normal image, rotated 90 CW
			case 8:
				$this->rotateImage(90);
				break;
		}

		return true;
	}

	/**
	 * Rotate an image by X degrees, GD function
	 *
	 * @param int $degrees
	 */
	protected function rotateImage($degrees)
	{
		// Kind of need this to do anything
		if (function_exists('imagerotate'))
		{
			// Use positive degrees so GD does not get confused
			$degrees -= floor($degrees / 360) * 360;

			// Rotate away
			$background = imagecolorallocatealpha($this->_image, 255, 255, 255, 127);
			$this->_image = imagerotate($this->_image, $degrees, $background);
		}
	}

	/**
	 * Flop an image using GD functions by copying top to bottom / flop
	 */
	protected function flopImage()
	{
		$this->flipImage('horizontal');
	}

	/**
	 * Flip an image using GD function by copying top to bottom / flip vertical
	 *
	 * @param string $axis vertical for flip about vertical otherwise horizontal flip
	 */
	protected function flipImage($axis = 'vertical')
	{
		imageflip($this->_image, $axis === 'vertical' ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
	}

	/**
	 * Simple function to generate an image containing some text.
	 * It uses preferentially Imagick if present, otherwise GD.
	 * Font and size are fixed.
	 *
	 * @param string $text The text the image should contain
	 * @param int $width Width of the final image
	 * @param int $height Height of the image
	 * @param string $format Type of the image (valid types are png, jpeg, gif)
	 *
	 * @return resource|boolean The image
	 */
	public function generateTextImage($text, $width = 100, $height = 100, $format = 'png')
	{
		global $settings;

		$create_function = 'image' . $format;

		// Create a white filled box
		$image = imagecreate($width, $height);
		imagecolorallocate($image, 255, 255, 255);

		$text_color = imagecolorallocate($image, 0, 0, 0);
		$font = $settings['default_theme_dir'] . '/fonts/VDS_New.ttf';

		// The loop is to try to fit the text into the image.
		$true_type = function_exists('imagettftext');
		$font_size = $true_type ? 28 : 5;
		do
		{
			if ($true_type)
			{
				$metric = imagettfbbox($font_size, 0, $font, $text);
				$text_width = abs($metric[4] - $metric[0]);
				$text_height = abs($metric[5] - $metric[1]);
			}
			else
			{
				$text_width = imagefontwidth($font_size) * strlen($text);
				$text_height = imagefontheight($font_size);
			}
		} while ($text_width > $width && $font_size-- > 1);

		$w_offset = ($width - $text_width) / 2;
		$h_offset = $true_type ? ($height / 2) + ($text_height / 2) : ($height - $text_height) / 2;

		if ($true_type)
		{
			imagettftext($image, $font_size, 0, $w_offset, $h_offset, $text_color, $font, $text);
		}
		else
		{
			imagestring($image, $font_size, $w_offset, $h_offset, $text, $text_color);
		}

		// Capture the image string
		ob_start();
		$result = $create_function($image);
		$image = ob_get_contents();
		ob_end_clean();

		return $result ? $image : false;
	}
}
