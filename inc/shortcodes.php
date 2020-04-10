<?php
//
// Shortcodes Functions
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

class Shortcodes_Custom {

  //-----------------------------------------------------------------------------------
  //  Redirect
  //-----------------------------------------------------------------------------------

    public static function init() {
      add_shortcode( 'ga', array( __CLASS__, 'google_analytics' ) );
      add_shortcode( "animated-gif", array( __CLASS__, "animated_gif" ) );
    }

    public function __construct() {}

  //-----------------------------------------------------------------------------------
  //  Elements
  //-----------------------------------------------------------------------------------

    public static function animated_gif( $atts = '' ) {
      $value = shortcode_atts(array(
        'url' => '',
        'style' => 'max-height: 100%; width: auto;',
      ), $atts);

      return '<img src="' . $value['url'] . '" style="' . $value['style'] . '" />';
    }

    //------------------ Google Analytics ------------------//
    public static function google_analytics( $atts, $content = null ) {
      $sc = shortcode_atts( array(
        'section' => '',
        'cat' => '',
      ), $atts );

      if ( $sc['cat'] !== '' ) {
        $sc['cat'] .= ': ';
      }

      $types = array( 'a', 'button' );
      $search = array();
      $replace = array();
      foreach ( $types as $type ) {
        preg_match_all( '#<\s*?' . $type . '\b[^>]*>(.*?)</' . $type . '\b[^>]*>#s', $content, $$type, PREG_SET_ORDER );
        foreach ( $$type as $match ) {
          $html = '';
          if ( strpos( $match[1], '</i>' ) !== false ) {
            preg_match( '/i class="(.*?)"/', $match[1], $html_match );
            $html = ucwords( str_replace( '-', ' ', $html_match[1] ) );
          } elseif ( strpos( $match[1], '<img ' ) !== false ) {
            preg_match( '/src="(\S*?)"/', $match[1], $html_match );
            $html = ucwords( str_replace( '-', ' ', pathinfo( $html_match[1] )['filename'] ) );
          }
          $match[1] = trim( wp_kses( strip_lines( $match[1] ), array(), array() ) );
          $search[] = $match[0];
          // Replace
          $replace[] = str_replace( '<' . $type . ' ', '<' . $type . ' ga-on="click" ga-event-category="' . $sc['section'] . '" ga-event-action="' . $sc['cat'] . $match[1] . '" ', $match[0] );
        }
      }
      $content = str_replace( $search, $replace, $content );

      return do_shortcode( $content );
    }

}

Shortcodes_Custom::init();
