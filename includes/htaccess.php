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
//  Flush rules
//-----------------------------------------------------------------------------------

  function flush_dnd_htaccess( $remove_rules = false ) {
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

    // Remove the DND marker.
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
//  Rules test
//-----------------------------------------------------------------------------------

  function dnd_htaccess_rules_test( $rules_name ) {
    $request_args = apply_filters(
      'dnd_htaccess_rules_test_args',
      [
        'redirection' => 0,
        'timeout'     => 5,
        'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
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
//  Create htaccess uUle
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_marker() {
    // Recreate DND marker.
    $marker = '# BEGIN DND Theme v' . DND_VERSION . PHP_EOL;

$marker .= <<<HTACCESS
############## Essentials ##############
  # Limit server request methods to GET and PUT
  Options -ExecCGI -Indexes +FollowSymLinks
  # Rewrite
  RewriteEngine on
  RewriteOptions Inherit
  RewriteBase /
############## Performance ##############
  # Explicitly disable caching for scripts and other dynamic files
  <FilesMatch "\.(pl|php|cgi|spl|scgi|fcgi)$">
    Header unset Cache-Control
  </FilesMatch>

HTACCESS;

    $marker .= get_dnd_htaccess_charset();
    $marker .= get_dnd_htaccess_etag();
    //$marker .= get_dnd_htaccess_web_fonts_access();
    $marker .= get_dnd_htaccess_mod_expires();
    //$marker .= get_dnd_htaccess_mod_deflate();
    $marker .= get_dnd_htaccess_security();
    $marker .= get_dnd_htaccess_tricks();

    $marker .= '# END DND Theme' . PHP_EOL;

    $marker = apply_filters( 'dnd_htaccess_marker', $marker );

    return $marker;
  }

//-----------------------------------------------------------------------------------
//  Tricks
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_tricks() {
$rules = <<<HTACCESS
############## Usability Tricks ##############
  # Automatically corect simple speling erors
  <IfModule mod_speling.c>
    CheckSpelling On
  </IfModule>
  # View Documents in New Tabs Instead of Download
  <IfModule mod_headers.c>
    <FilesMatch "\.(doc?x|dotx|pdf)$">
      Header set Content-Disposition inline
    </FilesMatch>
  </IfModule>
############## MP4 Fixes ##############
  SetEnvIfNoCase Request_URI get_file\.mp4$ no-gzip dont-vary

HTACCESS;

    $rules = apply_filters( 'dnd_htaccess_tricks', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Security
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_security() {
    $block = apply_filters( 'dnd_block_xmlrpc_security', true );
    $rules = '';

    if ( $block )
$rules .= <<<HTACCESS
# Block WordPress xmlrpc.php requests
<Files xmlrpc.php>
  order deny,allow
  deny from all
</Files>
HTACCESS;

$rules .= <<<HTACCESS
#BASIC ID=1
RedirectMatch 409 .(htaccess|htpasswd|ini|phps|fla|psd|log|sh)$
ServerSignature Off
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in.*$ [NC]
  RewriteRule ^readme*.*html$ /forbidden [L,QSA]
  RewriteRule ^license*.*txt$ /forbidden [L,QSA]
  RewriteRule ^wp-config*.*php$ /forbidden [L,QSA]
</IfModule>
#BASIC

#BLOCK WP FILE ACCESS  ID=2
# Block the include-only files.
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in.*$ [NC]
  RewriteRule ^wp-admin/includes/ /forbidden [NC,L]
  RewriteRule ^wp-includes/[^/]+.php$ /forbidden [NC,L]
  RewriteRule ^wp-content/uploads/(.*).php$ /forbidden [NC,L]
  RewriteRule ^wp-includes/js/tinymce/langs/.+.php /forbidden [NC,L]
  RewriteRule ^wp-includes/theme-compat/ /forbidden [NC,L]
</IfModule>
#BLOCK WP FILE ACCESS

#BLOCK DEBUG LOG ACCESS
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^debug*.*log$ /forbidden [L,QSA]
</IfModule>
#BLOCK DEBUG LOG ACCESS

#FORBID PROXY COMMENT POSTING ID=7
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in.*$ [NC]
  RewriteCond %{REQUEST_METHOD} ^POST
  RewriteCond %{HTTP:VIA} !^$ [OR]
  RewriteCond %{HTTP:FORWARDED} !^$ [OR]
  RewriteCond %{HTTP:USERAGENT_VIA} !^$ [OR]
  RewriteCond %{HTTP:X_FORWARDED_FOR} !^$ [OR]
  RewriteCond %{HTTP:X_FORWARDED_HOST} !^$ [OR]
  RewriteCond %{HTTP:PROXY_CONNECTION} !^$ [OR]
  RewriteCond %{HTTP:XPROXY_CONNECTION} !^$ [OR]
  RewriteCond %{HTTP:HTTP_PC_REMOTE_ADDR} !^$ [OR]
  RewriteCond %{HTTP:HTTP_CLIENT_IP} !^$
  RewriteRule wp-comments-post\.php /forbidden [NC]
</IfModule>
#FORBID PROXY COMMENT POSTING

#WPSCAN ID=19
<IfModule mod_rewrite.c>
  RewriteEngine on
  RewriteRule ^(.*)/plugins/(.*)readme\.(txt|html)$ /forbidden [NC,L] 
</IfModule>
#WPSCAN

HTACCESS;

    $rules = apply_filters( 'dnd_htaccess_security', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Mod Deflate
//-----------------------------------------------------------------------------------

  /*function get_dnd_htaccess_mod_deflate() {
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

    $rules = apply_filters( 'dnd_htaccess_mod_deflate', $rules );

    return $rules;
  }*/

//-----------------------------------------------------------------------------------
//  Mod Expires
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_mod_expires() {
$rules = <<<HTACCESS
############## Proper MIME type for all files ##############
  <IfModule mod_mime.c>
    # Standard text files
    AddType text/html .html .htm
    AddType text/css .css
    AddType text/plain .txt
    AddType text/richtext .rtf .rtx
    AddType application/javascript .js
    AddType text/x-javascript .js2
    AddType text/javascript .js3
    AddType text/x-js .js4
    AddType text/xsd .xsd
    AddType text/xsl .xsl
    AddType text/xml .xml
    AddType application/java .class
    AddType application/json .json
    AddType text/x-component .htc
    # Feed files
    AddType application/rss+xml .rss
    AddType application/atom+xml .atom
    # Image files
    AddType image/svg+xml .svg .svgz
    AddEncoding gzip .svgz  
    AddType image/bmp .bmp
    AddType image/gif .gif
    AddType image/x-icon .ico
    AddType image/jpeg .jpg .jpeg .jpe
    AddType image/png .png
    AddType image/webp .webp
    AddType image/tiff .tif .tiff
    # Audio files
    AddType audio/midi .mid .midi
    AddType audio/ogg .ogg
    AddType audio/mpeg .mp3 .m4a
    AddType audio/x-realaudio .ra .ram
    AddType audio/wma .wma
    AddType audio/wav .wav
    # Movie files
    AddType video/ogg .ogv
    AddType video/webm .webm
    AddType video/asf .asf .asx .wax .wmv .wmx
    AddType video/avi .avi
    AddType video/divx .divx
    AddType video/quicktime .mov .qt
    AddType video/mp4 .mp4 .m4v
    AddType video/mpeg .mpeg .mpg .mpe
    # Other
    AddType application/x-gzip .gz .gzip
    AddType application/zip .zip
    AddType application/x-tar .tar
    AddType application/x-shockwave-flash .swf
    AddType text/cache-manifest appcache manifest
    AddType application/octet-stream safariextz
    AddType application/x-web-app-manifest+json webapp
    AddType text/x-vcard .vcf
    # Documents
    AddType application/pdf .pdf
    AddType application/vnd.ms-access .mdb
    AddType application/vnd.ms-project .mpp
    AddType application/vnd.ms-powerpoint .pot .pps .ppt .pptx .potx .ppam .ppsm .ppsx .pptm
    AddType application/vnd.ms-excel .xla .xls .xlsx .xlt .xlw .xlsb .xlsm .xltx .xlam
    AddType application/vnd.ms-write .wri
    AddType application/vnd.ms-word .docx .dotx
    AddType application/x-msdownload .exe
    AddType application/vnd.oasis.opendocument.database .odb
    AddType application/vnd.oasis.opendocument.chart .odc
    AddType application/vnd.oasis.opendocument.formula .odf
    AddType application/vnd.oasis.opendocument.graphics .odg
    AddType application/vnd.oasis.opendocument.presentation .odp
    AddType application/vnd.oasis.opendocument.spreadsheet .ods
    AddType application/vnd.oasis.opendocument.text .odt
    # Webfonts
    AddType application/vnd.ms-fontobject .eot
    AddType font/opentype .otf
    AddType application/x-font-ttf .ttf .ttc
    AddType application/x-font-woff .woff
    AddType application/x-font-woff2 .woff2
  </IfModule>

############## Expires headers (for better cache control) ##############
  <IfModule mod_expires.c>
    # Enable expiration control
    ExpiresActive On
    # Default expiration: 1 hour after request
    ExpiresDefault "access plus 1 year"
    # Special requests
    ExpiresByType text/cache-manifest "access plus 0 seconds"
    # Standard text files expiration: 1 week after request
    ExpiresByType text/html "access plus 30 seconds"
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType text/plain "access plus 1 year"
    ExpiresByType text/richtext "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType text/x-javascript "access plus 1 year"
    ExpiresByType text/javascript "access plus 1 year"
    ExpiresByType text/x-js "access plus 1 year"
    ExpiresByType application/xhtml+xml "access plus 60 seconds"
    ExpiresByType application/json "access plus 60 seconds"
    ExpiresByType text/xsd "access plus 60 seconds
    ExpiresByType text/xsl "access plus 60 seconds
    ExpiresByType application/java "access plus 60 seconds
    ExpiresByType text/x-component "access plus 60 seconds
    # Data
    ExpiresByType text/xml "access plus 60 seconds
    ExpiresByType application/json "access plus 60 seconds
    ExpiresByType application/xml "access plus 60 seconds
    # Feed
    ExpiresByType application/rss+xml "access plus 600 seconds
    ExpiresByType application/atom+xml "access plus 600 seconds
    # Image
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/tiff "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    ExpiresByType images/bmp "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    # Audio
    ExpiresByType audio/midi "access plus 1 year"
    ExpiresByType audio/mpeg "access plus 1 year"
    ExpiresByType audio/ogg "access plus 1 year"
    ExpiresByType audio/x-realaudio "access plus 1 year"
    ExpiresByType audio/wma "access plus 1 year"
    ExpiresByType audio/wav "access plus 1 year"
    # Movie
    ExpiresByType video/avi "access plus 1 year"
    ExpiresByType video/mpeg "access plus 1 year"
    ExpiresByType video/mp4 "access plus 1 year"
    ExpiresByType video/quicktime "access plus 1 year"
    ExpiresByType video/webm "access plus 1 year"
    ExpiresByType video/ogg "access plus 1 year"
    ExpiresByType video/asf "access plus 1 year"
    ExpiresByType video/divx "access plus 1 year"
    # Other
    ExpiresByType application/zip "access plus 1 year"
    ExpiresByType application/x-tar "access plus 1 year"
    ExpiresByType application/x-shockwave-flash "access plus 1 year"
    ExpiresByType application/octet-stream "access plus 600 seconds"
    ExpiresByType application/x-web-app-manifest+json "access plus 600 seconds"
    # Documents
    ExpiresByType application/pdf "access plus 1 year"
    ExpiresByType application/vnd.ms-access "access plus 1 year"
    ExpiresByType application/vnd.ms-project "access plus 1 year"
    ExpiresByType application/vnd.ms-powerpoint "access plus 1 year"
    ExpiresByType application/vnd.ms-excel "access plus 1 year"
    ExpiresByType application/vnd.ms-write "access plus 1 year"
    ExpiresByType application/x-msdownload "access plus 1 year"
    ExpiresByType application/vnd.oasis.opendocument.database "access plus 1 year"
    ExpiresByType application/vnd.oasis.opendocument.chart "access plus 1 year"
    ExpiresByType application/vnd.oasis.opendocument.formula "access plus 1 year"
    ExpiresByType application/vnd.oasis.opendocument.graphics "access plus 1 year"
    ExpiresByType application/vnd.oasis.opendocument.presentation "access plus 1 year"
    ExpiresByType application/vnd.oasis.opendocument.spreadsheet "access plus 1 year"
    ExpiresByType application/vnd.oasis.opendocument.text "access plus 1 year"
    # Webfonts
    ExpiresByType application/vnd.ms-fontobject "access plus 1 year"
    ExpiresByType font/opentype "access plus 1 year"
    ExpiresByType application/x-font-ttf "access plus 1 year"
    ExpiresByType application/x-font-woff "access plus 1 year"
    ExpiresByType application/x-font-woff2 "access plus 1 year"
  </IfModule>

HTACCESS;

    $rules = apply_filters( 'dnd_htaccess_mod_expires', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  htaccess Charset
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_charset() {
    // Get charset of the blog.
    $charset = preg_replace( '/[^a-zA-Z0-9_\-\.:]+/', '', get_bloginfo( 'charset', 'display' ) );
    $email = get_bloginfo( 'admin_email' );

$rules = <<<HTACCESS
# Use $charset encoding for anything served text/plain or text/html
AddDefaultCharset $charset
# Force $charset for a number of file formats
<IfModule mod_mime.c>
  AddCharset $charset .atom .css .js .json .rss .vtt .xml
</IfModule>
# Set the default language
DefaultLanguage en-US
# Set server timezone
SetEnv TZ America/Chicago
# Set the server administrator email
SetEnv SERVER_ADMIN $email

HTACCESS;

    $rules = apply_filters( 'dnd_htaccess_charset', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to remove the etag
//-----------------------------------------------------------------------------------

  function get_dnd_htaccess_etag() {
$rules = <<<HTACCESS
# FileETag None is not enough for every server.
<IfModule mod_headers.c>
Header unset ETag
</IfModule>
FileETag None

HTACCESS;

    $rules = apply_filters( 'dnd_htaccess_etag', $rules );

    return $rules;
  }

//-----------------------------------------------------------------------------------
//  Rules to Cross-origin fonts sharing when CDN is used
//-----------------------------------------------------------------------------------

  /*function get_dnd_htaccess_web_fonts_access() {
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

    $rules = apply_filters( 'dnd_htaccess_web_fonts_access', $rules );

    return $rules;
  }*/

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
//  Check if DND htaccess rules are already present in the file
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
