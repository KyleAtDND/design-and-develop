<?php
//
// Images Functions
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
//  Images in Post
//-----------------------------------------------------------------------------------

  //------------------ Attachment by URL ------------------//
  function get_attachment_id_by_url_custom( $url ) {
    // Split the $url into two parts with the wp-content directory as the separator
    $parsed_url  = explode( parse_url( WP_CONTENT_URL, PHP_URL_PATH ), $url );
    // Get the host of the current site and the host of the $url, ignoring www
    $this_host = str_ireplace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );
    $file_host = str_ireplace( 'www.', '', parse_url( $url, PHP_URL_HOST ) );
    // Return nothing if there aren't any $url parts or if the current host and $url host do not match
    if ( ! isset( $parsed_url[1] ) || empty( $parsed_url[1] ) || ( $this_host != $file_host ) ) {
      return $url;
    }

    $key = sanitize_key( 'image_ids_' . substr( pathinfo($parsed_url[1], PATHINFO_FILENAME), 0, 2 ) );
    $md5 = md5( $parsed_url[1] );
    $cache = get_transient( $key );
    if ( $cache === false ) $cache = array();

    if ( ! isset( $cache[$md5] ) ) {
      $ext = pathinfo($parsed_url[1], PATHINFO_EXTENSION);
      $new_name = preg_replace( '/-\d*X\d*\.' . $ext . '$/', '.' . $ext, $parsed_url[1] );
      $new_name = preg_replace( '/^\/uploads\//', '', $new_name );

      $attachment = get_posts( array(
        'post_type' => 'attachment',
        'meta_key' => '_wp_attached_file',
        'meta_value' => $new_name,
        'meta_compare' => '=',
        'ep_integrate' => true,
      ) );
      if ( count( $attachment ) === 0 ) {
        $cache[$md5] = array(
          'id' => 0,
          'path' => $parsed_url[1],
        );
        $image_id = $url;
      } else {
        $attachment = reset( $attachment );
        $image_id = $attachment->ID;
        $cache[$md5] = array(
          'id' => $image_id,
          'path' => $parsed_url[1],
        );
      }
      set_transient( $key, $cache, 2592000 );
    } else {
      if ( $cache[$md5]['id'] == 0 ) return $url;
      $image_id = $cache[$md5]['id'];
    }

    return $image_id;
  }
