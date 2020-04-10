<?php
//
// Local All Functions
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
//  Constants
//-----------------------------------------------------------------------------------

  if ( ! defined( 'ENV' ) ) {
    // Dev or Dist
    define( 'ENV', 'dist' );
  }

//-----------------------------------------------------------------------------------
//  Front-end
//-----------------------------------------------------------------------------------

  // Register Stylesheets and Scripts and Enqueue Livereload
  function local_all_include() {
    // Local Live Reload
    wp_register_script('livereload', 'http://localhost:35729/livereload.js?snipver=1', array(), null, true );
    wp_enqueue_script('livereload');
  }
  add_action( 'wp_enqueue_scripts', 'local_all_include');

//-----------------------------------------------------------------------------------
//  Mail
//-----------------------------------------------------------------------------------

  add_filter( 'wp_mail', function( $args ) {
    $args['to'] = get_option( 'admin_email' );
    return $args;
  }, 10, 1 );

//-----------------------------------------------------------------------------------
//  HTTP Only
//-----------------------------------------------------------------------------------

  add_filter( 'woocommerce_get_endpoint_url', function( $url ) {
    return str_replace( 'https', 'http', $url );
  }, 101, 1 );
