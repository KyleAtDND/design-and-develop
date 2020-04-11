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
//  Update & Install Actions
//-----------------------------------------------------------------------------------

  add_action( 'upgrader_process_complete', function( $info ) {
    if ( ! isset( $info['result']['destination_name'] ) || $info['result']['destination_name'] !== 'design-and-develop' ) return;

    flush_dnd_htaccess();
    dnd_update_wp_config();
  }, 10 );

  add_action( 'after_switch_theme', function() {
    flush_dnd_htaccess();
    dnd_update_wp_config();
  }, 10 );

//-----------------------------------------------------------------------------------
//  WP-Config
//-----------------------------------------------------------------------------------

  function dnd_update_wp_config() {
    if ( ! class_exists( 'WPConfigTransformer' ) ) {
      include_once DND_LIBRARIES_PATH . 'wp-config-transformer.php';
    }
    $file = dnd_locate_wp_config();
    $config_transformer = new WPConfigTransformer( $file );
    $config_transformer->update( 'constant', 'WP_DEBUG', 'true', array( 'raw' => true ) );
    $config_transformer->update( 'constant', 'WP_DEBUG_LOG', 'true', array( 'raw' => true ) );
    $config_transformer->update( 'constant', 'WP_DEBUG_DISPLAY', 'false', array( 'raw' => true ) );
    $config_transformer->update( 'constant', 'SCRIPT_DEBUG', 'false', array( 'raw' => true ) );
    $config_transformer->update( 'constant', 'FORCE_SSL_ADMIN', 'true', array( 'raw' => true ) );
  }

//-----------------------------------------------------------------------------------
//  Find Files
//-----------------------------------------------------------------------------------

  function dnd_locate_wp_config() {
    if ( file_exists( ABSPATH . 'wp-config.php' ) )
      $path = ABSPATH . 'wp-config.php';
    elseif ( file_exists( ABSPATH . '../wp-config.php' ) && ! file_exists( ABSPATH . '/../wp-settings.php' ) )
      $path = ABSPATH . '../wp-config.php';
    else
      $path = false;

    if ( $path )
      $path = realpath( $path );

    return $path;
  }
