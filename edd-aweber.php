<?php
/*
Plugin Name: Easy Digital Downloads - AWeber
Plugin URL: http://easydigitaldownloads.com/extension/aweber
Description: Include an AWeber signup option with your Easy Digital Downloads checkout
Version: 2.0.5
Author: Justin Sainton and Pippin Williamson
Author URI: http://zaowebdesign.com
Contributors: JustinSainton, Pippin Williamson
*/

define( 'EDD_AWEBER_STORE_API_URL', 'https://easydigitaldownloads.com' );
define( 'EDD_AWEBER_PRODUCT_NAME', 'AWeber' );
define( 'EDD_AWEBER_PATH', dirname( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| LICENSING / UPDATES
|--------------------------------------------------------------------------
*/

if( class_exists( 'EDD_License' ) && is_admin() ) {
	$eddaw_license = new EDD_License( __FILE__, EDD_AWEBER_PRODUCT_NAME, '2.0.5', 'Pippin Williamson' );
}


if( ! class_exists( 'EDD_Newsletter' ) ) {
	include( EDD_AWEBER_PATH . '/includes/class-edd-newsletter.php' );
}
include( EDD_AWEBER_PATH . '/includes/class-edd-aweber.php' );

$edd_aweber = new EDD_Aweber( 'aweber', 'AWeber' );