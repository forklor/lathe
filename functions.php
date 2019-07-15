<?php

if (!class_exists('Timber')) {
	add_action('admin_notices', function() {
		echo '<div class="error"><p>Timber not activated. Make sure you activate the plugin in <a href="' . esc_url( admin_url( 'plugins.php#timber' ) ) . '">' . esc_url( admin_url( 'plugins.php') ) . '</a></p></div>';
	});
	
	add_filter('template_include', function($template) {
		return get_stylesheet_directory() . '/static/no-timber.html';
	});
	
	return;
}

/*
	"Coming Soon" page for non-logged-in users.
	You can comment this out when you're ready.
 */
if (!is_user_logged_in() && !is_admin()) {
	add_filter('template_include', function($template) {
		return get_stylesheet_directory() . '/coming-soon.php';
	});
}

Timber::$dirname = 'templates';

class LatheSite extends Timber\Site {

	function __construct() {
		add_theme_support('post-formats');
		add_theme_support('post-thumbnails');
		add_theme_support('menus');
		add_theme_support('html5', array(
			'comment-list', 
			'comment-form', 
			'search-form', 
			'gallery', 
			'caption'
		));

		add_action('after_setup_theme', function() {
			load_theme_textdomain('lathe', get_template_directory() . '/languages');
		});
		
		parent::__construct();
	}
	
}

new LatheSite();
