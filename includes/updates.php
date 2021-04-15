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

  // Clear Cache on Updates
  add_action( 'upgrader_process_complete', function( $info, $test = '' ) {
    if ( get_class( $info ) !== 'Plugin_Upgrader' ) {
      return;
    }

    if ( class_exists( 'Swift_Performance_Cache' ) ) {
      Swift_Performance_Cache::clear_all_cache();
    }

    if ( class_exists( 'Nginx_Helper' ) ) {
      global $nginx_purger;
      $nginx_purger->purge_all();
    }

    wp_cache_flush();
  }, 0, );

  add_action( 'upgrader_process_complete', function( $info, $test = '' ) {
    if ( get_class( $info ) === 'Plugin_Upgrader' ) return;

    //dnd_flush_logs();
    //dnd_flush_htaccess();
    //dnd_update_wp_config();
  }, 10 );

  add_action( 'after_switch_theme', function() {
    //dnd_flush_logs();
    //dnd_flush_htaccess();
    //dnd_update_wp_config();
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
    $config_transformer->update( 'constant', 'DISALLOW_FILE_EDIT', 'true', array( 'raw' => true ) );
  }

//-----------------------------------------------------------------------------------
//  Flush Logs
//-----------------------------------------------------------------------------------

  function dnd_flush_logs() {
    file_put_contents( WP_CONTENT_DIR . '/debug.log', '' );
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
