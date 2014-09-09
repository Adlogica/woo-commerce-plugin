<?php

/**
 * Adlogica for Woo-commerce stores
 *
 * Allows adlogica tracking on store pages.
 *
 * @class 		WC_Adlogica
 * @extends		WC_Integration
 * @author Adarsh <adarsh@rallytec.in>
 */
class WC_Adlogica extends WC_Integration {

    /**
     * Init and hook in the integration.
     *
     * @access public
     * @return void
     */
    public function __construct() {
        $this->id = 'adlogica';
        $this->method_title = __('Adlogica Integration', 'woocommerce');
        $this->method_description = __('Adds digital data to all product pages to enable adlogica tracking', 'woocommerce');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->adlogica_org_id = $this->get_option('adlogica_org_id');
        $this->adlogica_app_id = $this->get_option('adlogica_app_id');
        $this->adlogica_tracking_enabled = $this->get_option('adlogica_tracking_enabled');
        $this->enable_guest_checkout = get_option('woocommerce_enable_guest_checkout') == 'yes' ? true : false;
        $this->track_login_step_for_guest_user = $this->get_option('track_login_step_for_guest_user') == 'yes' ? true : false;

        // Actions
        add_action('woocommerce_update_options_integration_adlogica', array($this, 'process_admin_options'));
        // Tracking code
        add_action('wp_head', array($this, 'adlogica_tracking_code'));
        add_action('woocommerce_thankyou', array($this, 'adlogica_transaction_code'));

        
        add_action('woocommerce_after_single_product', array($this, 'product_detail_view'));
        add_action('woocommerce_after_cart', array($this, 'remove_cart_tracking'));

        // Event tracking code
        add_action('woocommerce_after_add_to_cart_button', array($this, 'add_to_cart'));
    }

    /**
     * Initialise Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields() {

        $this->form_fields = array(
            'adlogica_org_id' => array(
                'title' => __('Adlogica Organization ID', 'woocommerce'),
                'description' => __('Login to Adlogica console to find your Organization ID. e.g. <code>53cf079d6563324c72260300</code>', 'woocommerce'),
                'type' => 'text',
                'default' => get_option('woocommerce_adlogica_org_id') // Backwards compat
            ),
            'adlogica_app_id' => array(
                'title' => __('Adlogica App ID', 'woocommerce'),
                'description' => __('Login to Adlogica console to find your App ID. e.g. <code>53cf079e6563324c39c90300</code>', 'woocommerce'),
                'type' => 'text',
                'default' => get_option('woocommerce_adlogica_app_id') // Backwards compat
            ),
            'adlogica_tracking_enabled' => array(
                'title' => __('Adlogica Tracking code', 'woocommerce'),
                'label' => __('Add tracking code to your site using this plugin &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Optional).', 'woocommerce'),
                'description' => sprintf(__('You don\'t need to enable this if haven\'t signed up on Adlogica', 'woocommerce')),
                'type' => 'checkbox',
                'checkboxgroup' => 'start',
                'default' => get_option('woocommerce_adlogica_tracking_enabled') ? get_option('woocommerce_adlogica_tracking_enabled') : 'no'  // Backwards compat
            ),
            'track_login_step_for_guest_user' => array(
                'label' => __('Track Login step for Guest users if Guest Checkout is enabled &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Optional).', 'woocommerce'),
                'type' => 'checkbox',
                'checkboxgroup' => '',
                'description' => sprintf(__('For Guest users Login is not a mandatory step. Checking the box would consider the click event on place order as Login as well as Checkout.', 'woocommerce')),
                'default' => get_option('track_login_step_for_guest_user') ? get_option('track_login_step_for_guest_user') : 'no'  // Backwards compat
            )
        );
    }

// End init_form_fields()

    /**
     * Adlogica tracking
     *
     * @access public
     * @return void
     */
    function adlogica_tracking_code() {
        if (is_admin() || current_user_can('manage_options') || $this->adlogica_tracking_enabled == "no") {
            return;
        }

        $org_id = $this->adlogica_org_id;
        $app_id = $this->adlogica_app_id;

        if (!$org_id || !$app_id) {
            return;
        }

        echo "<script type='text/javascript' src='//snowplow-savvy-staging.s3.amazonaws.com/" . esc_js($org_id) ."/" . esc_js($app_id) ."/adlogica_tracker.js'></script>";
        
        echo "<script type='text/javascript'>digitalData = {}; </script>";
    }

