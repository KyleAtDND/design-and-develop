<?php
//
// Library Functions
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
//  Error Logging & Emails
//-----------------------------------------------------------------------------------

  //------------------ To Log ------------------//
  function write_log_custom( $log )  {
    if ( true === WP_DEBUG ) {
      if ( is_array( $log ) || is_object( $log ) ) {
        error_log( print_r( $log, true ) );
      } else {
        error_log( $log );
      }
    }
  }

  if ( ! function_exists( 'write_log' ) ) {
    function write_log( $log ) {
      return write_log_custom( $log );
    }
  }

  //------------------ Var Dump into Log ------------------//
  function var_error_log_custom( $object = null ){
    ob_start();                    // start buffer capture
    var_dump( $object );           // dump the values
    $contents = ob_get_contents(); // put the buffer into a variable
    ob_end_clean();                // end capture
    error_log( $contents );        // log contents of the result of var_dump( $object )
  }

  if ( ! function_exists( 'write_log' ) ) {
    function var_error_log( $log ) {
      return var_error_log_custom( $log );
    }
  }

//-----------------------------------------------------------------------------------
//  Expand Wordpress Core Functions
//-----------------------------------------------------------------------------------

  //------------------ Remove Action With Method Name Only ------------------//
  function remove_filters_with_method_name_custom( $hook_name = '', $method_name = '', $priority = 10 ) {
    global $wp_filter;

    // Take only filters on right hook name and priority
    if ( !isset($wp_filter[$hook_name][$priority]) || !is_array($wp_filter[$hook_name][$priority]) )
      return false;

    // Loop on filters registered
    foreach( (array) $wp_filter[$hook_name][$priority] as $unique_id => $filter_array ) {
      // Test if filter is an array ! (always for class/method)
      if ( isset($filter_array['function']) && is_array($filter_array['function']) ) {
        // Test if object is a class and method is equal to param !
        if ( is_object($filter_array['function'][0]) && get_class($filter_array['function'][0]) && $filter_array['function'][1] == $method_name ) {
          unset($wp_filter[$hook_name][$priority][$unique_id]);
        }
      }
    }
    return false;
  }

  //------------------ Remove Action with Class and Method Name ------------------//
  function remove_filters_for_class_custom( $tag, $class_name = '', $method_name = '', $priority = 10 ) {
    global $wp_filter;
    // Check that filter actually exists first
    if ( ! isset( $wp_filter[ $tag ] ) ) {
      return FALSE;
    }

    if ( is_object( $wp_filter[ $tag ] ) && isset( $wp_filter[ $tag ]->callbacks ) ) {
      // Create $fob object from filter tag, to use below
      $fob       = $wp_filter[ $tag ];
      $callbacks = &$wp_filter[ $tag ]->callbacks;
    } else {
      $callbacks = &$wp_filter[ $tag ];
    }
    // Exit if there aren't any callbacks for specified priority
    if ( ! isset( $callbacks[ $priority ] ) || empty( $callbacks[ $priority ] ) ) {
      return FALSE;
    }
    // Loop through each filter for the specified priority, looking for our class & method
    foreach ( (array) $callbacks[ $priority ] as $filter_id => $filter ) {
      // Filter should always be an array - array( $this, 'method' ), if not goto next
      if ( ! isset( $filter['function'] ) || ! is_array( $filter['function'] ) ) {
        continue;
      }
      // If first value in array is not an object, it can't be a class
      if ( ! is_object( $filter['function'][0] ) ) {
        continue;
      }
      // Method doesn't match the one we're looking for, goto next
      if ( $filter['function'][1] !== $method_name ) {
        continue;
      }
      // Method matched, now let's check the Class
      if ( get_class( $filter['function'][0] ) === $class_name ) {
        // WordPress 4.7+ use core remove_filter() since we found the class object
        if ( isset( $fob ) ) {
          // Handles removing filter, reseting callback priority keys mid-iteration, etc.
          $fob->remove_filter( $tag, $filter['function'], $priority );
        } else {
          // Use legacy removal process (pre 4.7)
          unset( $callbacks[ $priority ][ $filter_id ] );
          // and if it was the only filter in that priority, unset that priority
          if ( empty( $callbacks[ $priority ] ) ) {
            unset( $callbacks[ $priority ] );
          }
          // and if the only filter for that tag, set the tag to an empty array
          if ( empty( $callbacks ) ) {
            $callbacks = array();
          }
          // Remove this filter from merged_filters, which specifies if filters have been sorted
          unset( $GLOBALS['merged_filters'][ $tag ] );
        }
        return TRUE;
      }
    }
    return FALSE;
  }

