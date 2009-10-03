<?php
/*
Plugin Name: ImageShack Offloader (alternative Cron handling)
Version: 1.1a
Description: Offload your images to <a href="http://imageshack.us">ImageShack</a> to save server resources.
Author: scribu
Author URI: http://scribu.net/
Plugin URI: http://scribu.net/wordpress/imageshack-offloader
Text Domain: imageshack-offloader
Domain Path: /lang

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


#DEBUG
scbCron::debug();

function count_schedules($name)
{
	$crons = _get_cron_array();

	foreach ( array_keys($crons) as $timestamp )
		if ( isset($crons[$timestamp][$name]) )
			$i++;

	debug($i);
}

function really_clear_scheduled_hook($name)
{
	$crons = _get_cron_array();

	foreach ( array_keys($crons) as $timestamp )
	{
		unset($crons[$timestamp][$name]);

		if ( empty($crons[$timestamp]) )
			unset($crons[$timestamp]);
	}

	_set_cron_array( $crons );
}
#DEBUG

imageShackCore::init();

abstract class imageShackCore
{
	static $options;
	const ver = '1.1';
	const where_posts = "
		WHERE post_type = 'attachment'
		AND post_status <> 'trash'
		AND post_mime_type LIKE 'image%'
	";

	const where_key = "WHERE meta_key LIKE '!_imageshack!_%' ESCAPE '!'";

	static function init()
	{
		// Load scbFramework
		require_once dirname(__FILE__) . '/scb/load.php';

		self::$options = new scbOptions('imageshack-offloader', __FILE__, array(
			'sizes' => array('full', 'large', 'medium', 'thumbnail'),
			'login' => '',
		));

		imageShackOffloader::init();
count_schedules(imageShackOffloader::hook);
#really_clear_scheduled_hook(imageShackOffloader::hook);
#imageShackOffloader::initial_offload();

		if ( is_admin() )
		{
			require_once dirname(__FILE__) . '/admin.php';

			new imageShackOffloaderAdmin(__FILE__, self::$options);
			imageShackStats::init();
		}

		imageShackDisplay::init();
#m		imageShackErrors::init();
	}

	static function get_meta_key($size)
	{
		return '_imageshack_' . $size;
	}
}

abstract class imageShackOffloader
{
	private static $initial = false;
	private static $cron;

	const hook = 'imageShack_offload_single';

	static function init()
	{
		add_action(self::hook, array(__CLASS__, 'do_job'), 10, 3);

		add_action('add_attachment', array(__CLASS__, 'offload'));

		self::$cron = new scbCron(__FILE__, array(
			'callback' => array(__CLASS__, 'initial_offload'),
			'schedule' => 'hourly',
		));

		register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
	}

	static function uninstall()
	{
		global $wpdb;
		
		$wpdb->query("DELETE FROM {$wpdb->postmeta}	" . imageShackCore::where_key);
	}

	// start offloading 100 attachments, with a slight delay.
	// on the last attachment, set flag to start again
	static function initial_offload()
	{
		self::$initial = true;

		global $wpdb;

		$ids = $wpdb->get_col("
			SELECT ID FROM {$wpdb->posts}
			" . imageShackCore::where_posts . "
			AND ID NOT IN (
				SELECT post_id
				FROM {$wpdb->postmeta}
			" . imageShackCore::where_key . "
			)
			LIMIT 1
		");

		if ( empty($ids) )
			return self::$cron->unschedule();

		$count = count($ids);
		for ($i = 0; $i < $count; $i++ )
		{
			if ( $i == $count - 1 )
				$continue = true;

			self::offload($ids[$i], $i * 5, $continue);
		}

		self::$initial = false;
	}

	// Offload a single attachment, with all intermediate sizes
	static function offload($id, $delay = -1, $continue = false)
	{
		if ( $delay < 0 && ! wp_attachment_is_image($id) )
			return;

		foreach ( imageShackCore::$options->sizes as $size )
		{
			$args = array($id, $size);

			if ( $continue )
			{
				$args[] = true;
				$continue = false;
			}

			wp_schedule_single_event( time() + $delay, self::hook, $args );
		}
	}

	static function do_job($id, $size, $continue = false)
	{
		$old = self::get_current_url($id, $size);

		if ( $new = self::get_new_url($old) )
			add_post_meta($id, $meta_key, $new, true);

#		if ( $continue )
#			self::initial_offload();
	}

	private static function get_current_url($id, $size)
	{
		if ( ! $file = image_downsize($id, $size) )
			return false;

		return $file[0];
	}

	private static function get_new_url($url)
	{
		if ( empty($url) || FALSE !== strpos($url, 'imageshack.us') )
			return false;

		$args = array(
			'xml' => 'yes',
			'public' => 'no',
			'rembar' => 'yes',
			'url' => $url,
			'cookie' => imageShackCore::$options->login
		);

		if ( empty($args['cookie']) )
			unset($args['cookie']);

		$response = wp_remote_post('http://www.imageshack.us/transload.php', array('body' => $args));

		if ( ! $r = wp_remote_retrieve_body($response) )
			return false;

		$r = explode('<image_link>', $r);
		$r = explode('</image_link>', $r[1]);

		return $r[0];
	}
}


// Replace images on the front-end
abstract class imageShackDisplay
{
	static function init()
	{
		add_filter('image_downsize', array(__CLASS__, 'image_downsize_filter'), 10, 3);
		add_filter('the_content', array(__CLASS__, 'the_content_filter'), 20);
	}

	static function the_content_filter($content)
	{
		$regex = '<img[^>]* size-(\w+) wp-image-(\d+)/';
		return preg_replace_callback($regex, array(__CLASS__, 'preg_callback'), $content);
	}

	static function preg_callback($match)
	{
		list ( $content, $size, $id ) = $match;

		list ( $old ) = self::image_downsize_unfiltered($id, $size);

		if ( ! $new = self::get_url($id, $size) || $new == $old )
			return $content;

		$old_url = array("src='{$old}'", "src=\"{$old}\"");
		$new_url = array("src='{$new}'", "src=\"{$new}\"");

		return str_replace($old_url, $new_url, $content);
	}

	static function image_downsize_filter($data, $id, $size)
	{
		$url = self::get_url($id, $size);

		if ( ! $url )
			return $data;

		if ( false === $data )
			$data = self::image_downsize_unfiltered($id, $size);

		$data[0] = $url;

		return $data;
	}

	private static function image_downsize_unfiltered($id, $size)
	{
		remove_filter('image_downsize', array(__CLASS__, 'image_downsize_filter'), 10, 3);
		$data = image_downsize($id, $size);
		add_filter('image_downsize', array(__CLASS__, 'image_downsize_filter'), 10, 3);

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


// Un-offload images that can't be loaded from ImageShack
abstract class imageShackErrors
{
	static function init()
	{
		add_action('template_redirect', array(__CLASS__, 'script'));
		add_action('wp_ajax_imageshack-offloader', array(__CLASS__, 'ajax_response'));
		add_action('wp_ajax_nopriv_imageshack-offloader', array(__CLASS__, 'ajax_response'));
	}

	static function script()
	{
		wp_enqueue_script('ishack-errors', plugin_dir_url(__FILE__) . 'inc/errors.js', array('jquery'), imageShackCore::ver, true);

		wp_localize_script('ishack-errors', 'iShackL10n', array(
			'ajax' => admin_url('admin-ajax.php')
		));
	}

	static function ajax_response()
	{
		global $wpdb;

		$urls = array_unique($_POST['urls']);

		if ( ! $urls )
			return;

		foreach ( $urls as $i => $url )
		{
			// see if the file is actually missing; wp_remote_head() gives "400 Bad Request"
			if ( FALSE !== @file_get_contents($url) )
				unset($urls[$i]);
			else
				$urls[$i] = "'" . $wpdb->escape($url) . "'";
		}

		$urls = implode(',', $urls);

		echo (int) $wpdb->query("
			DELETE FROM {$wpdb->postmeta}
			WHERE meta_key LIKE '!_imageshack!_%' ESCAPE '!'
			AND meta_value IN ($urls)
		");

		die;
	}
}

