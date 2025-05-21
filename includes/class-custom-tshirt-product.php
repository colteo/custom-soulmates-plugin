<?php
/**
 * Custom T-Shirt Product Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Custom_Tshirt_Product {
    
    public function __construct() {
        // Aggiungi nuovo tipo di prodotto
        add_filter('product_type_selector', array($this, 'add_custom_tshirt_product_type'));
        
        // Schede prodotto personalizzate
        add_filter('woocommerce_product_data_tabs', array($this, 'add_custom_tshirt_product_data_tab'));
        
        // Campi personalizzati per il prodotto
        add_action('woocommerce_product_data_panels', array($this, 'add_custom_tshirt_product_data_fields'));
        
        // Salva campi personalizzati
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_tshirt_product_data'));
        
        // Carica script e stili admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Carica script e stili frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Valida i dati nel carrello
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_custom_tshirt_data'), 10, 3);
        
        // Aggiungi dati personalizzati al carrello
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_custom_tshirt_data_to_cart'), 10, 3);
        
        // Mostra dati personalizzati nel carrello
        add_filter('woocommerce_get_item_data', array($this, 'get_custom_tshirt_item_data'), 10, 2);
        
        // Aggiungi prezzi variabili in base al tipo
        add_action('woocommerce_before_calculate_totals', array($this, 'custom_tshirt_price_calculation'));
        
        // Aggiungi metadati all'ordine
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_custom_tshirt_order_item_meta'), 10, 4);
    }
    
    /**
     * Carica script e stili admin
     */
    public function enqueue_admin_scripts($hook) {
        // Carica solo nelle pagine pertinenti
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        $screen = get_current_screen();
        if (isset($screen->post_type) && 'product' !== $screen->post_type) {
            return;
        }
        
        wp_enqueue_media(); // Per il selettore di immagini
        wp_enqueue_style('custom-tshirt-admin-css', CUSTOM_TSHIRT_PLUGIN_URL . 'assets/admin/css/admin.css', array(), CUSTOM_TSHIRT_VERSION);
        wp_enqueue_script('custom-tshirt-admin-js', CUSTOM_TSHIRT_PLUGIN_URL . 'assets/admin/js/admin.js', array('jquery'), CUSTOM_TSHIRT_VERSION, true);
    }
    
    /**
     * Carica script e stili frontend
     */
    public function enqueue_frontend_scripts() {
        global $post;
        
        if (is_product() && $post) {
            $product = wc_get_product($post->ID);
            
            if ($product && 'custom_tshirt' === $product->get_type()) {
                wp_enqueue_style('custom-tshirt-css', CUSTOM_TSHIRT_PLUGIN_URL . 'assets/css/frontend.css', array(), CUSTOM_TSHIRT_VERSION);
                wp_enqueue_script('custom-tshirt-js', CUSTOM_TSHIRT_PLUGIN_URL . 'assets/js/designer.js', array('jquery'), CUSTOM_TSHIRT_VERSION, true);
                
                // Recupera tutti i tipi di magliette
                $tshirt_types = get_post_meta($post->ID, '_custom_tshirt_types', true);
                $tshirt_types = !empty($tshirt_types) ? json_decode($tshirt_types, true) : array();
                
                // Se non ci sono tipi, usa i vecchi campi basic/deluxe
                $types_data = array();
                if (empty($tshirt_types)) {
                    $basic_price = get_post_meta($post->ID, '_custom_tshirt_basic_price', true);
                    $deluxe_price = get_post_meta($post->ID, '_custom_tshirt_deluxe_price', true);
                    
                    if (!empty($basic_price)) {
                        $types_data['basic'] = array(
                            'name' => 'Basic',
                            'price' => $basic_price,
                            'description' => ''
                        );
                    }
                    
                    if (!empty($deluxe_price)) {
                        $types_data['deluxe'] = array(
                            'name' => 'Deluxe',
                            'price' => $deluxe_price,
                            'description' => ''
                        );
                    }
                } else {
                    foreach ($tshirt_types as $type) {
                        if (isset($type['id']) && isset($type['name']) && isset($type['price'])) {
                            $types_data[$type['id']] = array(
                                'name' => $type['name'],
                                'price' => $type['price'],
                                'description' => isset($type['description']) ? $type['description'] : ''
                            );
                        }
                    }
                }
                
                // Passa dati al JS
                wp_localize_script('custom-tshirt-js', 'customTshirtData', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'productId' => $post->ID,
                    'types' => $types_data,
                    'nonce' => wp_create_nonce('custom_tshirt_nonce')
                ));
            }
        }
    }
    
    /**
     * Carica il template personalizzato per il carrello
     */
    public function custom_tshirt_add_to_cart_template() {
        wc_get_template('single-product/add-to-cart/custom-tshirt.php', array(), '', CUSTOM_TSHIRT_PLUGIN_PATH . 'templates/');
    }
    
    /**
     * Aggiungi tipo di prodotto custom
     */
    public function add_custom_tshirt_product_type($types) {
        $types['custom_tshirt'] = __('Maglietta Personalizzata', 'custom-tshirt-designer');
        return $types;
    }
    
    /**
     * Aggiungi tab nelle impostazioni prodotto
     */
    public function add_custom_tshirt_product_data_tab($tabs) {
        $tabs['custom_tshirt'] = array(
            'label'    => __('Opzioni Maglietta', 'custom-tshirt-designer'),
            'target'   => 'custom_tshirt_product_data',
            'class'    => array('show_if_custom_tshirt'),
            'priority' => 21
        );
        return $tabs;
    }
    
    /**
     * Aggiungi campi dati nella scheda prodotto
     */
    public function add_custom_tshirt_product_data_fields() {
        echo '<div id="custom_tshirt_product_data" class="panel woocommerce_options_panel">';
        
        echo '<div class="options_group">';
        echo '<h3 style="padding-left: 12px;">' . __('Configurazione Opzioni Maglietta', 'custom-tshirt-designer') . '</h3>';
        
        // SEZIONE COLORI - Modifica per gestione dinamica
        echo '<div class="form-field color-options-field">';
        echo '<label>' . __('Colori Disponibili', 'custom-tshirt-designer') . '</label>';
        echo '<div class="color-tags-container">';
        
        // Recupera colori esistenti
        $colors = get_post_meta(get_the_ID(), '_custom_tshirt_colors', true);
        $colors_array = !empty($colors) ? array_filter(array_map('trim', explode("\n", $colors))) : array();
        
        // Mostra i colori come tag con possibilità di rimozione
        if (!empty($colors_array)) {
            foreach ($colors_array as $color) {
                echo '<span class="color-tag">' . esc_html($color) . '<a class="remove-tag">×</a></span>';
            }
        }
        
        echo '</div>';
        
        // Campo nascosto per salvare i colori
        echo '<input type="hidden" name="_custom_tshirt_colors" id="_custom_tshirt_colors" value="' . esc_attr($colors) . '" />';
        
        // Campo di input per aggiungere nuovi colori
        echo '<div class="color-input-container">';
        echo '<input type="text" id="new_color" placeholder="' . __('Aggiungi un colore e premi Invio', 'custom-tshirt-designer') . '" />';
        echo '</div>';
        echo '<p class="description">' . __('Aggiungi colori premendo Invio dopo ciascuno', 'custom-tshirt-designer') . '</p>';
        echo '</div>';
        
        // SEZIONE TAGLIE - Modifica per gestione dinamica
        echo '<div class="form-field size-options-field">';
        echo '<label>' . __('Taglie Disponibili', 'custom-tshirt-designer') . '</label>';
        echo '<div class="size-tags-container">';
        
        // Recupera taglie esistenti
        $sizes = get_post_meta(get_the_ID(), '_custom_tshirt_sizes', true);
        $sizes_array = !empty($sizes) ? array_filter(array_map('trim', explode("\n", $sizes))) : array();
        
        // Mostra le taglie come tag con possibilità di rimozione
        if (!empty($sizes_array)) {
            foreach ($sizes_array as $size) {
                echo '<span class="size-tag">' . esc_html($size) . '<a class="remove-tag">×</a></span>';
            }
        }
        
        echo '</div>';
        
        // Campo nascosto per salvare le taglie
        echo '<input type="hidden" name="_custom_tshirt_sizes" id="_custom_tshirt_sizes" value="' . esc_attr($sizes) . '" />';
        
        // Campo di input per aggiungere nuove taglie
        echo '<div class="size-input-container">';
        echo '<input type="text" id="new_size" placeholder="' . __('Aggiungi una taglia e premi Invio', 'custom-tshirt-designer') . '" />';
        echo '</div>';
        echo '<p class="description">' . __('Aggiungi taglie premendo Invio dopo ciascuna', 'custom-tshirt-designer') . '</p>';
        echo '</div>';
        
        echo '</div>'; // Chiudi options_group
        
        // SEZIONE TIPI DI MAGLIETTE con possibilità di aggiungere con pulsante "+"
        echo '<div class="options_group">';
        echo '<h3 style="padding-left: 12px;">' . __('Tipi di Magliette', 'custom-tshirt-designer') . '</h3>';

        // Contenitore per i tipi di magliette
        echo '<div class="tshirt-types-container">';

        // Recupera i tipi di magliette esistenti
        $tshirt_types = get_post_meta(get_the_ID(), '_custom_tshirt_types', true);
        $tshirt_types = !empty($tshirt_types) ? json_decode($tshirt_types, true) : array();

        // Se non ci sono tipi, aggiungiamo i tipi predefiniti "Basic" e "Deluxe"
        if (empty($tshirt_types)) {
            $tshirt_types = array(
                array(
                    'id' => 'basic',
                    'name' => 'Basic',
                    'price' => '15.00',
                    'description' => 'Stampa standard di buona qualità',
                    'image' => ''
                ),
                array(
                    'id' => 'deluxe',
                    'name' => 'Deluxe',
                    'price' => '25.00',
                    'description' => 'Stampa premium di alta qualità e maggiore durata',
                    'image' => ''
                )
            );
        }

        // Mostra i tipi di magliette esistenti
        foreach ($tshirt_types as $index => $type) {
            $type_id = isset($type['id']) ? $type['id'] : 'type_' . $index;
            $type_name = isset($type['name']) ? $type['name'] : '';
            $type_price = isset($type['price']) ? $type['price'] : '';
            $type_desc = isset($type['description']) ? $type['description'] : '';
            $type_image = isset($type['image']) ? $type['image'] : '';
            
            echo '<div class="tshirt-type-item" data-type-id="' . esc_attr($type_id) . '">';
            
            // Intestazione del tipo con nome e pulsanti
            echo '<div class="tshirt-type-header">';
            echo '<div class="tshirt-type-title">' . __('Tipo:', 'custom-tshirt-designer') . ' <span class="type-name-display">' . esc_html($type_name) . '</span></div>';
            echo '<div class="tshirt-type-actions">';
            echo '<a href="#" class="button collapse-type">' . __('Espandi/Comprimi', 'custom-tshirt-designer') . '</a>';
            echo '<a href="#" class="button remove-type">' . __('Rimuovi', 'custom-tshirt-designer') . '</a>';
            echo '</div>';
            echo '</div>';
            
            // Contenuto del tipo
            echo '<div class="tshirt-type-content">';
            
            // Nome
            echo '<div class="tshirt-type-field">';
            echo '<label>' . __('Nome', 'custom-tshirt-designer') . '</label>';
            echo '<input type="text" name="tshirt_type[' . $index . '][name]" value="' . esc_attr($type_name) . '" class="type-name-field" required>';
            echo '</div>';
            
            // Prezzo
            echo '<div class="tshirt-type-field">';
            echo '<label>' . __('Prezzo (€)', 'custom-tshirt-designer') . '</label>';
            echo '<input type="number" step="0.01" min="0" name="tshirt_type[' . $index . '][price]" value="' . esc_attr($type_price) . '" required>';
            echo '</div>';
            
            // Descrizione
            echo '<div class="tshirt-type-field" style="grid-column: span 2;">';
            echo '<label>' . __('Descrizione', 'custom-tshirt-designer') . '</label>';
            echo '<textarea name="tshirt_type[' . $index . '][description]" rows="2">' . esc_textarea($type_desc) . '</textarea>';
            echo '</div>';
            
            // Immagine (tramite Media Library)
            echo '<div class="tshirt-type-field" style="grid-column: span 2;">';
            echo '<label>' . __('Immagine', 'custom-tshirt-designer') . '</label>';
            echo '<div class="tshirt-type-image-container">';
            
            if (!empty($type_image)) {
                echo '<img src="' . esc_url($type_image) . '" class="tshirt-type-thumbnail">';
            }
            
            echo '<input type="hidden" name="tshirt_type[' . $index . '][image]" value="' . esc_attr($type_image) . '" class="tshirt-type-image-field">';
            echo '<button type="button" class="button upload-type-image">' . __('Scegli immagine', 'custom-tshirt-designer') . '</button>';
            
            if (!empty($type_image)) {
                echo '<button type="button" class="button remove-type-image">' . __('Rimuovi immagine', 'custom-tshirt-designer') . '</button>';
            }
            
            echo '</div>';
            echo '</div>';
            
            // Campo nascosto per l'ID
            echo '<input type="hidden" name="tshirt_type[' . $index . '][id]" value="' . esc_attr($type_id) . '">';
            
            echo '</div>'; // Chiudi tshirt-type-content
            echo '</div>'; // Chiudi tshirt-type-item
        }

        // Pulsante per aggiungere un nuovo tipo
        echo '<div class="add-tshirt-type-button">';
        echo '<span class="dashicons dashicons-plus-alt"></span> ' . __('Aggiungi nuovo tipo di maglietta', 'custom-tshirt-designer');
        echo '</div>';

        // Template per nuovi tipi (nascosto, usato da JavaScript)
        echo '<script type="text/template" id="tshirt-type-template">
            <div class="tshirt-type-item" data-type-id="{{id}}">
                <div class="tshirt-type-header">
                    <div class="tshirt-type-title">' . __('Tipo:', 'custom-tshirt-designer') . ' <span class="type-name-display">{{name}}</span></div>
                    <div class="tshirt-type-actions">
                        <a href="#" class="button collapse-type">' . __('Espandi/Comprimi', 'custom-tshirt-designer') . '</a>
                        <a href="#" class="button remove-type">' . __('Rimuovi', 'custom-tshirt-designer') . '</a>
                    </div>
                </div>
                <div class="tshirt-type-content">
                    <div class="tshirt-type-field">
                        <label>' . __('Nome', 'custom-tshirt-designer') . '</label>
                        <input type="text" name="tshirt_type[{{index}}][name]" value="{{name}}" class="type-name-field" required>
                    </div>
                    <div class="tshirt-type-field">
                        <label>' . __('Prezzo (€)', 'custom-tshirt-designer') . '</label>
                        <input type="number" step="0.01" min="0" name="tshirt_type[{{index}}][price]" value="{{price}}" required>
                    </div>
                    <div class="tshirt-type-field" style="grid-column: span 2;">
                        <label>' . __('Descrizione', 'custom-tshirt-designer') . '</label>
                        <textarea name="tshirt_type[{{index}}][description]" rows="2">{{description}}</textarea>
                    </div>
                    <div class="tshirt-type-field" style="grid-column: span 2;">
                        <label>' . __('Immagine', 'custom-tshirt-designer') . '</label>
                        <div class="tshirt-type-image-container">
                            <input type="hidden" name="tshirt_type[{{index}}][image]" value="{{image}}" class="tshirt-type-image-field">
                            <button type="button" class="button upload-type-image">' . __('Scegli immagine', 'custom-tshirt-designer') . '</button>
                        </div>
                    </div>
                    <input type="hidden" name="tshirt_type[{{index}}][id]" value="{{id}}">
                </div>
            </div>
        </script>';

        // Campo nascosto per memorizzare tutti i tipi in JSON
        echo '<input type="hidden" name="_custom_tshirt_types" id="_custom_tshirt_types" value="' . esc_attr(json_encode($tshirt_types)) . '">';

        echo '</div>'; // Chiudi tshirt-types-container
        echo '</div>'; // Chiudi options_group
        
        // Opzioni avanzate
        echo '<div class="options_group">';
        echo '<h3 style="padding-left: 12px;">' . __('Opzioni Avanzate', 'custom-tshirt-designer') . '</h3>';
        
        // Guida alle taglie
        woocommerce_wp_textarea_input(array(
            'id'          => '_custom_tshirt_size_guide',
            'label'       => __('Guida alle Taglie', 'custom-tshirt-designer'),
            'description' => __('Inserisci una descrizione per la guida alle taglie (opzionale). Supporta HTML.', 'custom-tshirt-designer'),
            'desc_tip'    => true
        ));
        
        // Dimensione massima upload
        woocommerce_wp_text_input(array(
            'id'          => '_custom_tshirt_max_upload_size',
            'label'       => __('Dimensione Max Upload (MB)', 'custom-tshirt-designer'),
            'description' => __('Dimensione massima dei file caricati in MB. Lascia vuoto per usare le impostazioni predefinite.', 'custom-tshirt-designer'),
            'desc_tip'    => true,
            'type'        => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '1'
            )
        ));
        
        // Formati immagine consentiti
        woocommerce_wp_select(array(
            'id'          => '_custom_tshirt_allowed_formats',
            'label'       => __('Formati Immagine Consentiti', 'custom-tshirt-designer'),
            'description' => __('Seleziona i formati di immagine consentiti per l\'upload', 'custom-tshirt-designer'),
            'desc_tip'    => true,
            'options'     => array(
                'all'       => __('Tutti (JPG, PNG, GIF)', 'custom-tshirt-designer'),
                'jpg_png'   => __('Solo JPG e PNG', 'custom-tshirt-designer'),
                'jpg_only'  => __('Solo JPG', 'custom-tshirt-designer'),
                'png_only'  => __('Solo PNG', 'custom-tshirt-designer')
            )
        ));
        
        // Testo istruzioni personalizzate
        woocommerce_wp_textarea_input(array(
            'id'          => '_custom_tshirt_custom_instructions',
            'label'       => __('Istruzioni Personalizzate', 'custom-tshirt-designer'),
            'description' => __('Inserisci istruzioni personalizzate da mostrare agli utenti (opzionale). Supporta HTML.', 'custom-tshirt-designer'),
            'desc_tip'    => true
        ));
        echo '</div>';
        
        echo '</div>'; // Chiudi panel
    }
    
    /**
     * Salva i dati personalizzati del prodotto
     */
    public function save_custom_tshirt_product_data($post_id) {
        // Salva colori
        if (isset($_POST['_custom_tshirt_colors'])) {
            $colors = wc_clean(wp_unslash($_POST['_custom_tshirt_colors']));
            
            // Verifica se contiene valori multipli separati da spazi invece che da newline
            if (strpos($colors, "\n") === false && strpos($colors, ' ') !== false) {
                // Converte spazi in newline
                $colors_array = array_filter(array_map('trim', explode(' ', $colors)));
                $colors = implode("\n", $colors_array);
            }
            
            update_post_meta($post_id, '_custom_tshirt_colors', $colors);
        }
        
        // Salva taglie (stessa logica)
        if (isset($_POST['_custom_tshirt_sizes'])) {
            $sizes = wc_clean(wp_unslash($_POST['_custom_tshirt_sizes']));
            
            // Verifica se contiene valori multipli separati da spazi invece che da newline
            if (strpos($sizes, "\n") === false && strpos($sizes, ' ') !== false) {
                // Converte spazi in newline
                $sizes_array = array_filter(array_map('trim', explode(' ', $sizes)));
                $sizes = implode("\n", $sizes_array);
            }
            
            update_post_meta($post_id, '_custom_tshirt_sizes', $sizes);
        }
        
        // Salva tipi di magliette
        if (isset($_POST['tshirt_type']) && is_array($_POST['tshirt_type'])) {
            $tshirt_types = array();
            
            foreach ($_POST['tshirt_type'] as $type) {
                if (empty($type['name']) || !isset($type['price'])) {
                    continue;
                }
                
                $tshirt_types[] = array(
                    'id' => isset($type['id']) ? sanitize_key($type['id']) : 'type_' . uniqid(),
                    'name' => sanitize_text_field($type['name']),
                    'price' => wc_format_decimal($type['price']),
                    'description' => isset($type['description']) ? sanitize_textarea_field($type['description']) : '',
                    'image' => isset($type['image']) ? esc_url_raw($type['image']) : ''
                );
            }
            
            update_post_meta($post_id, '_custom_tshirt_types', json_encode($tshirt_types));
            
            // Per retrocompatibilità, manteniamo ancora i campi basic/deluxe
            $basic_price = '';
            $deluxe_price = '';
            
            foreach ($tshirt_types as $type) {
                if ($type['id'] === 'basic' || strtolower($type['name']) === 'basic') {
                    $basic_price = $type['price'];
                } elseif ($type['id'] === 'deluxe' || strtolower($type['name']) === 'deluxe') {
                    $deluxe_price = $type['price'];
                }
            }
            
            update_post_meta($post_id, '_custom_tshirt_basic_price', $basic_price);
            update_post_meta($post_id, '_custom_tshirt_deluxe_price', $deluxe_price);
        }
        
        // Salva altri campi esistenti
        $size_guide = isset($_POST['_custom_tshirt_size_guide']) ? wp_kses_post($_POST['_custom_tshirt_size_guide']) : '';
        update_post_meta($post_id, '_custom_tshirt_size_guide', $size_guide);
        
        $max_upload_size = isset($_POST['_custom_tshirt_max_upload_size']) ? absint($_POST['_custom_tshirt_max_upload_size']) : '';
        update_post_meta($post_id, '_custom_tshirt_max_upload_size', $max_upload_size);
        
        $allowed_formats = isset($_POST['_custom_tshirt_allowed_formats']) ? wc_clean($_POST['_custom_tshirt_allowed_formats']) : 'all';
        update_post_meta($post_id, '_custom_tshirt_allowed_formats', $allowed_formats);
        
        $custom_instructions = isset($_POST['_custom_tshirt_custom_instructions']) ? wp_kses_post($_POST['_custom_tshirt_custom_instructions']) : '';
        update_post_meta($post_id, '_custom_tshirt_custom_instructions', $custom_instructions);
    }
    
    /**
     * Validazione dei dati personalizzati
     */
    public function validate_custom_tshirt_data($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        if ($product->get_type() !== 'custom_tshirt') {
            return $passed;
        }
        
        // Verifica immagine
        if (empty($_POST['custom_tshirt_image_path'])) {
            wc_add_notice(__('Devi caricare un\'immagine per la tua maglietta personalizzata.', 'custom-tshirt-designer'), 'error');
            $passed = false;
        }
        
        // Verifica tipo
        if (empty($_POST['custom_tshirt_type'])) {
            wc_add_notice(__('Seleziona il tipo di maglietta.', 'custom-tshirt-designer'), 'error');
            $passed = false;
        }
        
        // Verifica colore
        if (empty($_POST['custom_tshirt_color'])) {
            wc_add_notice(__('Seleziona il colore della maglietta.', 'custom-tshirt-designer'), 'error');
            $passed = false;
        }
        
        // Verifica taglia
        if (empty($_POST['custom_tshirt_size'])) {
            wc_add_notice(__('Seleziona la taglia della maglietta.', 'custom-tshirt-designer'), 'error');
            $passed = false;
        }
        
        return $passed;
    }
    
    /**
     * Aggiungi dati personalizzati al carrello
     */
    public function add_custom_tshirt_data_to_cart($cart_item_data, $product_id, $variation_id) {
        $product = wc_get_product($product_id);
        
        if ($product->get_type() !== 'custom_tshirt') {
            return $cart_item_data;
        }
        
        // Debug per verificare i dati in ingresso
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verifica se i dati sono presenti
        if (isset($_POST['custom_tshirt_type'], $_POST['custom_tshirt_color'], $_POST['custom_tshirt_size'], $_POST['custom_tshirt_image_path'])) {
            // Standardizziamo usando il formato "key" senza prefisso
            $cart_item_data['custom_tshirt_data'] = array(
                'image_path' => sanitize_text_field($_POST['custom_tshirt_image_path']),
                'type' => sanitize_text_field($_POST['custom_tshirt_type']),
                'color' => sanitize_text_field($_POST['custom_tshirt_color']),
                'size' => sanitize_text_field($_POST['custom_tshirt_size'])
            );
            
            // Debug per verificare i dati che vengono salvati
            error_log('Dati salvati nel carrello: ' . print_r($cart_item_data['custom_tshirt_data'], true));
            
            // Crea un ID univoco basato sui dati personalizzati
            $cart_item_data['unique_key'] = md5(json_encode($cart_item_data['custom_tshirt_data']));
        } else {
            error_log('Dati personalizzati mancanti nella richiesta POST');
        }
        
        return $cart_item_data;
    }
    
    /**
     * Mostra dati personalizzati nel carrello
     */
    public function get_custom_tshirt_item_data($item_data, $cart_item) {
        if (isset($cart_item['custom_tshirt_data'])) {
            $custom_data = $cart_item['custom_tshirt_data'];
            
            // Recupera il nome del tipo dalla configurazione
            $type_id = $custom_data['custom_tshirt_type'];
            $type_name = $type_id; // Valore predefinito
            $product_id = $cart_item['product_id'];
            
            // Cerca il nome nei tipi personalizzati
            $tshirt_types = get_post_meta($product_id, '_custom_tshirt_types', true);
            $tshirt_types = !empty($tshirt_types) ? json_decode($tshirt_types, true) : array();
            
            if (!empty($tshirt_types)) {
                foreach ($tshirt_types as $type) {
                    if (isset($type['id']) && $type['id'] === $type_id) {
                        $type_name = $type['name'];
                        break;
                    }
                }
            }
            
            $item_data[] = array(
                'key'   => __('Tipo', 'custom-tshirt-designer'),
                'value' => $type_name
            );
            
            $item_data[] = array(
                'key'   => __('Colore', 'custom-tshirt-designer'),
                'value' => $custom_data['custom_tshirt_color']
            );
            
            $item_data[] = array(
                'key'   => __('Taglia', 'custom-tshirt-designer'),
                'value' => $custom_data['custom_tshirt_size']
            );
        }
        
        return $item_data;
    }
    
    /**
     * Helper: Ottieni valore tipo standardizzato
     */
    private function get_type_value($data) {
        if (isset($data['type'])) {
            return $data['type'];
        } elseif (isset($data['custom_tshirt_type'])) {
            return $data['custom_tshirt_type'];
        }
        return '';
    }

    /**
     * Helper: Ottieni valore colore standardizzato
     */
    private function get_color_value($data) {
        if (isset($data['color'])) {
            return $data['color'];
        } elseif (isset($data['custom_tshirt_color'])) {
            return $data['custom_tshirt_color'];
        }
        return '';
    }

    /**
     * Helper: Ottieni valore taglia standardizzato
     */
    private function get_size_value($data) {
        if (isset($data['size'])) {
            return $data['size'];
        } elseif (isset($data['custom_tshirt_size'])) {
            return $data['custom_tshirt_size'];
        }
        return '';
    }

    /**
     * Helper: Ottieni percorso immagine standardizzato
     */
    private function get_image_path_value($data) {
        if (isset($data['image_path'])) {
            return $data['image_path'];
        } elseif (isset($data['custom_tshirt_image_path'])) {
            return $data['custom_tshirt_image_path'];
        }
        return '';
    }

    /**
     * Calcolo prezzi in base al tipo
     */
    public function custom_tshirt_price_calculation($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['custom_tshirt_data'])) {
                $product_id = $cart_item['product_id'];
                $product = wc_get_product($product_id);
                
                // Usa il metodo helper per ottenere il tipo
                $type_id = $this->get_type_value($cart_item['custom_tshirt_data']);
                
                // Cerca il prezzo nei tipi personalizzati
                $tshirt_types = get_post_meta($product_id, '_custom_tshirt_types', true);
                $tshirt_types = !empty($tshirt_types) ? json_decode($tshirt_types, true) : array();
                
                $price = null;
                
                // Cerca nei tipi personalizzati
                if (!empty($tshirt_types)) {
                    foreach ($tshirt_types as $type) {
                        if (isset($type['id']) && $type['id'] === $type_id) {
                            $price = floatval($type['price']);
                            break;
                        }
                    }
                }
                
                // Fallback per retrocompatibilità
                if ($price === null) {
                    if ($type_id === 'basic') {
                        $price = floatval(get_post_meta($product_id, '_custom_tshirt_basic_price', true));
                    } else {
                        $price = floatval(get_post_meta($product_id, '_custom_tshirt_deluxe_price', true));
                    }
                }
                
                if ($price > 0) {
                    $cart_item['data']->set_price($price);
                }
            }
        }
    }

    /**
     * Aggiungi metadati all'ordine
     */
    public function add_custom_tshirt_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['custom_tshirt_data'])) {
            $custom_data = $values['custom_tshirt_data'];
            $product_id = $item->get_product_id();
            
            // Usa i metodi helper per ottenere i valori
            $type_id = $this->get_type_value($custom_data);
            $color = $this->get_color_value($custom_data);
            $size = $this->get_size_value($custom_data);
            $image_path = $this->get_image_path_value($custom_data);
            
            $type_name = $type_id; // Valore predefinito
            
            // Cerca il nome nei tipi personalizzati
            $tshirt_types = get_post_meta($product_id, '_custom_tshirt_types', true);
            $tshirt_types = !empty($tshirt_types) ? json_decode($tshirt_types, true) : array();
            
            if (!empty($tshirt_types)) {
                foreach ($tshirt_types as $type) {
                    if (isset($type['id']) && $type['id'] === $type_id) {
                        $type_name = $type['name'];
                        break;
                    }
                }
            }
            
            $item->add_meta_data(__('Tipo', 'custom-tshirt-designer'), $type_name);
            $item->add_meta_data(__('Colore', 'custom-tshirt-designer'), $color);
            $item->add_meta_data(__('Taglia', 'custom-tshirt-designer'), $size);
            $item->add_meta_data('_custom_tshirt_image_path', $image_path, true);
        }
    }
}