//-----------------------------------------------------------------------------------
//  Add Core Functions
//-----------------------------------------------------------------------------------

  //------------------ Paths ------------------//
  function get_wordpress_path_custom() {
    $parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
    return chop($parse_uri[0], 'index.php' );
  }

  //------------------ Local vs Live ------------------//
  function is_local_custom() {
    $http_host = ( isset( $_SERVER['HTTP_HOST'] ) ) ? $_SERVER['HTTP_HOST'] : '';
    $explode = explode( '.', $http_host );

    if ( defined( 'IS_LOCAL' ) ) {
      return IS_LOCAL;
    } else {
      $option = get_option( 'is_local', '' );
      if ( $option === '' ) {
        update_option( 'is_local', 'no' );
      }

      if ( $option !== 'force' ) {
        if ( isset( $_SERVER['HTTP_HOST'] ) && strpos( substr( $http_host, -3 ), 'www' ) !== false ) {
          update_option( 'is_local', 'yes' );
          $option = 'yes';
        } elseif ( isset( $_SERVER['HTTP_HOST'] ) && strpos( substr( $http_host, -3 ), 'www' ) === false && $option === 'yes' ) {
          update_option( 'is_local', 'no' );
          $option = 'no';
        }
      } else {
        $option = 'yes';
      }

      $bool = ( $option === 'no' ) ? false : true;

      define( 'IS_LOCAL', $bool );

      return IS_LOCAL;
    }
  }

  function is_mac_custom() {
    if ( ! defined( 'IS_MAC' ) ) {
      $url = parse_url( home_url() )['host'];
      $url = explode( '.', $url );
      $extension = end( $url );

      if ( $extension === 'www' ) {
        $option = get_option( 'is_mac' );
        if ( $option === '' || $option === false ) {
          update_option( 'is_mac', 'no', 'yes' );
        }

        $bool = ( $option === 'no' ) ? false : true;
        define( 'IS_MAC', $bool );
      } else {
        define( 'IS_MAC', false );
      }
    }

    return IS_MAC;
  }

  //------------------ Check if It Is an Ancestor ------------------//
  function is_tree_custom( $pid ) {
    if ( get_post() ) {
      //$pid = The ID of the ancestor page
      global $post; //load details about this page
      if ( is_page() ) {
        $anc = get_post_ancestors( $post->ID );
        foreach($anc as $ancestor) {
          if( is_page() && $ancestor == $pid ) {
            return true;
          }
        }
      } elseif ( is_category() ) {
        //return true;
      } elseif ( is_archive() ) {
        global $page_id;
        if ( $page_id < 1 ) {
          $page_id = get_custom_page_info_custom(true);
        }
        if ( $page_id > 0 ) {
          if ( is_post_type_archive() && $page_id == $pid ) {
            return true;
          }
        }
      }
      if ( is_page() && ( is_page( $pid ) ) ) {
        return true; // we’re at the page or at a sub page
      }
    }
    return false;
  }

  //------------------ Convert Full URL to Relative Link ------------------//
  function remove_http_custom( $url ) {
    $url = parse_url( $url );
    $url = $url['path'];

    return $url;
  }

  //------------------ Page ID by Archive Slug ------------------//
  function get_custom_page_info_custom($pid_only = true, $post_type = false, $pid = '') {
    $wc_exists = ( function_exists( 'is_woocommerce' ) );
    $wc = ( $wc_exists && is_woocommerce() ) ? true : false;

    global $wp_query;
    if ( isset( $wp_query->query['error'] ) && $wp_query->query['error'] == 404 ) {
      $page_info = array(
        'post_type' => '',
        'parents' => '',
        'page_id' => '',
        'tax' => '',
      );

      return $page_info;
    }

    if ( isset( $_GET['post_type'] ) ) {
      $post_type = $_GET['post_type'];
    } elseif ( $post_type = get_post_type() ) {
      $post_type = get_post_type();
    } elseif ( isset( $wp_query->query['post_type'] ) ) {
      global $wp_query;
      $post_type = $wp_query->query['post_type'];
    } else {
      if ( $wc_exists && ( is_product() || is_product_tag() || is_product_category() || is_woocommerce() || is_shop() ) ) {
        $post_type = 'product';
      }
    }

    if ( $pid === '' ) {
      if ( is_page() || is_single() ) {
        global $post;
        $pid = ( is_object( $post ) ) ? $post->ID : 0;
      } elseif ( is_home() || is_category() || is_tag() ) {
        $pid = get_option('page_for_posts');
      } elseif ( is_front_page() ) {
        $pid = get_option('page_on_front');
      } elseif ( $wc ) {
        $pid = get_option( 'woocommerce_shop_page_id' ); 
      } elseif ( is_archive() || is_tax() ) {
        $pid = get_option( $post_type . '_page_theme' );
      }
    }

    if ( $pid_only === true ) return $pid;
    if ( $pid_only === 'basic' ) return array( 'page_id' => $pid, 'post_type' => $post_type );

    global $post_types_array;
    $post_types = array();
    foreach ( $post_types_array as $key => $post_type ) {
      $post_types[] = $key;
    }

    if ( $wc ) {
      if ( is_product_taxonomy() ) {
        $post_type = 'product';
        if ( is_product_category() ) {
          $taxonomy = 'product_cat';
        } elseif ( is_product_tag() ) {
          $taxonomy = 'product_tag';
        }
        $term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
      }

      if ( is_product_category() ) {
        $post_type_obj = get_post_type_object( $post_type );
        $slug = $post_type_obj->rewrite['with_front'];
      } elseif ( is_shop() ) {
        $slug = remove_http_custom( get_permalink( $pid ) );
        $slug = preg_replace( '#/(.*)/#', '$1', $slug );
      }

      $post_type_obj = get_post_type_object( $post_type );
    } else {
      if ( is_tax() ) {
        $term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
        $post_type = str_replace( 'category_', 'post_', $term->taxonomy );
      } else {
        $post_type = get_post_type();
      }

      $post_type_obj = get_post_type_object( $post_type );

      if ( $post_type !== false && is_home() || ( is_singular('post') ) ) {
        $slug = remove_http_custom( get_permalink( $pid ) );
        $slug = preg_replace( '#/(.*)/#', '$1', $slug );
      } elseif ( !is_category() && is_object($post_type_obj) ) {
        $slug = sanitize_title( $post_type_obj->labels->name );
      } else {
        $slug = get_option( 'category_base' );
      }
    }

    $page_info = array(
      'post_type' => $post_type_obj,
      'parents' => get_post_ancestors( $pid ),
      'page_id' => '',
      'tax' => '',
    );

    if ( $pid !== '' ) {
      $page_info['page_id'] = $pid;
    }

    return $page_info;
  }

