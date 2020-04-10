<?php
//
// Functions Functions
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
//  Include Libraries
//-----------------------------------------------------------------------------------

  // Include extra Core functions
<<<<<<< HEAD
  include 'includes/library.php';

  // Localhost Features
  if ( is_local_custom() ) {
    include 'includes/localize.php';
  }
=======
  include 'inc/library.php';

  // Localhost Features
  if ( is_local_custom() ) {
    include 'inc/local/local-all.php';

    if ( is_mac_custom() ) {
      include 'inc/local/local-cond.php';
    }
  }

  // Image functions
  include 'inc/images.php';

  // Shortcodes
  include 'inc/shortcodes.php';

  // Plugins
  include 'inc/plugins/plugins.php';

  // Content
  include 'inc/content.php';
>>>>>>> b15933d51cb9ff86b812384260b6299fe7c38ecd
