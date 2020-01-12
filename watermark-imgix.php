<?php
/**
 * Plugin Name: Watermark Imgix
 * Description: Watermark uploaded images with the Imgix API
 * Author: Rick Osborne
 *
 * If you value your sanity, DO NOT use this plugin.
 * It works for me ... but probably won't for long.
 */

defined('ABSPATH') or die('No direct access');

final class WatermarkImgix {
	private static $instance = null;
	public $options = array();
	public $defaults = array(
		'options' => array(
			'imgix' => array(
				// this will be something like 'example.imgix.net'
				'hostname' => '',
				// this is the imgix API param
				'mark_scale' => 10
			),
			'local' => array(
				// this will be something like '/some/path/to/wp-content/uploads/'
				'content_path' => '',
				// There are two ways to do this:
				// 1. Pass in the full URL to your watermark on your own domain, like: https://example.com/images/watermark.png
				// -or-
				// 2. Using the imgix UI, set the `mark-base` param to that URL, then set this to '?'.
				// Functionally, they both do the same thing.
				'watermark_url' => ''
			)
		)
	);
	// set this to true for stupidly verbose logging messages.
	private $debug = false;

	public function __construct () {

		$options = get_option('watermark_imgix_options', $this->defaults['options']);
		$options['imgix'] = array_merge($this->defaults['options']['imgix'], isset($options['imgix']) ? $options['imgix'] : array());
		$options['local'] = array_merge($this->defaults['options']['local'], isset($options['local']) ? $options['local'] : array());
		$this->options = $options;

		add_action('wp_update_attachment_metadata', array($this, 'wp_update_attachment_metadata'), 5, 2);
//		update_option('watermark_imgix_options', $options);
	}

	public static function get_instance () {
		if (is_null(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * The WP media hook system is full of Lovecraftian madness.
	 * This hook gets called a number of times, once for each resizing.
	 * I assume this will get completely reworked in 6.0 because it is ridiculous.
	 */
	public function wp_update_attachment_metadata ($data = array(), $post_id = null) {
		$data = $this->watermark_file($data, true, $post_id);
		if (isset($data['sizes']) && is_array($data['sizes']) && count($data['sizes']) > 0) {
			foreach ($data['sizes'] as $size_id => $size) {
				$updated = $this->watermark_file($size);
				$data['sizes'][$size_id]['file'] = $updated['file'];
			}
		}
		return $data;
	}

	/**
	 * This does the actual work.
	 */
	public function watermark_file ($data = array(), $include_path = false, $post_id = null) {
		if (!isset($data['file'])) {
			return $data;
		}
		$file_name = basename($data['file']);
		if (substr_compare($file_name, 'wm-', 0) == 0) {
			return $data;
		}
		$this->debug_log('watermark_file', $file_name);
		$this->debug_log('wp_update_attachment_metadata', json_encode($data));
		if (!isset($this->options['local']['content_path'])) {
			$this->debug_log('wp_update_attachment_metadata', 'Expected a content_path!');
		}
		$content_path = $this->options['local']['content_path'];
		if (!isset($this->options['local']['watermark_url'])) {
			$this->debug_log('wp_update_attachment_metadata', 'Expected a watermark_url!');
		}
		$watermark_url = $this->options['local']['watermark_url'];
		if (!isset($this->options['imgix']['hostname'])) {
			$this->debug_log('wp_update_attachment_metadata', 'Expected a imgix/hostname!');
		}
		$imgix_hostname = $this->options['imgix']['hostname'];
		if (!isset($this->options['imgix']['mark_scale'])) {
			$this->debug_log('wp_update_attachment_metadata', 'Expected a imgix/mark_scale!');
		}
		$mark_scale = $this->options['imgix']['mark_scale'];
		$marked_filename = 'wm-' . $file_name;
		$upload_dir = wp_upload_dir();
		$subdir = substr($upload_dir['subdir'], 1);
		$api_url = 'https://' . $imgix_hostname . $content_path . $subdir . '/' . $file_name . '?mark=' . urlencode($watermark_url) . '&mark-scale=' . $mark_scale;
		$this->debug_log('wp_update_attachment_metadata', 'fetch ' . $api_url);
		$ch = curl_init($api_url);
		$marked_path = $upload_dir['path'] . '/' . $marked_filename;
		$fh = fopen($marked_path, 'w');
		curl_setopt($ch, CURLOPT_FILE, $fh);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		if (curl_error($ch)) {
			$this->debug_log('wp_update_attachment_metadata', 'curl error: ' . curl_error($ch));
			curl_close($ch);
			fclose($fh);
		} else {
			curl_close($ch);
			fflush($fh);
			fclose($fh);
			$upload_path = $upload_dir['path'] . '/' . $file_name;
			unlink($upload_path);
			$this->debug_log('wp_update_attachment_metadata', 'copy complete: ' . $upload_path . ' => ' . $marked_path);
			$data['file'] = $include_path ? $subdir . '/' . $marked_filename : $marked_filename;
			if (!is_null($post_id)) {
				// sooooo ... yeah.
				// The original file attachment name doesn't get updated when you update it at the top level.
				// So you have to hack it.  This is pure awesome.
				$attached_file = get_post_meta($post_id, '_wp_attached_file', true);
				if (isset($attached_file) && !empty($attached_file) && strcmp($attached_file, $data['file']) != 0) {
					update_post_meta($post_id, '_wp_attached_file', $data['file'], $attached_file);
				}
			}
		}
		return $data;
	}

	public function debug_log ($methodName, $msg = '') {
		if ($this->debug) {
			trigger_error('[wp-watermark-imgix] #' . $methodName . ' ' . $msg);
		}
	}
}

WatermarkImgix::get_instance();