//-----------------------------------------------------------------------------------
//  Basic PHP
//-----------------------------------------------------------------------------------

  //------------------ Modify Array ------------------//
  if ( ! function_exists( 'array_move_to_top' ) ) {
    function array_move_to_top( &$array, $key ) {
      $temp = array($key => $array[$key]);
      unset($array[$key]);
      $array = $temp + $array;
    }
  }

  if ( ! function_exists( 'array_move_to_top' ) ) {
    function array_move_to_bottom( &$array, $key ) {
      $value = $array[$key];
      unset($array[$key]);
      $array[$key] = $value;
    }
  }

  if ( ! function_exists( 'post_to_edit' ) ) {
    function post_to_edit( $object, $debug = true ) {
      if ( is_int( $object ) ) {
      } elseif ( method_exists( $object, 'get_id' ) ) {
        $id = $object->get_id();
      } elseif ( isset( $object->ID ) ) {
        $id = $object->ID;
      } elseif ( $debug ) {
        return '(couldn\'t find object ID: ' . print_r( $object, true ) . ')';
      } else {
        return '';
      }

      return '<a href="' . get_edit_post_link( $id ) . '" target="_blank">' . get_edit_post_link( $id ) . '</a>';
    }
  }

//-----------------------------------------------------------------------------------
//  Clean XML Fields
//-----------------------------------------------------------------------------------

  if ( ! function_exists( 'strip_special' ) ) {
    function strip_special( $string ) {
      $string = str_replace( '-', ' ', $string ); // Replaces all hyphens with spaces.
      $string = preg_replace( '/[^A-Za-z0-9\-]/', ' ', $string ); // Removes special chars.

      return preg_replace( '/\s+/', ' ', $string ); // Replaces multiple spaces with single one.
    }
  }

