<?php
//
// Index Functions
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

get_header();

  if ( have_posts() ) :
    while ( have_posts() ) : the_post();

      the_content();

    endwhile;
  endif;

get_footer();
