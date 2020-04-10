<?php
//
// Content Functions
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
//  Post-Content
//-----------------------------------------------------------------------------------

  // Get by PID
  function get_the_content_custom( $pid ) {
    $content_post = ( is_object( $pid ) ) ? $pid : get_post( $pid );
    $content = $content_post->post_content;
    $content = apply_filters('the_content', $content);
    $content = str_replace(']]>', ']]&gt;', $content);
    return $content;
  }