//-----------------------------------------------------------------------------------
//  Set Globals
//-----------------------------------------------------------------------------------

  //------------------ Post Type and PID ------------------//
  function set_globals_custom() {
    global $post_type;
    global $page_id;
    $page_info = get_custom_page_info_custom('basic');
    $post_type = $page_info['post_type'];
    $page_id = $page_info['page_id'];
  }
  add_action( 'template_redirect', 'set_globals_custom', 0, 0 );

//-----------------------------------------------------------------------------------
//  Images
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

//-----------------------------------------------------------------------------------
//  Content
//-----------------------------------------------------------------------------------

  // Get by PID
  function get_the_content_custom( $pid ) {
    $content_post = ( is_object( $pid ) ) ? $pid : get_post( $pid );
    $content = $content_post->post_content;
    $content = apply_filters('the_content', $content);
    $content = str_replace(']]>', ']]&gt;', $content);
    return $content;
  }

//-----------------------------------------------------------------------------------
//  Github Plugin Organization
//-----------------------------------------------------------------------------------

  // Set Options
  /*add_filter( 'github_updater_set_options', function () {
    return array(
      //'my-private-theme'    => '',
      'github_access_token' => '',
    );
  } );*/

  // Hide Settings
  //add_filter( 'github_updater_hide_settings', '__return_true' );

//-----------------------------------------------------------------------------------
//  WP Enqueue Styles
//-----------------------------------------------------------------------------------

  add_action( 'wp_enqueue_scripts', function() {
    //wp_enqueue_style( 'design-and-develop-style', get_stylesheet_uri() );
    //wp_add_inline_style( 'design-and-develop-style', $css );
  }, 0 );

  add_action( 'wp_head', function() {
    echo '<style>
     .grecaptcha-badge {
        display: none;
      }
    </style>';
  });

//-----------------------------------------------------------------------------------
//  Refresh Styles
//-----------------------------------------------------------------------------------

  function dnd_reset_github_cache() {
    global $wpdb;

    $table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
    $column        = is_multisite() ? 'meta_key' : 'option_name';
    $delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

    $wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) ); // phpcs:ignore

    return true;
  }

//-----------------------------------------------------------------------------------
//  Add Basic WP Features
//-----------------------------------------------------------------------------------

  add_action( 'after_setup_theme', function() {
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'widget-customizer' );
    add_theme_support( 'woocommerce' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'menus' );
    add_theme_support( 'html5' );
  });
