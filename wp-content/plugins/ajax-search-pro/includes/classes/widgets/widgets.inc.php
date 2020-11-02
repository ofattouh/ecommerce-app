<?php
if (!defined('ABSPATH')) die('-1');

require_once(ASP_CLASSES_PATH . "widgets/class-search-widget.php");
require_once(ASP_CLASSES_PATH . "widgets/class-last_searches-widget.php");
require_once(ASP_CLASSES_PATH . "widgets/class-top_searches-widget.php");

add_action( 'widgets_init', create_function( '', 'return register_widget("AjaxSearchProWidget");' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("AjaxSearchProLastSearchesWidget");' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("AjaxSearchProTopSearchesWidget");' ) );