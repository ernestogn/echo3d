<?php
/**
 * Theme Functions
 *
 * @author Jegstudio
 * @package echelon-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined( 'ECHELON_FSE_VERSION' ) || define( 'ECHELON_FSE_VERSION', '1.1.1' );
defined( 'ECHELON_FSE_DIR' ) || define( 'ECHELON_FSE_DIR', trailingslashit( get_template_directory() ) );
defined( 'ECHELON_FSE_URI' ) || define( 'ECHELON_FSE_URI', trailingslashit( get_template_directory_uri() ) );

require get_parent_theme_file_path( 'inc/autoload.php' );

Echelon_Fse\Init::instance();