    /**
     * Adlogica Transaction tracking
     *
     * @access public
     * @param mixed $order_id
     * @return void
     */
    function adlogica_transaction_code($order_id) {
        global $woocommerce, $current_user;

        if ($this->disable_tracking($this->adlogica_tracking_enabled) || current_user_can('manage_options'))
            return;

        // Get the order and track it
        $order = new WC_Order($order_id);
        $address = $order->get_shipping_address();
        
        $user = "digitalData.user = [{
                            profile: [{
                                profileInfo: {
                                  profileID: '" . esc_js($current_user->id) . "',
                                  email: '" . esc_js($current_user->user_email) . "'
                                },
                                address: {
                                  line1: '" . esc_js($order->billing_address_1) . "',
                                  line2: '" . esc_js($order->billing_address_2) . "',
                                  city: '" . esc_js($order->billing_city) . "',
                                  stateProvince: '" . esc_js($order->billing_state) . "',
                                  postalCode: '" . esc_js($order->billing_postcode) . "',
                                  country: '" . esc_js($order->billing_country) . "',
                                  firstname: '" . esc_js($order->billing_first_name) . "',
                                  lastname: '" . esc_js($order->billing_last_name) . "',
                                },
                                shipping_address: {
                                  line1: '" . esc_js($order->shipping_address_1) . "',
                                  line2: '" . esc_js($order->shipping_address_2) . "',
                                  city: '" . esc_js($order->shipping_city) . "',
                                  stateProvince: '" . esc_js($order->shipping_state) . "',
                                  postalCode: '" . esc_js($order->shipping_postcode) . "',
                                  country: '" . esc_js($order->shipping_country) . "',
                                  firstname: '" . esc_js($order->shipping_first_name) . "',
                                  lastname: '" . esc_js($order->shipping_last_name) . "',
                                }
                            }]
                        }];";
                        
        $transaction = "digitalData.transaction = {
                            transactionID: '" . esc_js($order->get_order_number()) . "',
                            total: {
                                transactionTotal: '" . esc_js($order->get_total()) . "',
                                shipping: '" . esc_js($order->get_shipping()) . "',
                                tax: '" . esc_js($order->get_total_tax()) . "'
                            },
                            profile: {
                                profileInfo: {
                                  profileID: '" . esc_js($current_user->id) . "',
                                  email: '" . esc_js($current_user->user_email) . "'
                                },
                                address: {
                                  line1: '" . esc_js($order->shipping_address_1) . "',
                                  line2: '" . esc_js($order->shipping_address_2) . "',
                                  city: '" . esc_js($order->shipping_city) . "',
                                  stateProvince: '" . esc_js($order->shipping_state) . "',
                                  postalCode: '" . esc_js($order->shipping_postcode) . "',
                                  country: '" . esc_js($order->shipping_country) . "',
                                  firstname: '" . esc_js($order->shipping_first_name) . "',
                                  lastname: '" . esc_js($order->shipping_last_name) . "',
                                }
                            },
                            item: []
                        };";
                                
        // Order items
        if ($order->get_items()) {
            foreach ($order->get_items() as $item) {
                $_product = $order->get_product_from_item($item);
                $category = get_the_terms($_product->id, 'product_cat');
                $categories = '';
                foreach ($category as $term) {
                    $categories.=$term->name . ',';
                }
                $src = (string) reset(simplexml_import_dom(DOMDocument::loadHTML($_product->get_image()))->xpath("//img/@src"));
                $transaction .= "var item = {
                    productInfo: {
                      productID: '" . esc_js($_product->id) . "',
                      productName: '" . esc_js($item['name']) . "',
                      description: '" . esc_js($item['name']) . "',
                      productURL: '" . esc_url( get_permalink( $_product->id ) ) . "',
                      sku: '" . esc_js($_product->get_sku()) . "',
                      productImage: '" . esc_js($src) . "'
                    },

                    category: {
                      primaryCategory: '" . esc_js($categories) . "'
                    },
                    quantity: '" . esc_js($item['qty']) . "',
                    price: {
                      basePrice: '" . esc_js($order->get_item_total($item)) . "',
                      currency: ''
                    }
                };";
                $transaction .= "digitalData.transaction.item.push(item);";
            }
        }

        echo '<script type="text/javascript">' . $user . '</script>';
        echo '<script type="text/javascript">' . $transaction . '</script>';

    }
    /**
     * Digital Data for single product add to cart
     *
     * @access public
     * @return void
     */
    function add_to_cart() {

        if ($this->disable_tracking($this->adlogica_tracking_enabled))
            return;

        if (!is_single())
            return;

        global $product, $woocommerce, $current_user;
        $src = (string) reset(simplexml_import_dom(DOMDocument::loadHTML($product->get_image()))->xpath("//img/@src"));
        // Add single quotes to allow jQuery to be substituted into _trackEvent parameters       
        $parameters['label'] = "'" . esc_js($product->get_sku() ? __('SKU:', 'woocommerce') . ' ' . $product->get_sku() : "#" . $product->id ) . "'";
        $category = get_the_terms($product->id, 'product_cat');
        $categories = '';
        foreach ($category as $term) {
            $categories.=$term->name . ',';
        }
        if (version_compare($woocommerce->version, '2.1', '>=')) {
            wc_enqueue_js("
			$('.single_add_to_cart_button').click(function() {
                 var quantity = '';
                var propertyMap = {quantity: quantity};
                digitalData.product = [{
                   productInfo: {
                     productID: '" . esc_js($product->id) . "',
                     productName: '" . esc_js($product->get_title()) . "',
                     description: '" . esc_js($product->get_title()) . "',
                     productURL: '" . esc_url( get_permalink( $product->id ) ) . "',
                     sku: '" . esc_js($product->get_sku()) . "',
                     productImage: '" . esc_js($src) . "'
                   },
                   category: {
                     primaryCategory: '" . esc_js($categories) . "'
                   }
                 }];
                for(var attr in digitalData.product[0].category){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].category[attr];
                }
                for(var attr in digitalData.product[0].productInfo){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].productInfo[attr];
                }
                var event_object = {
                  eventInfo: {
                    action: 'add_to_cart',
                    type: 'unstructured'
                  },
                  attributes: propertyMap,
                  context: custom_context,
                  pixel_definition: '',
                  pixel_id: ''
                }
                pm_cookie.setCookie('_adlogica_event', window.btoa(JSON.stringify(event_object)), 300000, '/', window.location.hostname);
               
			});
		");
        } else {
            $woocommerce->add_inline_js("
			$('.single_add_to_cart_button').click(function() {
                 var quantity = '';
                var propertyMap = {quantity: quantity};
                digitalData.product = [{
                   productInfo: {
                     productID: '" . esc_js($product->id) . "',
                     productName: '" . esc_js($product->get_title()) . "',
                     description: '" . esc_js($product->get_title()) . "',
                     productURL: '" . esc_url( get_permalink( $product->id ) ) . "',
                     sku: '" . esc_js($product->get_sku()) . "',
                     productImage: '" . esc_js($src) . "'
                   },
                   category: {
                     primaryCategory: '" . esc_js($categories) . "'
                   }
                 }];
                for(var attr in digitalData.product[0].category){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].category[attr];
                }
                for(var attr in digitalData.product[0].productInfo){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].productInfo[attr];
                }
                var event_object = {
                  eventInfo: {
                    action: 'add_to_cart',
                    type: 'unstructured'
                  },
                  attributes': propertyMap,
                  context: custom_context,
                  pixel_definition: '',
                  pixel_id: ''
                }
                pm_cookie.setCookie('_adlogica_event', window.btoa(JSON.stringify(event_object)), 300000, '/', window.location.hostname);
               
			});
		");
        }
    }

    /**
     * Enhanced E-commerce tracking for product detail view
     *
     * @access public
     * @return void
     */
    public function product_detail_view() {
        if ($this->disable_tracking($this->adlogica_tracking_enabled)) {
            return;
        }

        global $product;
        global $woocommerce;
        $category = get_the_terms($product->ID, 'product_cat');
        $categories = '';
        foreach ($category as $term) {
            $categories.=$term->name . ',';
        }
        $src = (string) reset(simplexml_import_dom(DOMDocument::loadHTML($product->get_image()))->xpath("//img/@src"));
        if (version_compare($woocommerce->version, '2.1', '>=')) {

            wc_enqueue_js("
                digitalData.product = [{
                   productInfo: {
                     productID: '" . esc_js($product->id) . "',
                     productName: '" . esc_js($product->get_title()) . "',
                     description: '" . esc_js($product->get_title()) . "',
                     productURL: '" . esc_url( get_permalink( $product->id ) ) . "',
                     sku: '" . esc_js($product->get_sku()) . "',
                     productImage: '" . esc_js($src) . "'
                   },
                   category: {
                     primaryCategory: '" . esc_js($categories) . "'
                   }
                }];
            ");
        } else {
            $woocommerce->add_inline_js("
                digitalData.product = [{
                   productInfo: {
                     productID: '" . esc_js($product->id) . "',
                     productName: '" . esc_js($product->get_title()) . "',
                     description: '" . esc_js($product->get_title()) . "',
                     productURL: '" . esc_url( get_permalink( $product->id ) ) . "',
                     sku: '" . esc_js($product->get_sku()) . "',
                     productImage: '" . esc_js($src) . "'
                   },
                   category: {
                     primaryCategory: '" . esc_js($categories) . "'
                   }
                }];
            ");
        }
    }

    /**
     * Enhanced E-commerce tracking for remove from cart
     *
     * @access public
     * @return void
     */
    public function remove_cart_tracking() {
        if ($this->disable_tracking($this->adlogica_tracking_enabled)) {
            return;
        }
        global $woocommerce;
        if (version_compare($woocommerce->version, '2.1', '>=')) {
            wc_enqueue_js("$('.remove').click(function(){
                var quantity = '';
                var propertyMap = {quantity: quantity};
                digitalData.product = [{
                   productInfo: {
                     productID: '" . esc_js($product->id) . "',
                     productName: '" . esc_js($product->get_title()) . "',
                     description: '" . esc_js($product->get_title()) . "',
                     productURL: '" . esc_url( get_permalink( $product->id ) ) . "',
                     sku: '" . esc_js($product->get_sku()) . "'
                   },
                   category: {
                     primaryCategory: '" . esc_js($categories) . "'
                   }
                 }];
                for(var attr in digitalData.product[0].category){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].category[attr];
                }
                for(var attr in digitalData.product[0].productInfo){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].productInfo[attr];
                }
                var event_object = {
                  eventInfo: {
                    action: 'remove_from_cart',
                    type: 'unstructured'
                  },
                  attributes': propertyMap,
                  context: custom_context,
                  pixel_definition: '',
                  pixel_id: ''
                }
                pm_cookie.setCookie('_adlogica_event', window.btoa(JSON.stringify(event_object)), 300000, '/', window.location.hostname);
               
              });"
            );
        } else {
            $woocommerce->add_inline_js("$('.remove').click(function(){
                var quantity = '';
                var propertyMap = {quantity: quantity};
                digitalData.product = [{
                   productInfo: {
                     productID: '" . esc_js($product->id) . "',
                     productName: '" . esc_js($product->get_title()) . "',
                     description: '" . esc_js($product->get_title()) . "',
                     productURL: '" . esc_url( get_permalink( $product->id ) ) . "',
                     sku: '" . esc_js($product->get_sku()) . "'
                   },
                   category: {
                     primaryCategory: '" . esc_js($categories) . "'
                   }
                 }];
                for(var attr in digitalData.product[0].category){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].category[attr];
                }
                for(var attr in digitalData.product[0].productInfo){
                  propertyMap[pm__convertToUnderscore(attr)] = digitalData.product[0].productInfo[attr];
                }
                var event_object = {
                  eventInfo: {
                    action: 'remove_from_cart',
                    type: 'unstructured'
                  },
                  attributes': propertyMap,
                  context: custom_context,
                  pixel_definition: '',
                  pixel_id: ''
                }
                pm_cookie.setCookie('_adlogica_event', window.btoa(JSON.stringify(event_object)), 300000, '/', window.location.hostname);
               
              });"
            );
        }
    }

    /**
     * Check if tracking is disabled
     *
     * @access private
     * @param mixed $type
     * @return bool
     */
    private function disable_tracking($type) {
        if (is_admin() || current_user_can('manage_options') || (!$this->adlogica_org_id )   || (!$this->adlogica_app_id ) || 'no' == $type) {
            return true;
        }
    }

}
