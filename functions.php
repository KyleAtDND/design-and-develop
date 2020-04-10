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
  include 'includes/library.php';

  // Localhost Features
  if ( is_local_custom() ) {
    include 'includes/localize.php';
  }
