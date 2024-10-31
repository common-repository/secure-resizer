<?php

/*
Plugin Name: Secure Image Resizer
Plugin URI: http://siteorigin.com/secure-resizer
Description: A very simple and secure image resizer.
Author: Greg Priday
Version: 0.1
Author URI: http://siteorigin.com/
License: GPL 2.0
*/

/**
 * If a downsized version of an image is not available, then it's created.
 *
 * @param $n
 * @param $id
 * @param string $size
 *
 * @return array|bool
 */
function so_resize_image_downsize($n, $id, $size = 'medium'){
	// Check if the resized version exists
	$meta = wp_get_attachment_metadata($id);
	if(is_array($size) || !empty($meta['sizes'][$size])) return false;
	
	// We'll dynamically resize this image
	$upload_dir = wp_upload_dir();
	$original = $upload_dir['basedir'].'/'.$meta['file'];
	
	global $_wp_additional_image_sizes;
	if(empty($_wp_additional_image_sizes[$size])) return false;
	$dim = $_wp_additional_image_sizes[$size];
	$new = image_resize(
		$original,
		$dim['width'],
		$dim['height'],
		$dim['crop'],
		null,
		dirname($original)
	);
	
	// Save this size to the meta
	list($width, $height) = getimagesize($new);
	$new_meta = $meta;
	@$new_meta['sizes'][$size] = array(
		'file' => basename($new),
		'width' => $width,
		'height' => $height
	);
	update_post_meta($id, '_wp_attachment_metadata', $new_meta, $meta);
	
	return array(
		$upload_dir['baseurl'].'/'.dirname($meta['file']).'/'.basename($new),
		$width,
		$height,
		true
	);
}
add_action('image_downsize', 'so_resize_image_downsize', 10, 3);

/**
 * Gets the data of a custom image resize. Gives more settings.
 *
 * @param id $id The meta ID
 * @param string $name A unique name for the resize
 * @param array $settings The settings for the resize
 * 
 * @return array Contains the URL and size of the image
 */
