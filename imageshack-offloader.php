<?php
/*
Plugin Name: ImageShack Offloader
Version: 0.9b
Description: Offload your images to <a href="http://imageshack.us">ImageShack</a> to save server resources.
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/imageshack-offloader
Text Domain: imageshack-offloader

Copyright (C) 2009 scribu.net (scribu AT gmail DOT com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

imageShackCore::init();

class imageShackCore {
	static $options;

	static function init() {
		if ( self::$options != null )
			return;

		// Load scbFramework
		require_once(dirname(__FILE__) . '/scb/load.php');

		// Load options
		self::$options = new scbOptions('imageshack-offloader', __FILE__, array(
			'sizes' => array('full', 'large', 'medium', 'thumbnail'),
			'order' => 'newest',
			'interval' => 120,
			'login' => '',
			'unattached' => true
		));

		$offloader = new imageShackOffloader(self::$options);
		$display = new imageShackDisplay;

		// Set up cron
		$cron = new scbCron(__FILE__, array(
			'callback' => array($offloader, 'offload'),
			'interval' => self::$options->interval,
		));

		// Load settings page
		if ( is_admin() ) {
			require_once(dirname(__FILE__) . '/admin.php');
			new imageShackOffloaderAdmin(__FILE__, self::$options, $cron);
		}
	}

	static function get_meta_key($size) {
		return '_imageshack_' . $size;
	}

	static function get_where_clause() {
		$where = "
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE 'image/%'
		";

		if ( self::$options->unattached == FALSE )
			$where .= "AND post_parent > 0";

		return $where;
	}
}

class imageShackOffloader {
	private $options;

	function __construct($options) {
		$this->options = $options;

#if ( is_admin() )
#$this->offload();
	}

	function offload() {
		$this->udir = wp_upload_dir();

		$this->tmp_sizes = $this->options->sizes;
		shuffle($this->tmp_sizes);

		$this->_offload();
	}

	private function _offload() {
		global $wpdb;

		if ( empty($this->tmp_sizes) )
			return;

		echo $size = array_pop($this->tmp_sizes);
		$meta_key = imageShackCore::get_meta_key($size);
		$where = imageShackCore::get_where_clause();

		switch ($this->options->order) {
			case 'newest' :
				$orderby = 'ORDER BY post_date DESC';
				break;
			case 'random' :
				$orderby = 'ORDER BY RAND()';
				break;
			case 'oldest' :
				$orderby = 'ORDER BY post_date ASC';
				break;
		}

		$ids = $wpdb->get_col("
			SELECT ID
			FROM {$wpdb->posts}
			{$where}
			AND ID NOT IN (
				SELECT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = '{$meta_key}'
			)
			{$orderby}
			LIMIT 5
		");

		if ( empty($ids) )
			return $this->_offload();	// try with a different size

		foreach ( $ids as $id ) {
			if ( ! $file = $this->get_file_path($id, $size) )
				continue;

			if ( ! $url = $this->parse_imageshack_url($file) )
				continue;

			add_post_meta($id, $meta_key, $url, true);

			return;	// exit after first successful upload to prevent throtling
		}
	}

	function get_file_path($id, $size) {
		$meta = wp_get_attachment_metadata($id);
		$file = get_attached_file($id);

		if ( 'full' == $size )
			$path = $file;
		else {
			$file = str_replace(basename($file), $meta['sizes'][$size]['file'], $file);
			$path = path_join($this->udir['basedir'], $file);
		}

		if ( ! file_exists($path) )
			return false;

		return $path;
	}

	function parse_imageshack_url($path) {
		$post = array(
			'fileupload' => '@'.$path,
			'xml' => 'yes',
			'public' => 'no',
			'rembar' => 'yes',
		);

		if ( !empty($this->options->login) )
			$post['cookie'] = trim($this->options->login);

		if ( ! $r = $this->uploadToImageshack($post) )
			return false;

		$r = explode('<image_link>', $r);
		$r = explode('</image_link>', $r[1]);

		return $r[0];
	}

	function uploadToImageshack($post) {
		$ch = curl_init("http://www.imageshack.us/index.php");

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 240);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: '));

		$result = curl_exec($ch);

		if ( $err = curl_error($ch) )
			trigger_error($err, E_USER_WARNING);

		curl_close($ch);

		return $result;
	}
}


class imageShackDisplay {
	function __construct() {
		add_filter('image_downsize', array($this, 'image_downsize_filter'), 10, 3);
		add_filter('the_content', array($this, 'the_content_filter'), 20);
	}

	function the_content_filter($content) {
		return preg_replace_callback('/href=["\'](.*?)["\'][^>]*><img [^>]* size-(\w+) wp-image-(\d+)/', array($this, 'preg_callback'), $content);
	}

	function preg_callback($match) {
		$size = $match[2];
		$id = $match[3];

		if ( ! $url = $this->get_url($id, $size) )
			return $match[0];

		$old = $match[1];

		if ( $url == $old )		// should never happen
			return $match[0];

		$old_url = array("src='{$old}'", "src=\"{$old}\"");
		$new_url = array("src='{$url}'", "src=\"{$url}\"");

		return str_replace($old_url, $new_url, $match[0]);
	}

	function image_downsize_filter($data, $id, $size) {
		$url = $this->get_url($id, $size);

		if ( ! $url )
			return $data;

		$data[0] = $url;

		return $data;
	}

	static function get_url($id, $size) {
		return get_post_meta($id, imageShackCore::get_meta_key($size), true);
	}
}

function get_imageshack_url($id, $size = 'full') {
	return imageShackDisplay::get_url($id, $size);
}

