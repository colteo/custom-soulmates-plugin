<?php
/**
 * Custom T-Shirt Admin Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Custom_Tshirt_Admin {
    
    public function __construct() {
        // Aggiungi JavaScript per gestire la visualizzazione dei campi
        add_action('admin_footer', array($this, 'custom_tshirt_admin_js'));
        
        // Aggiungi colonna personalizzata nella lista ordini
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_tshirt_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_tshirt_column'), 10, 2);
        
        // Aggiungi meta box per visualizzare e scaricare le immagini personalizzate
        add_action('add_meta_boxes', array($this, 'add_custom_tshirt_meta_box'));
    }
    
    /**
     * Aggiungi JavaScript per l'admin
     */
    public function custom_tshirt_admin_js() {
        global $post_type;
        
        if ($post_type !== 'product') {
            return;
        }
        
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Mostra/nascondi tab in base al tipo di prodotto
                function handleProductTypeChange() {
                    var productType = $('#product-type').val();
                    
                    if (productType === 'custom_tshirt') {
                        // Nascondi le schede non necessarie
                        $('.inventory_options').addClass('hide_if_custom_tshirt');
                        $('.shipping_options').addClass('show_if_custom_tshirt');
                        $('.linked_product_options').addClass('hide_if_custom_tshirt');
                        $('.attribute_options').addClass('hide_if_custom_tshirt');
                        $('.variations_options').addClass('hide_if_custom_tshirt');
                        
                        // Mostra la nostra scheda
                        $('.custom_tshirt_options').addClass('show_if_custom_tshirt').removeClass('hide_if_custom_tshirt');
                        $('#custom_tshirt_product_data').addClass('show_if_custom_tshirt').removeClass('hide_if_custom_tshirt');
                        $('.show_if_custom_tshirt').show();
                    } else {
                        $('.show_if_custom_tshirt').hide();
                    }
                }
                
                $('#product-type').on('change', handleProductTypeChange);
                handleProductTypeChange();
                
                // Per il selettore di colori visuali
                $('#_custom_tshirt_visual_colors').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#_custom_tshirt_colors').closest('.form-field').append('<p class="description">Suggerimento: Usa nomi di colori standard come Rosso, Blu, Verde per una visualizzazione ottimale.</p>');
                    } else {
                        $('#_custom_tshirt_colors').closest('.form-field').find('p.description:last').remove();
                    }
                });
            });
        </script>
        <?php
    }
    
    /**
     * Aggiungi colonna nella lista ordini
     */
    public function add_order_tshirt_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;
            
            if ('order_status' === $column_name) {
                $new_columns['order_tshirts'] = __('Magliette', 'custom-tshirt-designer');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Mostra contenuto della colonna personalizzata
     */
    public function display_order_tshirt_column($column, $post_id) {
        if ('order_tshirts' === $column) {
            $order = wc_get_order($post_id);
            $count = 0;
            
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && 'custom_tshirt' === $product->get_type()) {
                    $count += $item->get_quantity();
                }
            }
            
            if ($count > 0) {
                echo '<mark class="order-tshirt-count">' . $count . '</mark>';
            } else {
                echo '<span class="na">—</span>';
            }
        }
    }
    
    /**
     * Aggiungi metabox per visualizzare immagini personalizzate
     */
    public function add_custom_tshirt_meta_box() {
        add_meta_box(
            'custom_tshirt_images',
            __('Immagini Magliette Personalizzate', 'custom-tshirt-designer'),
            array($this, 'display_custom_tshirt_images'),
            'shop_order',
            'normal',
            'default'
        );
    }
    
    /**
     * Visualizza immagini personalizzate nell'ordine
     */
    public function display_custom_tshirt_images($post) {
        $order = wc_get_order($post->ID);
        $has_custom_tshirts = false;
        
        echo '<div class="custom-tshirt-images-container">';
        
        foreach ($order->get_items() as $item_id => $item) {
            $image_path = wc_get_order_item_meta($item_id, '_custom_tshirt_image_path', true);
            
            if ($image_path) {
                $has_custom_tshirts = true;
                $upload_dir = wp_upload_dir();
                $image_url = $upload_dir['baseurl'] . '/' . $image_path;
                
                echo '<div class="custom-tshirt-image-box">';
                echo '<h4>' . __('Articolo:', 'custom-tshirt-designer') . ' ' . $item->get_name() . '</h4>';
                echo '<div class="custom-tshirt-details">';
                echo '<p><strong>' . __('Tipo:', 'custom-tshirt-designer') . '</strong> ' . wc_get_order_item_meta($item_id, __('Tipo', 'custom-tshirt-designer'), true) . '</p>';
                echo '<p><strong>' . __('Colore:', 'custom-tshirt-designer') . '</strong> ' . wc_get_order_item_meta($item_id, __('Colore', 'custom-tshirt-designer'), true) . '</p>';
                echo '<p><strong>' . __('Taglia:', 'custom-tshirt-designer') . '</strong> ' . wc_get_order_item_meta($item_id, __('Taglia', 'custom-tshirt-designer'), true) . '</p>';
                echo '<p><strong>' . __('Quantità:', 'custom-tshirt-designer') . '</strong> ' . $item->get_quantity() . '</p>';
                echo '</div>';
                echo '<div class="custom-tshirt-image">';
                echo '<img src="' . esc_url($image_url) . '" alt="' . __('Immagine Personalizzata', 'custom-tshirt-designer') . '" style="max-width: 200px;">';
                echo '</div>';
                echo '<a href="' . esc_url($image_url) . '" class="button" download>' . __('Scarica Immagine', 'custom-tshirt-designer') . '</a>';
                echo '</div>';
            }
        }
        
        if (!$has_custom_tshirts) {
            echo '<p>' . __('Nessuna maglietta personalizzata in questo ordine.', 'custom-tshirt-designer') . '</p>';
        }
        
        echo '</div>';
        
        // Stili inline per la visualizzazione
        ?>
        <style>
            .custom-tshirt-images-container {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
            }
            .custom-tshirt-image-box {
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 5px;
                width: calc(33.33% - 20px);
                min-width: 250px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .custom-tshirt-image-box h4 {
                margin-top: 0;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .custom-tshirt-details {
                margin-bottom: 15px;
            }
            .custom-tshirt-image {
                text-align: center;
                margin-bottom: 15px;
                background: #f9f9f9;
                padding: 10px;
                border-radius: 3px;
            }
            .custom-tshirt-image img {
                max-height: 150px;
                width: auto;
            }
        </style>
        <?php
    }
}