<?php
/**
 * Custom T-Shirt Cart Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Custom_Tshirt_Cart {
    
    public function __construct() {
        // Personalizzazione del flusso del carrello
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'checkout_create_order_line_item'), 10, 4);
        
        // Ajax per aggiungere la stessa maglietta con diverse opzioni
        add_action('wp_ajax_add_another_tshirt_variant', array($this, 'ajax_add_another_variant'));
        add_action('wp_ajax_nopriv_add_another_tshirt_variant', array($this, 'ajax_add_another_variant'));

        add_action('wp_ajax_add_multiple_tshirt_variants', array($this, 'ajax_add_multiple_tshirt_variants'));
        add_action('wp_ajax_nopriv_add_multiple_tshirt_variants', array($this, 'ajax_add_multiple_tshirt_variants'));
    }
    
    /**
     * Validazione durante l'aggiunta al carrello
     */
    public function validate_add_to_cart($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        if (!$product || 'custom_tshirt' !== $product->get_type()) {
            return $passed;
        }
        
        // Controlla se l'immagine è stata caricata
        if (empty($_POST['custom_tshirt_image_path'])) {
            wc_add_notice(__('È necessario caricare un\'immagine per la maglietta personalizzata.', 'custom-tshirt-designer'), 'error');
            $passed = false;
        }
        
        return $passed;
    }
    
    /**
     * Aggiungi dati personalizzati all'elemento carrello
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $product = wc_get_product($product_id);
        
        if (!$product || 'custom_tshirt' !== $product->get_type()) {
            return $cart_item_data;
        }
        
        // Raccogli tutti i dati personalizzati
        if (isset($_POST['custom_tshirt_image_path'], $_POST['custom_tshirt_type'], $_POST['custom_tshirt_color'], $_POST['custom_tshirt_size'])) {
            $cart_item_data['custom_tshirt_data'] = array(
                'image_path' => sanitize_text_field($_POST['custom_tshirt_image_path']),
                'type' => sanitize_text_field($_POST['custom_tshirt_type']),
                'color' => sanitize_text_field($_POST['custom_tshirt_color']),
                'size' => sanitize_text_field($_POST['custom_tshirt_size'])
            );
            
            // Crea un ID univoco per questo elemento
            $cart_item_data['unique_key'] = md5(json_encode($cart_item_data['custom_tshirt_data']));
        }
        
        return $cart_item_data;
    }
    
    /**
     * Ripristina i dati personalizzati dalla sessione
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['custom_tshirt_data'])) {
            $cart_item['custom_tshirt_data'] = $values['custom_tshirt_data'];
            $cart_item['unique_key'] = $values['unique_key'];
            
            // Aggiorna il prezzo in base al tipo
            $product = $cart_item['data'];
            $product_id = $product->get_id();
            
            if ('basic' === $cart_item['custom_tshirt_data']['type']) {
                $price = get_post_meta($product_id, '_custom_tshirt_basic_price', true);
            } else {
                $price = get_post_meta($product_id, '_custom_tshirt_deluxe_price', true);
            }
            
            if ($price) {
                $product->set_price($price);
            }
        }
        
        return $cart_item;
    }
    
    /**
     * Mostra dati personalizzati nel carrello
     */
    public function get_item_data($item_data, $cart_item) {
        // Debug: Aggiungi questo per vedere cosa c'è nel cart_item
        error_log('Cart Item: ' . print_r($cart_item, true));
        
        if (isset($cart_item['custom_tshirt_data'])) {
            $data = $cart_item['custom_tshirt_data'];
            error_log('Custom T-Shirt Data: ' . print_r($data, true));
            
            // Forza l'aggiunta direttamente al $item_data, bypassando qualsiasi logica standard
            $item_data[] = array(
                'key'     => __('Tipo', 'custom-tshirt-designer'),
                'value'   => isset($data['type']) ? ucfirst($data['type']) : 'N/A',
                'display' => '<strong>'.__('Tipo', 'custom-tshirt-designer').':</strong> ' . 
                            (isset($data['type']) ? ucfirst($data['type']) : 'N/A')
            );
            
            $item_data[] = array(
                'key'     => __('Colore', 'custom-tshirt-designer'),
                'value'   => isset($data['color']) ? $data['color'] : 'N/A',
                'display' => '<strong>'.__('Colore', 'custom-tshirt-designer').':</strong> ' . 
                            (isset($data['color']) ? $data['color'] : 'N/A')
            );
            
            $item_data[] = array(
                'key'     => __('Taglia', 'custom-tshirt-designer'),
                'value'   => isset($data['size']) ? $data['size'] : 'N/A',
                'display' => '<strong>'.__('Taglia', 'custom-tshirt-designer').':</strong> ' . 
                            (isset($data['size']) ? $data['size'] : 'N/A')
            );
        }
        
        return $item_data;
    }
    
    /**
     * Aggiungi metadati all'elemento dell'ordine
     */
    public function checkout_create_order_line_item($item, $cart_item_key, $values, $order) {
        if (isset($values['custom_tshirt_data'])) {
            $data = $values['custom_tshirt_data'];
            
            // Aggiungi i metadati usando il formato appropriato
            if (isset($data['type'])) {
                $item->add_meta_data(__('Tipo', 'custom-tshirt-designer'), ucfirst($data['type']));
            }
            
            if (isset($data['color'])) {
                $item->add_meta_data(__('Colore', 'custom-tshirt-designer'), $data['color']);
            }
            
            if (isset($data['size'])) {
                $item->add_meta_data(__('Taglia', 'custom-tshirt-designer'), $data['size']);
            }
            
            // Salva il percorso dell'immagine
            if (isset($data['image_path'])) {
                $item->add_meta_data('_custom_tshirt_image_path', $data['image_path'], true);
            }
        }
    }
        
    /**
     * AJAX per aggiungere varianti multiple
     */
    public function ajax_add_another_variant() {
        check_ajax_referer('add_tshirt_variants', 'security');
        
        if (!isset($_POST['variants']) || !is_array($_POST['variants'])) {
            wp_send_json_error(array('message' => __('Dati varianti mancanti', 'custom-tshirt-designer')));
        }
        
        $success = true;
        $variants = $_POST['variants'];
        
        foreach ($variants as $variant) {
            $cart_item_data = array(
                'custom_tshirt_data' => array(
                    'type' => sanitize_text_field($variant['type']),      // Usa 'type' invece di 'custom_tshirt_type'
                    'color' => sanitize_text_field($variant['color']),    // Usa 'color' invece di 'custom_tshirt_color'
                    'size' => sanitize_text_field($variant['size']),      // Usa 'size' invece di 'custom_tshirt_size'
                    'image_path' => sanitize_text_field($variant['image_path'])
                ),
                'unique_key' => md5(json_encode(array(
                    'type' => sanitize_text_field($variant['type']),
                    'color' => sanitize_text_field($variant['color']),
                    'size' => sanitize_text_field($variant['size'])
                )))
            );
            
            // Crea ID univoco
            $cart_item_data['unique_key'] = md5(json_encode($cart_item_data['custom_tshirt_data']));
            
            // Aggiungi al carrello
            $product_id = absint($variant['product_id']);
            $quantity = absint($variant['quantity']);
            
            if (!WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data)) {
                $success = false;
            }
        }
        
        if ($success) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => __('Errore durante l\'aggiunta delle varianti al carrello', 'custom-tshirt-designer')));
        }
    }

    /**
     * Gestisce l'aggiunta di varianti multiple tramite AJAX
     */
    public function ajax_add_multiple_tshirt_variants() {
        check_ajax_referer('custom_tshirt_nonce', 'security');
        
        if (!isset($_POST['variants']) || !is_array($_POST['variants'])) {
            wp_send_json_error(array('message' => __('Dati varianti mancanti', 'custom-tshirt-designer')));
        }
        
        $success = true;
        $variants = $_POST['variants'];
        $error_messages = array();
        
        // Verifica che l'immagine sia stata caricata
        if (empty($variants[0]['image_path'])) {
            wp_send_json_error(array('message' => __('È necessario caricare un\'immagine per la maglietta personalizzata.', 'custom-tshirt-designer')));
            return;
        }
        
        // Verifica validità delle varianti
        foreach ($variants as $index => $variant) {
            if (empty($variant['type'])) {
                $error_messages[] = sprintf(__('Variante #%d: Seleziona il tipo di maglietta.', 'custom-tshirt-designer'), $index + 1);
                $success = false;
            }
            
            if (empty($variant['color'])) {
                $error_messages[] = sprintf(__('Variante #%d: Seleziona il colore della maglietta.', 'custom-tshirt-designer'), $index + 1);
                $success = false;
            }
            
            if (empty($variant['size'])) {
                $error_messages[] = sprintf(__('Variante #%d: Seleziona la taglia della maglietta.', 'custom-tshirt-designer'), $index + 1);
                $success = false;
            }
            
            if (empty($variant['quantity']) || $variant['quantity'] < 1) {
                $error_messages[] = sprintf(__('Variante #%d: La quantità deve essere almeno 1.', 'custom-tshirt-designer'), $index + 1);
                $success = false;
            }
        }
        
        if (!$success) {
            wp_send_json_error(array('message' => implode('<br>', $error_messages)));
            return;
        }
        
        // Aggiungi tutte le varianti al carrello
        foreach ($variants as $variant) {
            $cart_item_data = array(
                'custom_tshirt_data' => array(
                    'image_path' => sanitize_text_field($variant['image_path']),
                    'type' => sanitize_text_field($variant['type']),
                    'color' => sanitize_text_field($variant['color']),
                    'size' => sanitize_text_field($variant['size'])
                )
            );
            
            // Crea ID univoco
            $cart_item_data['unique_key'] = md5(json_encode($cart_item_data['custom_tshirt_data']));
            
            // Aggiungi al carrello
            $product_id = absint($variant['product_id']);
            $quantity = absint($variant['quantity']);
            
            if (!WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data)) {
                $success = false;
                $error_messages[] = __('Errore durante l\'aggiunta al carrello', 'custom-tshirt-designer');
            }
        }
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Prodotti aggiunti al carrello con successo', 'custom-tshirt-designer'),
                'redirect' => wc_get_cart_url()
            ));
        } else {
            wp_send_json_error(array('message' => implode('<br>', $error_messages)));
        }
    }
}