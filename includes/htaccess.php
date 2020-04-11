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
//  FileSystem
//-----------------------------------------------------------------------------------

  function dnd_direct_filesystem() {
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    return new WP_Filesystem_Direct( new StdClass() );
  }

  function dnd_put_content( $file, $content ) {
    $chmod = dnd_get_filesystem_perms( 'file' );
    return dnd_direct_filesystem()->put_contents( $file, $content, $chmod );
  }

  function dnd_get_filesystem_perms( $type ) {
    static $perms = [];

    // Allow variants.
    switch ( $type ) {
      case 'dir':
      case 'dirs':
      case 'folder':
      case 'folders':
        $type = 'dir';
        break;

      case 'file':
      case 'files':
        $type = 'file';
        break;

      default:
        return 0755;
    }

    if ( isset( $perms[ $type ] ) ) {
      return $perms[ $type ];
    }

    // If the constants are not defined, use fileperms() like WordPress does.
    switch ( $type ) {
      case 'dir':
        if ( defined( 'FS_CHMOD_DIR' ) ) {
          $perms[ $type ] = FS_CHMOD_DIR;
        } else {
          $perms[ $type ] = fileperms( ABSPATH ) & 0777 | 0755;
        }
        break;

      case 'file':
        if ( defined( 'FS_CHMOD_FILE' ) ) {
          $perms[ $type ] = FS_CHMOD_FILE;
        } else {
          $perms[ $type ] = fileperms( ABSPATH . 'index.php' ) & 0777 | 0644;
        }
    }

    return $perms[ $type ];
}

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  add_action( 'upgrader_process_complete', function( $info ) {
    write_log( $info );
  });

  function flush_dnd_htaccess( $remove_rules = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    global $is_apache;

    if ( ! $is_apache || ( apply_filters( 'dnd_disable_htaccess', false ) && ! $remove_rules ) ) {
      return false;
    }

    if ( ! function_exists( 'get_home_path' ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $htaccess_file = get_home_path() . '.htaccess';

    if ( ! dnd_direct_filesystem()->is_writable( $htaccess_file ) ) {
      // The file is not writable or does not exist.
      return false;
    }

    // Get content of .htaccess file.
    $ftmp = dnd_direct_filesystem()->get_contents( $htaccess_file );

    if ( false === $ftmp ) {
      // Could not get the file contents.
      return false;
    }

    // Check if the file contains the WP rules, before modifying anything.
    $has_wp_rules = dnd_has_wp_htaccess_rules( $ftmp );

    // Remove the WP dnd marker.
    $ftmp = preg_replace( '/\s*# BEGIN DND Theme.*# END DND Theme\s*?/isU', PHP_EOL . PHP_EOL, $ftmp );
    $ftmp = ltrim( $ftmp );

    if ( ! $remove_rules ) {
      $ftmp = get_dnd_htaccess_marker() . PHP_EOL . $ftmp;
    }

    if ( apply_filters( 'dnd_remove_empty_lines', true ) ) {
      $ftmp = preg_replace( "/\n+/", "\n", $ftmp );
    }

    // Make sure the WP rules are still there.
    if ( $has_wp_rules && ! dnd_has_wp_htaccess_rules( $ftmp ) ) {
      return false;
    }

    // Update the .htacces file.
    return dnd_put_content( $htaccess_file, $ftmp );
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  function dnd_htaccess_rules_test( $rules_name ) {
    /**
    * Filters the request arguments
    *
    * @author Remy Perona
    * @since 2.10
    *
    * @param array $args Array of argument for the request.
    */
    $request_args = apply_filters(
      'dnd_htaccess_rules_test_args',
      [
        'redirection' => 0,
        'timeout'     => 5,
        'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
        'user-agent'  => 'wpdndbot',
        'cookies'     => $_COOKIE,
      ]
    );

    $response = wp_remote_get( site_url( DND_URL . 'tests/' . $rules_name . '/index.html' ), $request_args );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    return 500 !== wp_remote_retrieve_response_code( $response );
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_marker() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    // Recreate WP dnd marker.
    $marker = '# BEGIN DND Theme v' . DND_VERSION . PHP_EOL;

    /**
    * Add custom rules before rules added by WP dnd
    *
    * @since 2.6
    *
    * @param string $before_marker The content of all rules.
    */
    $marker .= apply_filters( 'before_dnd_htaccess_rules', '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

    $marker .= get_dnd_htaccess_charset();
    $marker .= get_dnd_htaccess_etag();
    $marker .= get_dnd_htaccess_web_fonts_access();
    $marker .= get_dnd_htaccess_files_match();
    $marker .= get_dnd_htaccess_mod_expires();
    $marker .= get_dnd_htaccess_mod_deflate();

    $marker .= apply_filters( 'after_dnd_htaccess_rules', '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

    $marker .= '# END DND Theme' . PHP_EOL;

    $marker = apply_filters( 'dnd_htaccess_marker', $marker );

    return $marker;
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_mod_rewrite() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    // No rewrite rules for multisite.
    if ( is_multisite() ) {
      return;
    }

    // No rewrite rules for Korean.
    if ( defined( 'WPLANG' ) && 'ko_KR' === WPLANG || 'ko_KR' === get_locale() ) {
      return;
    }

    // Get root base.
    $home_root = dnd_extract_url_component( home_url(), PHP_URL_PATH );
    $home_root = isset( $home_root ) ? trailingslashit( $home_root ) : '/';

    $site_root = dnd_extract_url_component( site_url(), PHP_URL_PATH );
    $site_root = isset( $site_root ) ? trailingslashit( $site_root ) : '';

    // Get cache root.
    if ( strpos( ABSPATH, DND_CACHE_PATH ) === false && isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
      $cache_root = str_replace( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ), '', DND_CACHE_PATH );
    } else {
      $cache_root = $site_root . str_replace( ABSPATH, '', DND_CACHE_PATH );
    }

    $http_host = apply_filters( 'dnd_url_no_dots', false ) ? dnd_remove_url_protocol( home_url() ) : '%{HTTP_HOST}';

    $is_1and1_or_force = apply_filters( 'dnd_force_full_path', strpos( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ), '/kunden/' ) === 0 );

    $rules      = '';
    $gzip_rules = '';
    $enc        = '';

    if ( $is_1and1_or_force ) {
      $cache_dir_path = str_replace( '/kunden/', '/', DND_CACHE_PATH ) . $http_host . '%{REQUEST_URI}';
    } else {
      $cache_dir_path = '%{DOCUMENT_ROOT}/' . ltrim( $cache_root, '/' ) . $http_host . '%{REQUEST_URI}';
    }

    if ( function_exists( 'gzencode' ) && apply_filters( 'dnd_force_gzip_htaccess_rules', true ) ) {
      $rules = '<IfModule mod_mime.c>' . PHP_EOL;
        $rules .= 'AddType text/html .html_gzip' . PHP_EOL;
        $rules .= 'AddEncoding gzip .html_gzip' . PHP_EOL;
      $rules .= '</IfModule>' . PHP_EOL;
      $rules .= '<IfModule mod_setenvif.c>' . PHP_EOL;
        $rules .= 'SetEnvIfNoCase Request_URI \.html_gzip$ no-gzip' . PHP_EOL;
      $rules .= '</IfModule>' . PHP_EOL . PHP_EOL;

      $gzip_rules .= 'RewriteCond %{HTTP:Accept-Encoding} gzip' . PHP_EOL;
      $gzip_rules .= 'RewriteRule .* - [E=WPR_ENC:_gzip]' . PHP_EOL;

      $enc = '%{ENV:WPR_ENC}';
    }

    $rules .= '<IfModule mod_rewrite.c>' . PHP_EOL;
    $rules .= 'RewriteEngine On' . PHP_EOL;
    $rules .= 'RewriteBase ' . $home_root . PHP_EOL;
    $rules .= get_dnd_htaccess_ssl_rewritecond();
    $rules .= dnd_get_webp_rewritecond( $cache_dir_path );
    $rules .= $gzip_rules;
    $rules .= 'RewriteCond %{REQUEST_METHOD} GET' . PHP_EOL;
    $rules .= 'RewriteCond %{QUERY_STRING} =""' . PHP_EOL;

    $cookies = get_dnd_cache_reject_cookies();
    if ( $cookies ) {
      $rules .= 'RewriteCond %{HTTP:Cookie} !(' . $cookies . ') [NC]' . PHP_EOL;
    }

    $uri = get_dnd_cache_reject_uri();
    if ( $uri ) {
      $rules .= 'RewriteCond %{REQUEST_URI} !^(' . $uri . ')$ [NC]' . PHP_EOL;
    }

    $rules .= ! is_dnd_cache_mobile() ? get_dnd_htaccess_mobile_rewritecond() : '';

    $ua = get_dnd_cache_reject_ua();
    if ( $ua ) {
      $rules .= 'RewriteCond %{HTTP_USER_AGENT} !^(' . $ua . ').* [NC]' . PHP_EOL;
    }

    $rules .= 'RewriteCond "' . $cache_dir_path . '/index%{ENV:WPR_SSL}%{ENV:WPR_WEBP}.html' . $enc . '" -f' . PHP_EOL;
    $rules .= 'RewriteRule .* "' . $cache_root . $http_host . '%{REQUEST_URI}/index%{ENV:WPR_SSL}%{ENV:WPR_WEBP}.html' . $enc . '" [L]' . PHP_EOL;
    $rules .= '</IfModule>' . PHP_EOL;

    /**
    * Filter rewrite rules to serve the cache file
    *
    * @since 1.0
    *
    * @param string $rules Rules that will be printed.
    */
    $rules = apply_filters( 'dnd_htaccess_mod_rewrite', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_mobile_rewritecond() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    // No rewrite rules for multisite.
    if ( is_multisite() ) {
      return;
    }

    $rules  = 'RewriteCond %{HTTP:X-Wap-Profile} !^[a-z0-9\"]+ [NC]' . PHP_EOL;
    $rules .= 'RewriteCond %{HTTP:Profile} !^[a-z0-9\"]+ [NC]' . PHP_EOL;
    $rules .= 'RewriteCond %{HTTP_USER_AGENT} !^.*(2.0\ MMP|240x320|400X240|AvantGo|BlackBerry|Blazer|Cellphone|Danger|DoCoMo|Elaine/3.0|EudoraWeb|Googlebot-Mobile|hiptop|IEMobile|KYOCERA/WX310K|LG/U990|MIDP-2.|MMEF20|MOT-V|NetFront|Newt|Nintendo\ Wii|Nitro|Nokia|Opera\ Mini|Palm|PlayStation\ Portable|portalmmm|Proxinet|ProxiNet|SHARP-TQ-GX10|SHG-i900|Small|SonyEricsson|Symbian\ OS|SymbianOS|TS21i-10|UP.Browser|UP.Link|webOS|Windows\ CE|WinWAP|YahooSeeker/M1A1-R2D2|iPhone|iPod|Android|BlackBerry9530|LG-TU915\ Obigo|LGE\ VX|webOS|Nokia5800).* [NC]' . PHP_EOL;
    $rules .= 'RewriteCond %{HTTP_USER_AGENT} !^(w3c\ |w3c-|acs-|alav|alca|amoi|audi|avan|benq|bird|blac|blaz|brew|cell|cldc|cmd-|dang|doco|eric|hipt|htc_|inno|ipaq|ipod|jigs|kddi|keji|leno|lg-c|lg-d|lg-g|lge-|lg/u|maui|maxo|midp|mits|mmef|mobi|mot-|moto|mwbp|nec-|newt|noki|palm|pana|pant|phil|play|port|prox|qwap|sage|sams|sany|sch-|sec-|send|seri|sgh-|shar|sie-|siem|smal|smar|sony|sph-|symb|t-mo|teli|tim-|tosh|tsm-|upg1|upsi|vk-v|voda|wap-|wapa|wapi|wapp|wapr|webc|winw|winw|xda\ |xda-).* [NC]' . PHP_EOL;

    /**
    * Filter rules for detect mobile version
    *
    * @since 2.0
    *
    * @param string $rules Rules that will be printed.
    */
    $rules = apply_filters( 'dnd_htaccess_mobile_rewritecond', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_ssl_rewritecond() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    $rules  = 'RewriteCond %{HTTPS} on [OR]' . PHP_EOL;
    $rules .= 'RewriteCond %{SERVER_PORT} ^443$ [OR]' . PHP_EOL;
    $rules .= 'RewriteCond %{HTTP:X-Forwarded-Proto} https' . PHP_EOL;
    $rules .= 'RewriteRule .* - [E=WPR_SSL:-https]' . PHP_EOL;

    /**
    * Filter rules for SSL requests
    *
    * @since 2.0
    *
    * @param string $rules Rules that will be printed.
    */
    $rules = apply_filters( 'dnd_htaccess_ssl_rewritecond', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  function dnd_get_webp_rewritecond( $cache_dir_path ) {
    if ( ! get_dnd_option( 'cache_webp' ) ) {
      return '';
    }

    $rules  = 'RewriteCond %{HTTP_ACCEPT} image/webp' . PHP_EOL;
    $rules .= 'RewriteCond "' . $cache_dir_path . '/.no-webp" !-f' . PHP_EOL;
    $rules .= 'RewriteRule .* - [E=WPR_WEBP:-webp]' . PHP_EOL;

    /**
    * Filter rules for webp.
    *
    * @since  3.4
    * @author Grégory Viguier
    *
    * @param string $rules Rules that will be printed.
    */
    return apply_filters( 'dnd_webp_rewritecond', $rules );
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with GZIP Compression
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_mod_deflate() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    $rules = '# Gzip compression' . PHP_EOL;
    $rules .= '<IfModule mod_deflate.c>' . PHP_EOL;
      $rules .= '# Active compression' . PHP_EOL;
      $rules .= 'SetOutputFilter DEFLATE' . PHP_EOL;
      $rules .= '# Force deflate for mangled headers' . PHP_EOL;
      $rules .= '<IfModule mod_setenvif.c>' . PHP_EOL;
        $rules .= '<IfModule mod_headers.c>' . PHP_EOL;
        $rules .= 'SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding' . PHP_EOL;
        $rules .= 'RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding' . PHP_EOL;
        $rules .= '# Don’t compress images and other uncompressible content' . PHP_EOL;
        $rules .= 'SetEnvIfNoCase Request_URI \\' . PHP_EOL;
        $rules .= '\\.(?:gif|jpe?g|png|rar|zip|exe|flv|mov|wma|mp3|avi|swf|mp?g|mp4|webm|webp|pdf)$ no-gzip dont-vary' . PHP_EOL;
        $rules .= '</IfModule>' . PHP_EOL;
      $rules .= '</IfModule>' . PHP_EOL . PHP_EOL;
      $rules .= '# Compress all output labeled with one of the following MIME-types' . PHP_EOL;
      $rules .= '<IfModule mod_filter.c>' . PHP_EOL;
      $rules .= 'AddOutputFilterByType DEFLATE application/atom+xml \
                                application/javascript \
                                application/json \
                                application/rss+xml \
                                application/vnd.ms-fontobject \
                                application/x-font-ttf \
                                application/xhtml+xml \
                                application/xml \
                                font/opentype \
                                image/svg+xml \
                                image/x-icon \
                                text/css \
                                text/html \
                                text/plain \
                                text/x-component \
                                text/xml' . PHP_EOL;
      $rules .= '</IfModule>' . PHP_EOL;
      $rules .= '<IfModule mod_headers.c>' . PHP_EOL;
        $rules .= 'Header append Vary: Accept-Encoding' . PHP_EOL;
      $rules .= '</IfModule>' . PHP_EOL;
    $rules .= '</IfModule>' . PHP_EOL . PHP_EOL;

    /**
    * Filter rules to improve performances with GZIP Compression
    *
    * @since 1.0
    *
    * @param string $rules Rules that will be printed.
    */
    $rules = apply_filters( 'dnd_htaccess_mod_deflate', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to improve performances with Expires Headers
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_mod_expires() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    $rules = <<<HTACCESS
  # Expires headers (for better cache control)
  <IfModule mod_expires.c>
    ExpiresActive on
    # Perhaps better to whitelist expires rules? Perhaps.
    ExpiresDefault                              "access plus 1 month"
    # cache.appcache needs re-requests in FF 3.6 (thanks Remy ~Introducing HTML5)
    ExpiresByType text/cache-manifest           "access plus 0 seconds"
    # Your document html
    ExpiresByType text/html                     "access plus 0 seconds"
    # Data
    ExpiresByType text/xml                      "access plus 0 seconds"
    ExpiresByType application/xml               "access plus 0 seconds"
    ExpiresByType application/json              "access plus 0 seconds"
    # Feed
    ExpiresByType application/rss+xml           "access plus 1 hour"
    ExpiresByType application/atom+xml          "access plus 1 hour"
    # Favicon (cannot be renamed)
    ExpiresByType image/x-icon                  "access plus 1 week"
    # Media: images, video, audio
    ExpiresByType image/gif                     "access plus 4 months"
    ExpiresByType image/png                     "access plus 4 months"
    ExpiresByType image/jpeg                    "access plus 4 months"
    ExpiresByType image/webp                    "access plus 4 months"
    ExpiresByType video/ogg                     "access plus 1 month"
    ExpiresByType audio/ogg                     "access plus 1 month"
    ExpiresByType video/mp4                     "access plus 1 month"
    ExpiresByType video/webm                    "access plus 1 month"
    # HTC files  (css3pie)
    ExpiresByType text/x-component              "access plus 1 month"
    # Webfonts
    ExpiresByType font/ttf                      "access plus 4 months"
    ExpiresByType font/otf                      "access plus 4 months"
    ExpiresByType font/woff                     "access plus 4 months"
    ExpiresByType font/woff2                    "access plus 4 months"
    ExpiresByType image/svg+xml                 "access plus 1 month"
    ExpiresByType application/vnd.ms-fontobject "access plus 1 month"
    # CSS and JavaScript
    ExpiresByType text/css                      "access plus 1 year"
    ExpiresByType application/javascript        "access plus 1 year"
  </IfModule>
  HTACCESS;

    $rules = apply_filters( 'dnd_htaccess_mod_expires', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules for default charset on static files
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_charset() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    // Get charset of the blog.
    $charset = preg_replace( '/[^a-zA-Z0-9_\-\.:]+/', '', get_bloginfo( 'charset', 'display' ) );

    $rules = "# Use $charset encoding for anything served text/plain or text/html" . PHP_EOL;
    $rules .= "AddDefaultCharset $charset" . PHP_EOL;
    $rules .= "# Force $charset for a number of file formats" . PHP_EOL;
    $rules .= '<IfModule mod_mime.c>' . PHP_EOL;
      $rules .= "AddCharset $charset .atom .css .js .json .rss .vtt .xml" . PHP_EOL;
    $rules .= '</IfModule>' . PHP_EOL . PHP_EOL;

    $rules .= '
    # Set the default language
    DefaultLanguage en-US
    # Set server timezone
    SetEnv TZ America/Chicago
    # Set the server administrator email
    SetEnv SERVER_ADMIN ' . get_bloginfo( 'admin_email' ) . '
    ';

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules for cache control
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_files_match() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    $rules = '<IfModule mod_alias.c>' . PHP_EOL;
      $rules .= '<FilesMatch "\.(html|htm|rtf|rtx|txt|xsd|xsl|xml)$">' . PHP_EOL;
        $rules .= '<IfModule mod_headers.c>' . PHP_EOL;
          $rules .= 'Header unset Pragma' . PHP_EOL;
          $rules .= 'Header append Cache-Control "public"' . PHP_EOL;
          $rules .= 'Header unset Last-Modified' . PHP_EOL;
        $rules .= '</IfModule>' . PHP_EOL;
      $rules .= '</FilesMatch>' . PHP_EOL . PHP_EOL;
      $rules .= '<FilesMatch "\.(css|htc|js|asf|asx|wax|wmv|wmx|avi|bmp|class|divx|doc|docx|eot|exe|gif|gz|gzip|ico|jpg|jpeg|jpe|json|mdb|mid|midi|mov|qt|mp3|m4a|mp4|m4v|mpeg|mpg|mpe|mpp|otf|odb|odc|odf|odg|odp|ods|odt|ogg|pdf|png|pot|pps|ppt|pptx|ra|ram|svg|svgz|swf|tar|tif|tiff|ttf|ttc|wav|wma|wri|xla|xls|xlsx|xlt|xlw|zip)$">' . PHP_EOL;
        $rules .= '<IfModule mod_headers.c>' . PHP_EOL;
          $rules .= 'Header unset Pragma' . PHP_EOL;
          $rules .= 'Header append Cache-Control "public"' . PHP_EOL;
        $rules .= '</IfModule>' . PHP_EOL;
      $rules .= '</FilesMatch>' . PHP_EOL;
    $rules .= '</IfModule>' . PHP_EOL . PHP_EOL;

    $rules = '
    ####### Expire Headers #######
      <FilesMatch "\.(html|htm|rtf|rtx|txt|xsd|xsl|xml|HTML|HTM|RTF|RTX|TXT|XSD|XSL|XML)$">
        <IfModule mod_headers.c>
          Header set Pragma "no-cache"
          Header set Cache-Control "max-age=0, private, no-store, no-cache, must-revalidate"
          Header set Expires "Wed, 11 Jan 2000 05:00:00 GMT"
        </IfModule>
      </FilesMatch>
      <FilesMatch "\.(css|htc|less|js|js2|svg|svgz|SVG|SVGZ|js3|js4|CSS|HTC|LESS|JS|JS2|JS3|JS4|asf|asx|wax|wmv|wmx|avi|bmp|class|divx|doc|docx|eot|exe|gif|gz|gzip|ico|jpg|jpeg|jpe|json|mdb|mid|midi|mov|qt|mp3|m4a|mp4|m4v|mpeg|mpg|mpe|mpp|otf|odb|odc|odf|odg|odp|ods|odt|ogg|pdf|png|pot|pps|ppt|pptx|ra|ram|svg|svgz|swf|tar|tif|tiff|ttf|ttc|wav|wma|wri|woff|woff2|xla|xls|xlsx|xlt|xlw|zip|ASF|ASX|WAX|WMV|WMX|AVI|BMP|CLASS|DIVX|DOC|DOCX|EOT|EXE|GIF|GZ|GZIP|ICO|JPG|JPEG|JPE|JSON|MDB|MID|MIDI|MOV|QT|MP3|M4A|MP4|M4V|MPEG|MPG|MPE|MPP|OTF|ODB|ODC|ODF|ODG|ODP|ODS|ODT|OGG|PDF|PNG|POT|PPS|PPT|PPTX|RA|RAM|SVG|SVGZ|SWF|TAR|TIF|TIFF|TTF|TTC|WAV|WMA|WRI|WOFF2|WOFF|XLA|XLS|XLSX|XLT|XLW|ZIP)$">
        <IfModule mod_headers.c>
          Header set Pragma "public"
          Header append Cache-Control "public"
          Header unset Set-Cookie
        </IfModule>
      </FilesMatch>
    ';

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to remove the etag
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_etag() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    $rules  = '# FileETag None is not enough for every server.' . PHP_EOL;
    $rules .= '<IfModule mod_headers.c>' . PHP_EOL;
    $rules .= 'Header unset ETag' . PHP_EOL;
    $rules .= '</IfModule>' . PHP_EOL . PHP_EOL;
    $rules .= '# Since we’re sending far-future expires, we don’t need ETags for static content.' . PHP_EOL;
    $rules .= '# developer.yahoo.com/performance/rules.html#etags' . PHP_EOL;
    $rules .= 'FileETag None' . PHP_EOL . PHP_EOL;

    $rules = apply_filters( 'dnd_htaccess_etag', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to Cross-origin fonts sharing when CDN is used
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_web_fonts_access() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
    if ( ! get_dnd_option( 'cdn', false ) ) {
      return;
    }

    $rules  = '# Send CORS headers if browsers request them; enabled by default for images.' . PHP_EOL;
    $rules  .= '<IfModule mod_setenvif.c>' . PHP_EOL;
      $rules  .= '<IfModule mod_headers.c>' . PHP_EOL;
      $rules  .= '# mod_headers, y u no match by Content-Type?!' . PHP_EOL;
      $rules  .= '<FilesMatch "\.(cur|gif|png|jpe?g|svgz?|ico|webp)$">' . PHP_EOL;
        $rules  .= 'SetEnvIf Origin ":" IS_CORS' . PHP_EOL;
        $rules  .= 'Header set Access-Control-Allow-Origin "*" env=IS_CORS' . PHP_EOL;
      $rules  .= '</FilesMatch>' . PHP_EOL;
      $rules  .= '</IfModule>' . PHP_EOL;
    $rules  .= '</IfModule>' . PHP_EOL . PHP_EOL;

    $rules  .= '# Allow access to web fonts from all domains.' . PHP_EOL;
    $rules  .= '<FilesMatch "\.(eot|otf|tt[cf]|woff2?)$">' . PHP_EOL;
      $rules .= '<IfModule mod_headers.c>' . PHP_EOL;
        $rules .= 'Header set Access-Control-Allow-Origin "*"' . PHP_EOL;
      $rules .= '</IfModule>' . PHP_EOL;
    $rules .= '</FilesMatch>' . PHP_EOL . PHP_EOL;

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Tell if WP rewrite rules are present in a given string.
//-----------------------------------------------------------------------------------

  function dnd_has_wp_htaccess_rules( $content ) {
    if ( is_multisite() ) {
      $has_wp_rules = strpos( $content, '# add a trailing slash to /wp-admin' ) !== false;
    } else {
      $has_wp_rules = strpos( $content, '# BEGIN WordPress' ) !== false;
    }

    return apply_filters( 'dnd_has_wp_htaccess_rules', $has_wp_rules, $content );
  }

//-----------------------------------------------------------------------------------
//  Check if WP dnd htaccess rules are already present in the file
//-----------------------------------------------------------------------------------

  function dnd_check_htaccess_rules() {
    if ( ! function_exists( 'get_home_path' ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $htaccess_file = get_home_path() . '.htaccess';

    if ( ! dnd_direct_filesystem()->is_readable( $htaccess_file ) ) {
      return false;
    }

    $htaccess = dnd_direct_filesystem()->get_contents( $htaccess_file );

    if ( preg_match( '/\s*# BEGIN DND Theme.*# END DND Theme\s*?/isU', $htaccess ) ) {
      return true;
    }

    return false;
  }
