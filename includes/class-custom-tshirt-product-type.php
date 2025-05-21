<?php
/**
 * Custom T-Shirt Product Type Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Classe che definisce il tipo di prodotto "Custom T-Shirt"
 */
class WC_Product_Custom_Tshirt extends WC_Product {
    
    /**
     * Initialize custom product type
     */
    public function __construct($product = 0) {
        $this->supports = array(
            'ajax_add_to_cart',
            'customizable',  // Aggiungi supporto per prodotti personalizzabili
        );
        parent::__construct($product);
    }
    
    /**
     * Get internal type
     */
    public function get_type() {
        return 'custom_tshirt';
    }
    
    /**
     * Return if the product is purchasable
     */
    public function is_purchasable() {
        return true;
    }
    
    /**
     * Return if the product is sold individually
     */
    public function is_sold_individually() {
        return false;
    }
    
    /**
     * Return if the product needs configuration
     */
    public function requires_configuration() {
        return true;
    }
    
    /**
     * Add to cart text
     */
    public function add_to_cart_text() {
        return apply_filters('woocommerce_product_add_to_cart_text', __('Personalizza', 'custom-tshirt-designer'), $this);
    }
    
    /**
     * Add to cart description
     */
    public function add_to_cart_description() {
        return apply_filters('woocommerce_product_add_to_cart_description', __('Personalizza la tua maglietta', 'custom-tshirt-designer'), $this);
    }
    
    /**
     * Get price HTML
     */
    public function get_price_html($price = '') {
        // Recupera i tipi di magliette
        $tshirt_types = $this->get_meta('_custom_tshirt_types');
        $tshirt_types = !empty($tshirt_types) ? json_decode($tshirt_types, true) : array();
        
        // Se non ci sono tipi, usa i vecchi campi basic/deluxe
        if (empty($tshirt_types)) {
            $basic_price = $this->get_meta('_custom_tshirt_basic_price');
            $deluxe_price = $this->get_meta('_custom_tshirt_deluxe_price');
            
            if (!empty($basic_price) && !empty($deluxe_price)) {
                if ($basic_price == $deluxe_price) {
                    $price = wc_price($basic_price);
                } else {
                    $min_price = min($basic_price, $deluxe_price);
                    $price = sprintf(__('A partire da %s', 'custom-tshirt-designer'), wc_price($min_price));
                }
            } elseif (!empty($basic_price)) {
                $price = wc_price($basic_price);
            } elseif (!empty($deluxe_price)) {
                $price = wc_price($deluxe_price);
            }
        } else {
            // Trova il prezzo minimo tra tutti i tipi
            $prices = array();
            foreach ($tshirt_types as $type) {
                if (!empty($type['price'])) {
                    $prices[] = floatval($type['price']);
                }
            }
            
            if (!empty($prices)) {
                $min_price = min($prices);
                if (count($prices) > 1) {
                    $price = sprintf(__('A partire da %s', 'custom-tshirt-designer'), wc_price($min_price));
                } else {
                    $price = wc_price($min_price);
                }
            }
        }
        
        return apply_filters('woocommerce_get_price_html', $price, $this);
    }
}