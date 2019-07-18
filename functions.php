<?php

if (!class_exists('Timber')) {
	add_action('admin_notices', function() {
		include get_stylesheet_directory() . '/inc/no-timber-admin.php';
	});
	add_filter('template_include', function($template) {
		return get_stylesheet_directory() . '/static/no-timber.html';
	});
	return;
}

use Timber\Site;
use Timber\Menu;
use Timber\Twig_Function;
use Timber\ImageHelper;

/* The folder(s) containing Twig templates. */
Timber::$dirname = array('templates');

/* Twig template cache */
Timber::$cache = defined(WP_DEBUG) ? WP_DEBUG : false;
Routes::map('maintenance/clear-twig-cache', function() {
	if (is_user_logged_in()) {
		$loader = new Timber\Loader();
		$loader->clear_cache_twig();
		echo 'Twig cache cleared.';
		exit(0);
	}
});

class LatheSite extends Site {

	const ASSETS_PATH = '/static/dist/';
	const MANIFEST_FILE = 'parcel-manifest.json';
	static $__manifest__ = false;
	
	/*
		The set of image sizes used for the `size()` Twig filter.
	 */
	const IMAGE_SIZES = array(
		'thumbnail' => [800, 600],
		'full' => [1920, 1280, 'center']
	);

	function __construct() {

		/*
			Configure the theme
			-------------------

			Setup the theme's capabilities and 
			attach hooks to relevant filters and actions.
		 */
		add_action('after_setup_theme', function() {
			/*
				Load translations
			 */
			load_theme_textdomain('lathe', get_template_directory() . '/languages');

			/*
				Enable support for post formats.
			 */
			add_theme_support('post-formats');

			/*
				Enable support for post thumbnails on posts and pages.
				
				https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
			 */
			add_theme_support('post-thumbnails');

			add_theme_support('menus');
			add_theme_support('html5', array(
				'comment-list', 
				'comment-form', 
				'search-form', 
				'gallery', 
				'caption'
			));

			/*
				Add support for responsive embedded content.
			 */
			add_theme_support('responsive-embeds');

			/*
				Add default posts and comments RSS feed links to head.
			 */
			add_theme_support('automatic-feed-links');

			/* 
				This denotes that the theme does not set its own 
				<title> tag, but rather lets WordPress (or plugins)
				decides what to show.

				The filters below allow us to control aspects
				of <title> generation from within the theme.
			*/
			add_theme_support('title-tag');

			add_filter('document_title_parts', function($title) {
				// Remove the tagline from the front page
				unset($title['tagline']);
				return $title;
			});

			add_filter('document_title_separator', function($sep) {
				return '·';
			});
		});

		/*
			Customize the WP Query object.

			This only applies to the main query on the page,
			and only on the user-facing website.
		 */
		add_action('pre_get_posts', function($query) {
			if (is_admin() || !$query->is_main_query()) {
				return;
			}

			/*
				For post type archives, ony show the top-level posts.
			 */
			if ($query->is_post_type_archive()) {
				if ($query->query_vars['post_parent'] == false) {
					$query->set('post_parent', 0);
				}
			}
		});

		/* 
			Fixes issue with nesting in the Menu editor
			Reference: https://core.trac.wordpress.org/ticket/18282
		*/
		add_filter('nav_menu_meta_box_object', function($obj) {
			$obj->_default_query = array('posts_per_page' => -1);
			return $obj;
		}, 9);

		/*
			Custom menu locations
			---------------------
		*/
		$menus = array(
			'main-menu' => __('Main Menu', 'lathe'),
			'footer-menu' => __('Footer Menu', 'lathe'),
			'social-menu' => __('Social Menu', 'lathe')
		);

		/* Register menu locations */
		add_action('init', function() use ($menus) {
			register_nav_menus($menus);
		});

		add_action('acf/init', function() {
			/* Allow ACF fields and field groups to be translated */
			add_filter('acf/settings/l10n_textdomain', function() {
				return 'lathe';
			});
			
			/* Create an Options Page */
			acf_add_options_page(array(
				'page_title' => __('Site Options', 'lathe'),
				'menu_title' => __('Site Options', 'lathe'),
				'menu_slug' => __('site-options', 'lathe'),
				'capability' => 'edit_posts',
				'redirect' => false
			));
		});

		add_filter('timber/context', function($context) use ($menus) {
			// All menus
			$context['menus'] = [];
			foreach(array_keys($menus) as $key) {
				$context['menus'][$key] = new Menu($key);
			}
			$context['menu'] = $context['menus']['main-menu'];

			// Site Settings
			if (function_exists('get_fields')) {
				$context['options'] = get_fields('option');
			}

			return $context;
		});

		add_filter('timber/twig', function($twig) {
			/* Twig Functions */
			$twig->addFunction(new Twig_Function('asset', array($this, 'asset')));

			/* Twig Filters */
			// TODO: This will need to be changed to Timber\Twig_Filter soon.
			$twig->addFilter(new Twig_SimpleFilter('size', array($this, 'size')));
			$twig->addFilter(new Twig_SimpleFilter('asset', array($this, 'asset')));

			return $twig;
		});

		parent::__construct();
	}

	function asset($handle, $enqueue = false) {

		// Manifest file has not been loaded yet, let's do that first.
		if (self::$__manifest__ === false) {
			$p = get_template_directory().self::ASSETS_PATH.self::MANIFEST_FILE;
			if (file_exists($p)) {
				self::$__manifest__ = json_decode(file_get_contents($p), TRUE);
			} else {
				self::$__manifest__ = NULL;
			}
		}

		// Manifest not found
		if (self::$__manifest__ === NULL) {
			trigger_error("Could not load manifest file", E_USER_WARNING);
			return;
		}

		// Handle not found in manifest
		if (!isset(self::$__manifest__[$handle])) {
			trigger_error("{$handle} is not defined as an asset", E_USER_WARNING);
			return;
		}

		$src = self::$__manifest__[$handle];
		$uri = get_template_directory_uri().self::ASSETS_PATH.$src;

		if ($enqueue === false) {
			return $uri;
		}

		if ($enqueue === true) {
			if (preg_match('/\.js$/i', $uri)) {
				wp_enqueue_script($handle, $uri);
			} else if (preg_match('/\.css$/i', $uri)) {
				wp_enqueue_style($handle, $uri);
			} else {
				trigger_error("Can't enqueue {$handle}", E_USER_WARNING);
			}
			return;
		}

		if ($enqueue === 'inline') {
			return file_get_contents(
				get_template_directory() . self::ASSETS_PATH . $src
			);
		}

		trigger_error("Undefined mode {$enqueue}", E_USER_WARNING);
	}

	function size($src, $size = '') {
		/*
			For SVG files, or for when the size was not found,
			just return the original image.
		 */
		$is_svg = preg_match('/[^\?]+\.svg\b/i', $src);
		if ($is_svg || !isset(self::IMAGE_SIZES[$size])) {
			return $src;
		}

		$dest = self::IMAGE_SIZES[$size];
		return ImageHelper::resize(
			$src,
			isset($dest[0]) ? $dest[0] : NULL, 
			isset($dest[1]) ? $dest[1] : NULL, 
			isset($dest[2]) ? $dest[2] : NULL
		);
	}
}

new LatheSite();
