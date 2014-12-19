<?php
/*
Plugin Name: Evgeny Muravjov Typograph
Version: 0.1.0
Plugin URI: https://github.com/semencov/mdash-wordpress
Description: Russian typography with Evgeny Muravjov Typograph, http://mdash.ru/
Author: Yuri Sementsov
Author URI: http://semencov.com/
*/

require_once 'EMT.php';

class Mdash
{
	// Typograph Instance
	private $EMT;

	private $options = array();
	private $filters = array(
		'the_title',
		'the_content',
		'the_excerpt',
	);

	// Check if site is multilingual
	private $i18n = false;
	private $lang;
	private $langs = array();


	function __construct()
	{
		$this->EMT = new EMTypograph();

		// Add menu page.
		add_action('admin_menu', array(&$this, 'add_submenu'));
	
		// Settings API.
		add_action('admin_init', array(&$this, 'register_setting'));

		// load the values recorded.
		$this->load_emt_settings();

		$this->EMT->setup($this->options);

		// Apply Typograph to content and excerpt
		foreach ($this->filters as $filter) {
			add_filter($filter, array(&$this, 'apply_formatting'), 9);
		}

		// add_filter('the_title', array(&$this, 'apply_formatting'), 9);
		// add_filter('the_content', array(&$this, 'apply_formatting'), 9);
		// add_filter('the_excerpt', array(&$this, 'apply_formatting'), 9);	
	}

	// Register settings.
	function register_setting()
	{
		register_setting('_emt_options', '_emt_options', array(&$this, 'validate_settings'));
		register_setting('_emt_options', '_emt_filters');
	}

	function validate_settings( $input )
	{
		$options = get_option( '_emt_options' );

		foreach ( $this->options as $id ) {
			if ( isset( $options[$id] ) && !isset( $input[$id] ) )
				unset( $options[$id] );
		}
	
		return $input;
	}

	function add_submenu()
	{
		// Add submenu to "Settings" menu.
		add_submenu_page( 'options-general.php', 'Typograph', __('Typograph', 'mdash'), 'administrator', __FILE__, array(&$this, 'display_page') );
	}

	function apply_formatting( $text )
	{
		if ( !is_string( $text ) )
			return $text;

		$this->EMT->set_text( $text );
		return $this->EMT->apply();
	}

	
	function load_emt_settings()
	{
		$this->check_i18n_plugin();

		$options = get_option( '_emt_options' );
		$filters = get_option( '_emt_filters' );

		if (!$options) {
			$options = $this->load_emt_default_options();
			update_option('_emt_options', $options);
		}

		if (!$filters) {
			$filters = $this->filters;
			update_option('_emt_filters', $filters);
		}

		$this->options = $options;
		$this->filters = $filters;
	}

	function load_emt_default_options()
	{
		$emt_options = $this->EMT->get_options_list();
		$options = array();

		foreach ($emt_options['all'] as $key => $opt) {
			$options[$key] = isset($opt['disabled']) && $opt['disabled'] == true ? "off" : "on";
		}

		return $options;

	}

	function load_textdomain()
	{
		// Looking for a file with a name mdash-ru_RU.mo
		load_plugin_textdomain('mdash', false, dirname(plugin_basename(__FILE__)));
	}

	private function check_i18n_plugin()
	{	
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if (is_plugin_active('polylang/polylang.php')) {
			$this->i18n = "polylang";
			$this->lang = @pll_current_language();

			$this->langs = @pll_the_languages(array('raw' => 1));
		}
	}


	function display_page()
	{
		global $wp_filter;

		$emt_options = $this->EMT->get_options_list();
		
		$wp_filters = array_keys($wp_filter);
		sort($wp_filters);

		if (isset($_POST['options'])) {
			$opts = (array) $_POST['options'];

			$options = get_option( '_emt_options' );

			$options = array_fill_keys(array_keys($options), 'off');
			$options = array_replace($options, $opts);
			
			update_option('_emt_options', $options);
			$this->options = $options;
		}

		if (isset($_POST['filters'])) {
			$filters = (array) $_POST['filters'];

			update_option('_emt_filters', $filters);
			$this->filters = $filters;
		}


		// TODO: add Chosen Jquery Plugin

		?>

		<div class="wrap">
			<span style="display:block; padding-left: 5px; padding-bottom: 5px">
				<h2><?php _e("Evgeny Muravjov's Typograph Settings", 'mdash'); ?></h2>
			</span>
						
			<form method="post" action="">
				<?php settings_fields('_emt_options'); ?>

				<span style="display:block; padding-left: 5px; padding-bottom: 5px">
					<h3><?php _e('Typograph Appliance','mdash'); ?></h3>
				</span>

				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<strong><?php _e("Registered Filters", 'mdash'); ?></strong>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span><?php _e("Registered Filters", 'mdash'); ?></span></legend>
									
									<select name="filters[]" id="filters" multiple size="10">
									<?php foreach ($wp_filters as $filter) : ?>
										<option value="<?php echo $filter; ?>"<?php if (in_array($filter, $this->filters)) echo "selected"; ?>><?php echo $filter; ?></option>
									<?php endforeach; ?>
									</select>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>
				<br>

				<span style="display:block; padding-left: 5px; padding-bottom: 5px">
					<h3><?php _e('Typograph Options','mdash'); ?></h3>
				</span>

				<table class="form-table">
					<tbody>
					<?php foreach($emt_options['group'] as $group) : ?>
						<tr valign="top">
							<th scope="row">
								<strong><?php _e($group['title'], 'emt'); ?></strong>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span><?php _e($group['title'], 'emt'); ?></span></legend>
									
									<?php foreach ($group['options'] as $option) : ?>
									<label><input type="checkbox" value="on" name="options[<?php echo $option; ?>]" <?php if ($this->options[$option] == "on") echo "checked"; ?> /> <?php _e( $emt_options['all'][$option]['description'], 'emt' ); ?></label><br>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Update Settings') ?>" />
				</p>
			</form>
		</div>
					
		<style type="text/css"> 
			.showonhover {position: relative;}
			.showonhover .hovertext {
				 opacity: 0;
				top: -99999px;
				position:absolute;
				z-index:1000;
				border:1px solid #ffd971;
				background-color:#fffdce;
				padding:7.5px;
				width:170px;
				font-size: 0.90em;
				-webkit-transition: opacity 0.3s ease;
				-moz-transition: opacity 0.3s ease;
				-o-transition: opacity 0.3s ease;
				transition: opacity 0.3s ease;
			}
			.showonhover:hover .hovertext {opacity:1;top:0;}
			a.viewdescription {color:#999;}
			a.viewdescription:hover {background-color:#999; color: White;}
		</style> 
		
		<?php

	}

}

// Start Disable Updates Manager once all other plugins are fully loaded.
global $Mdash; $Mdash = new Mdash();