function so_resize_custom($id, $name, $settings = array()){
	$resizes = $original_resizes = (array) get_post_meta($id, 'so_custom_resize', true);
	
	$upload = wp_upload_dir();
	$attachment_meta = wp_get_attachment_metadata($id);
	$attachment_post = get_post($id);

	$input = path_join($upload['basedir'], $attachment_meta['file']);
	
	$settings = array_merge(array(
		'width' => 100,
		'height' => 100,
		'format' => 'jpg',
		'compression' => 80,
		'crop' => true,
		'gravity' => false,
	), $settings);

	// This resizer only uses Imagick
	if(!class_exists('Imagick')) return null;
	
	global $wp_filter;
	
	$key_base = $settings;
	if(isset($wp_filter['soresize_process_custom_testing']))
		$key_base['soresize_process_custom'] = array_keys($wp_filter['soresize_process_custom_testing']);
	if(isset($key_base['soresize_process_custom_'.$name]))
		$key_base['soresize_process_custom_'.$name] = array_keys($wp_filter['soresize_process_custom_'.$name]);
	
	if(!empty($resizes[$name])){
		if($resizes[$name]['key'] != md5(serialize($key_base))){
			// The key has changed, so delete the old file
			unlink(path_join($upload['basedir'], $resizes[$name]['file']));
			unset($resizes[$name]);
		}
		else{
			// We have a copy of this file already, return it
			return $resizes[$name];
		}
	}
	
	if(empty($resizes[$name]) || $resizes[$name]['key'] != md5(serialize($key_base))){
		/**
		 * @var int $width
		 * @var int $height
		 * @var bool $crop
		 * @var int $gravity
		 * @var int $format
		 * @var int $compression
		 */
		extract($settings);
		$im = new Imagick();
		$im->readimage($input);

		if(!empty($crop)) {
			$meta = array(
				'width' => $im->getImageWidth(),
				'height' => $im->getImageHeight(),
			);

			$in_ratio = $meta['width'] / $meta['height'];
			$out_ratio = $width / $height;
			if($in_ratio > $out_ratio){
				$crop_size = array(
					'width' => $meta['height']*$out_ratio,
					'height' => $meta['height']
				);
			}
			else{
				$crop_size = array(
					'width' => $meta['width'],
					'height' => $meta['width'] / $out_ratio
				);
			}

			switch($gravity){
				case Imagick::GRAVITY_NORTHWEST:
					break;
				case Imagick::GRAVITY_NORTH:
					$x = ($meta['width'] / 2) - ($crop_size['width'] / 2);
					break;
				case Imagick::GRAVITY_NORTHEAST:
					$x = ($meta['width']) - $crop_size['width'];
					break;
				case Imagick::GRAVITY_WEST:
					$y = ($meta['height'] / 2) - ($crop_size['height'] / 2);
					break;
				case Imagick::GRAVITY_EAST:
					$x = $meta['width'] - $crop_size['width'];
					$y = ($meta['height'] / 2) - ($crop_size['height'] / 2);
					break;
				case Imagick::GRAVITY_SOUTHWEST:
					$x = 0;
					$y = $meta['height'] - $crop_size['height'];
					break;
				case Imagick::GRAVITY_SOUTH:
					$x = ($meta['width'] / 2) - ($crop_size['width'] / 2);
					$y = $meta['height'] - $crop_size['height'];
					break;
				case Imagick::GRAVITY_SOUTHEAST:
					$x = $meta['width'] - $crop_size['width'];
					$y = $meta['height'] - $crop_size['height'];
					break;
				case Imagick::GRAVITY_CENTER:
				default:
					$x = ($meta['width'] / 2) - ($crop_size['width'] / 2);
					$y = $meta['height'] / 2 - ($crop_size['height'] / 2);
					break;
			}
			$im->cropImage($crop_size['width'], $crop_size['height'], $x, $y);
			$im->thumbnailimage($width, $height);
		}
		else $im->thumbnailimage($width, $height, true);
		
		// Let other classes do some post processing
		do_action('soresize_process_custom', $im);
		do_action('soresize_process_custom_'.$name, $im);
		
		$im->setformat($format);
		$im->setcompressionquality($compression);
		
		$info = pathinfo(basename($attachment_meta['file']));
		$new_name = substr($info['basename'], 0, strlen($info['basename']) - strlen($info['extension']) - 1);
		$new_name .= '-'.$name.'.'.$format;
		
		$upload_file = wp_upload_bits($new_name,null,$im->getimageblob(), $attachment_post->post_date);
		
		// Update the meta
		$d = $im->getImageGeometry();
		$resizes[$name] = array(
			'key' => md5(serialize($key_base)),
			'width' => $d['width'],
			'height' => $d['height'],
			'file' => str_replace($upload['basedir'].'/', '', $upload_file['file']),
		);
		
		// Update the resize meta
		update_post_meta($id, 'so_custom_resize', $resizes, $original_resizes);
		return $resizes[$name];
	}
}

/**
 * Creates a custom resize and renders the HTML for an image.
 * 
 * @param $id
 * @param $name
 * @param array $settings
 * @param array $atts
 * 
 * @return string
 */
function so_resize_custom_html($id, $name, $settings = array(), $atts = array()){
	$upload = wp_upload_dir();

	$data = so_resize_custom($id, $name, $settings);
	error_log(print_r($data, true));
	
	/**
	 * @var int $width
	 * @var int $height
	 * @var string $file
	 */
	extract($data);
	
	$url = path_join($upload['baseurl'], $file);
	@$atts['class'] .= ' image-resize-'.$name;
	$atts_string = '';
	foreach($atts as $n => $v){
		$v = trim($v);
		$atts_string .= "{$n}='{$v}' ";
	}
	$atts_string = trim($atts_string);
	
	return "<img src='{$url}' width='{$width}' height='{$height}' {$atts_string} />";
}