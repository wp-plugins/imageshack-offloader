<?php

class imageShackOffloaderAdmin extends scbOptionsPage {

	function __construct($file, $options, $cron) {
		// Load translations
		$this->textdomain = 'imageshack-offloader';
		$plugin_dir = basename(dirname($file));
		load_plugin_textdomain($this->textdomain, "wp-content/plugins/$plugin_dir/lang", "$plugin_dir/lang");

		$this->cron = $cron;

		$this->args = array(
			'page_title' => 'ImageShack Offloader',
		);

		add_action('wp_dashboard_setup', array($this, 'add_box'));

		parent::__construct($file, $options);
	}

	function setup() {}

	function get_sizes() {
		$sizes = array(
			'full' => __('full', $this->textdomain),
			'large' => __('large', $this->textdomain),
			'medium' => __('medium', $this->textdomain),
			'thumbnail' => __('thumbnail', $this->textdomain),
		);

		return apply_filters('imageshack_offloader_sizes', $sizes, $this->textdomain);
	}

	// Wrap a field in a table row
	function form_row_raw($title, $content) {
		return "\n<tr>\n\t<th scope='row'>{$title}</th>\n\t<td>\n\t\t{$content}\n\t</td>\n\n</tr>";
	}

	// Generates multiple rows and wraps them in a form table
	function table_wrap($content, $action = 'action', $value = 'Save Changes') {
		$output = "\n<table class='form-table'>" . $content . "\n</table>\n";
		$output .= $this->submit_button($action);

		return parent::form_wrap($output, $this->nonce);
	}

	function validate($new_options) {
		// Validate login
		$new_options['login'] = trim($new_options['login']);

		// Validate interval
		$new_options['interval'] = intval($new_options['interval']);

		if ( 0 == $new_options['interval'] )
			$new_options['interval'] = $this->options->interval;

		if ( $new_options['interval'] != $this->options->interval )
			$this->cron->reschedule(array('interval' => $new_options['interval']));

		return $new_options;
	}

	function page_content() {
		// Sizes
		$content =  "<p>" . __('Upload images with these sizes:', $this->textdomain) . "</p>\n";
		foreach ( $this->get_sizes() as $size => $l10n) {
			$checked = @in_array($size, $this->options->sizes) ? " checked='checked'" : '';
			$content .= "<p>" . $this->input(array(
				'type' => 'checkbox',
				'names' => 'sizes[]',
				'values' => $size,
				'desc' => $l10n,
				'extra' => $checked
			)) . "</p>\n";
		}
		$rows[] = $this->form_row_raw(__('Image sizes', $this->textdomain), $content);

		// Unattached
		$rows[] = $this->form_row(array(
			'title' => __('Unattached images', $this->textdomain),
			'type' => 'checkbox',
			'names' => 'unattached',
			'desc' => __('Also upload unattached images.', $this->textdomain)
		), $this->options->get());

		// Order
		$orders = array(
			'newest' => __('newest first', $this->textdomain),
			'random' => __('random', $this->textdomain),
			'oldest' => __('oldest first', $this->textdomain),
		);

		$content = '';
		foreach ( $orders as $val => $desc )
			$content .= "<p>" . $this->input(array(
				'type' => 'radio',
				'names' => 'order',
				'values' => $val,
				'desc' => $desc,
			), $this->options->get()) . "</p>\n";
		$rows[] = $this->form_row_raw(__('Upload order', $this->textdomain), $content);

		// Interval
		$rows[] = $this->form_row(array(
			'title' => __('Upload interval', $this->textdomain),
			'type' => 'text',
			'names' => 'interval',
			'values' => $this->options->interval,
			'extra' => "class='small-text'",
			'desc' => __('Try to upload an image every %input% seconds.', $this->textdomain)
		), $this->options->get());

		// Login
		$rows[] = $this->form_row(array(
			'title' => __('Registration code', $this->textdomain),
			'type' => 'text',
			'names' => 'login',
			'desc' => '<br/>' . __('Paste the registration code found on <a target="_blank" href="http://profile.imageshack.us/registration/">this page</a> on Imageshack, after logging in.', $this->textdomain),
			'extra' => "class='regular-text'", 
		), $this->options->get());

		echo $this->table_wrap(implode('', $rows));
	}

	//_________________________Box stuff_________________________

	function add_box() {
		if ( current_user_can('manage_options') )
			wp_add_dashboard_widget('offloaderdiv', 'Offloading status', array($this, 'stats'));
	}

	function stats() {
		global $wpdb;

		$total = $wpdb->get_var("
			SELECT COUNT(*)
			FROM {$wpdb->posts}
			" . imageShackCore::get_where_clause()
		);

		$data = $wpdb->get_results("
			SELECT meta_key AS size, COUNT(*) as count
			FROM {$wpdb->postmeta}
			WHERE meta_key LIKE '!_imageshack!_%' ESCAPE '!'
			GROUP BY meta_key
		");

		if ( empty($data) ) {
			echo "<p>No images offloaded yet</p>\n";
			return;
		}

		$sizes = $this->get_sizes();
		foreach ( $data as $row ) {
			$name = ucfirst($sizes[str_replace('_imageshack_', '', $row->size)]);
			$count = round($row->count * 100 / $total, 2);

			echo "<p><strong>$name</strong>: $count% ($row->count)</p>\n";
		}
	}
}

