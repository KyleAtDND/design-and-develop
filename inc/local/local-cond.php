<?php
//
// Local Cond Functions
//
// Package: Design & Develop
// Subpackage: Design And Develop
// Version: 1.0.0 - 14-04-2019
// Author: design & develop - kyle@designanddevelop.io/
// License: MIT
//

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

//-----------------------------------------------------------------------------------
//  Styles and Scripts
//-----------------------------------------------------------------------------------

  //------------------ Admin and Main - Local Only ------------------//
  // Register Stylesheets and Scripts and Enqueue Livereload
  function local_cond_include() {
    // Other
    wp_register_script( 'test', get_template_directory_uri() . '/inc/local/js/main/test.js', array( 'jquery' ), null, true );
    wp_enqueue_script( 'test' );
    wp_enqueue_style( 'test', get_template_directory_uri() . '/inc/local/css/test.css', array(), '1.0.0', 'all' );
  }
  add_action( 'wp_enqueue_scripts', 'local_cond_include' );
  add_action( 'admin_enqueue_scripts', 'local_cond_include' );

  //------------------ Local Versions ------------------//
  // CSS
  add_action( 'wp_enqueue_scripts', function() {
    // Register
    wp_register_style( 'style-main', get_template_directory_uri() . '/assets/css/dev/final/main.css', array(), null, 'all' );
  }, 1 );

  // JS
  add_action( 'wp_enqueue_scripts', function() {
    // Register
    wp_register_script( 'script-main', get_template_directory_uri() . '/assets/js/dev/final/main/main.scripts.js', array( 'jquery' ), null, true );
  }, 1 );
