<?php

class imageShackOffloaderAdmin extends scbAdminPage {
	function setup() {
		// Load translations
		$plugin_dir = basename(dirname($file));
		load_plugin_textdomain('imageshack-offloader', "wp-content/plugins/$plugin_dir/lang", "$plugin_dir/lang");

		$this->args = array(
			'page_title' => 'ImageShack Offloader',
		);
	}

	static function get_sizes() {
		$sizes = array(
			'full' => __('full', 'imageshack-offloader'),
			'large' => __('large', 'imageshack-offloader'),
			'medium' => __('medium', 'imageshack-offloader'),
			'thumbnail' => __('thumbnail', 'imageshack-offloader'),
		);

		return apply_filters('imageshack_offloader_sizes', $sizes, 'imageshack-offloader');
	}

	function validate($new_options) {
		// Validate login
		$new_options['login'] = trim($new_options['login']);

		// Validate interval
		$new_options['interval'] = intval($new_options['interval']);

		if ( $new_options['interval'] != $this->options->interval && imageShackCore::$cron != NULL )
			imageShackCore::$cron->reschedule(array('interval' => $new_options['interval']));

		return $new_options;
	}

	function page_content() {
		// Sizes
		$content =  "<p>" . __('Upload images with these sizes:', 'imageshack-offloader') . "</p>\n";
		foreach ( $this->get_sizes() as $size => $l10n) {
			$checked = @in_array($size, $this->options->sizes) ? " checked='checked'" : '';
			$content .= $this->input(array(
				'type' => 'checkbox',
				'name' => 'sizes[]',
				'value' => $size,
				'desc' => "<p>%input% $l10n</p>\n",
				'extra' => $checked
			));
		}
		$rows[] = $this->row_wrap(__('Image sizes', 'imageshack-offloader'), $content);

		// Unattached
		$rows[] = $this->table_row(array(
			'title' => __('Unattached images', 'imageshack-offloader'),
			'type' => 'checkbox',
			'name' => 'unattached',
			'desc' => __('Also upload unattached images.', 'imageshack-offloader')
		));

		// Order
		$orders = array(
			'newest' => __('newest first', 'imageshack-offloader'),
			'random' => __('random', 'imageshack-offloader'),
			'oldest' => __('oldest first', 'imageshack-offloader'),
		);

		$content = '';
		foreach ( $orders as $val => $desc )
			$content .= $this->input(array(
				'type' => 'radio',
				'name' => 'order',
				'value' => $val,
				'desc' => "<p>%input% $desc</p>\n",
			));
		$rows[] = $this->row_wrap(__('Offload priority', 'imageshack-offloader'), $content);

		// Interval
		$rows[] = $this->table_row(array(
			'title' => __('Offload interval', 'imageshack-offloader'),
			'type' => 'text',
			'name' => 'interval',
			'extra' => "class='small-text'",
			'desc' => __('Try to offload an image every %input% seconds. <br>If you set it to 0, images will be offloaded faster, but your site will be slower.', 'imageshack-offloader')
		));

		// Login
		$rows[] = $this->table_row(array(
			'title' => __('Registration code', 'imageshack-offloader'),
			'type' => 'text',
			'name' => 'login',
			'desc' => '<br/>' . __('To put offloaded images into an account, paste the registration code found on <a target="_blank" href="http://profile.imageshack.us/registration/">this page</a> on Imageshack, after logging in.', 'imageshack-offloader'),
			'extra' => "class='regular-text'", 
		));

		echo $this->form_table_wrap(implode('', $rows));
	}
}


abstract class imageShackStats {
	static function init() {
		add_action('wp_dashboard_setup', array(__CLASS__, 'add_box'));
	}
	
	static function add_box() {
		if ( current_user_can('manage_options') )
			wp_add_dashboard_widget('offloaderdiv', __('Offloading status', 'imageshack-offloader'), array(__CLASS__, 'stats'));
	}

	static function stats() {
		global $wpdb;

		$total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}" . imageShackCore::get_where_clause());

		$data = $wpdb->get_results("
			SELECT meta_key AS size, COUNT(*) as count
			FROM {$wpdb->postmeta}
			" . imageShackCore::where_key . "
			GROUP BY meta_key
		");

		if ( empty($data) ) {
			echo "<p>No images offloaded yet</p>\n";
			return;
		}

		$sizes = imageShackOffloaderAdmin::get_sizes();

		foreach ( $data as $row ) {
			$size = str_replace('_imageshack_', '', $row->size);
			$name = ucfirst($sizes[$size]);
			$count = $row->count ? round($row->count * 100 / $total, 2) : 0;

			echo "<p><strong>$name</strong>: $count% ($row->count)</p>\n";
		}
	}
}

