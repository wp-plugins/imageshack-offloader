<?php
/*
Plugin Name: ImageShack Offloader
Version: 1.0.2
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

abstract class imageShackCore 
{
	static $options;
	static $cron;

	static function init()
	{
		require_once dirname(__FILE__) . '/scb/load.php';

		// Load options
		self::$options = new scbOptions('imageshack-offloader', __FILE__, array(
			'sizes' => array('full', 'large', 'medium', 'thumbnail'),
			'unattached' => true,
			'order' => 'newest',
			'use_transload' => true,
			'interval' => 10,
			'login' => '',
		));

		// Set up cron
		$callback = array('imageShackOffloader', 'offload');

		if ( self::$options->interval > 0 )
			self::$cron = new scbCron(__FILE__, array(
				'callback' => array('imageShackOffloader', 'offload'),
				'interval' => self::$options->interval,
			));
		else
			call_user_func($callback);

		// Load settings page
		if ( is_admin() )
		{
			require_once dirname(__FILE__) . '/admin.php';
			new imageShackOffloaderAdmin(__FILE__, self::$options);
			imageShackStats::init();
		}
		else
			imageShackDisplay::init();
	}

	static function get_meta_key($size)
	{
		return '_imageshack_' . $size;
	}

	static function get_where_clause()
	{
		$where = "
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE 'image/%'
		";

		if ( self::$options->unattached == FALSE )
			$where .= "AND post_parent > 0";

		return $where;
	}
}

abstract class imageShackOffloader 
{
	static $udir;

	static function offload()
	{
		self::$udir = wp_upload_dir();	

		$where = imageShackCore::get_where_clause();

		switch (imageShackCore::$options->order)
		{
			case 'newest' :
				$orderby = 'ORDER BY post_date DESC'; break;
			case 'random' :
				$orderby = 'ORDER BY RAND()'; break;
			case 'oldest' :
				$orderby = 'ORDER BY post_date ASC'; break;
		}

		$tmp_sizes = imageShackCore::$options->sizes;

		shuffle($tmp_sizes);

		do {
			$size = array_pop($tmp_sizes);

			if ( self::_offload($size, $where, $orderby) )
				return;
		} while ( ! empty($tmp_sizes) );
	}

	private static function _offload($size, $where, $orderby)
	{
		global $wpdb;

		$meta_key = imageShackCore::get_meta_key($size);

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
			return false;

		foreach ( $ids as $id )
		{
			if ( ! $url = self::parse_imageshack_url($id, $size) )
				continue;

			add_post_meta($id, $meta_key, $url, true);

			return true;	// exit after first successful upload to prevent throtling
		}
	}

	private static function parse_imageshack_url($id, $size)
	{
		$args = array(
			'xml' => 'yes',
			'public' => 'no',
			'rembar' => 'yes',
			'cookie' => trim(imageShackCore::$options->login)
		);

		if ( empty($args['cookie']) )
			unset($args['cookie']);

		if ( !$file = self::get_file_url($id, $size) )
			return false;

		$url = 'http://www.imageshack.us/transload.php';
		$args['url'] = $file;

		if ( ! $r = self::uploadToImageshack($url, $args) )
			return false;

		$r = explode('<image_link>', $r);
		$r = explode('</image_link>', $r[1]);

		return $r[0];
	}

	private static function uploadToImageshack($url, $args)
	{
		$response = wp_remote_post($url, array('body' => $args));

		return wp_remote_retrieve_body($response);
	}

	private static function get_file_url($id, $size)
	{
		if ( ! $file = image_downsize($id, $size) )
			return false;

		return $file[0];
	}
}


abstract class imageShackDisplay
{
	static function init()
	{
		add_filter('image_downsize', array(__CLASS__, 'image_downsize_filter'), 10, 3);
		add_filter('the_content', array(__CLASS__, 'the_content_filter'), 20);
	}

	static function the_content_filter($content)
	{
		$regex = '/href=["\'](.*?)["\'][^>]*><img [^>]* size-(\w+) wp-image-(\d+)/';
		return preg_replace_callback($regex, array(__CLASS__, 'preg_callback'), $content);
	}

	static function preg_callback($match)
	{
		$size = $match[2];
		$id = $match[3];

		if ( ! $url = self::get_url($id, $size) )
			return $match[0];

		$old = $match[1];

		if ( $url == $old )		// should never happen
			return $match[0];

		$old_url = array("src='{$old}'", "src=\"{$old}\"");
		$new_url = array("src='{$url}'", "src=\"{$url}\"");

		return str_replace($old_url, $new_url, $match[0]);
	}

	static function image_downsize_filter($data, $id, $size)
	{
		$url = self::get_url($id, $size);

		if ( ! $url )
			return $data;

		if ( false === $data )
		{
			// Hack so that we don't have to paste the whole function here
			remove_filter('image_downsize', array(__CLASS__, 'image_downsize_filter'), 10, 3);
			$data = image_downsize($id, $size);
			add_filter('image_downsize', array(__CLASS__, 'image_downsize_filter'), 10, 3);
		}

		$data[0] = $url;

		return $data;
	}

	static function get_url($id, $size)
	{
		return get_post_meta($id, imageShackCore::get_meta_key($size), true);
	}
}

function get_imageshack_url($id, $size = 'full')
{
	return imageShackDisplay::get_url($id, $size);
}

