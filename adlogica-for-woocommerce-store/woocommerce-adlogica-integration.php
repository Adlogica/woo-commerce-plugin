<?php
/*
  Plugin Name: Adlogica Integration for Woocommerce store
  Plugin URI: http://downloads.savvyads.com/adlogica-plugin-woocommerce/
  Description: Exposes digitalData and enables adlogica tracking on WooCommerce store pages.
  Author: Adarsh
  Author URI: http://www.savvyads.com
  Version: 0.0.1
 */

// Add the integration to WooCommerce
function wc_adlogica_add_integration($integrations) {
    global $woocommerce;

    if (is_object($woocommerce)) {
        include_once( 'includes/class-wc-adlogica-integration.php' );
        $integrations[] = 'WC_Adlogica';
    }

    return $integrations;
}

add_filter('woocommerce_integrations', 'wc_adlogica_add_integration', 10);
