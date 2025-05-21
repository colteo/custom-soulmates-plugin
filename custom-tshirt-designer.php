<?php
/**
 * Plugin Name: Custom T-Shirt Designer
 * Description: Un plugin per WooCommerce che permette ai clienti di personalizzare e ordinare magliette.
 * Version: 1.0.0
 * Author: Il tuo nome
 * Text Domain: custom-tshirt-designer
 * Domain Path: /languages
 * WC requires at least: 4.0.0
 * WC tested up to: 7.5.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Definizioni costanti
define('CUSTOM_TSHIRT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CUSTOM_TSHIRT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CUSTOM_TSHIRT_VERSION', '1.0.0');

// Controllo se WooCommerce è attivo
function custom_tshirt_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'custom_tshirt_woocommerce_notice');
        return false;
    }
    return true;
}

// Avviso se WooCommerce non è attivo
function custom_tshirt_woocommerce_notice() {
    ?>
    <div class="error">
        <p><?php _e('Custom T-Shirt Designer richiede che WooCommerce sia installato e attivato!', 'custom-tshirt-designer'); ?></p>
    </div>
    <?php
}

// Carica i file necessari
function custom_tshirt_load_files() {
    if (!custom_tshirt_check_woocommerce()) {
        return;
    }
    
    // Inclusione della classe per il tipo di prodotto
    require_once CUSTOM_TSHIRT_PLUGIN_PATH . 'includes/class-custom-tshirt-product-type.php';
    
    // Inclusione delle altre classi
    require_once CUSTOM_TSHIRT_PLUGIN_PATH . 'includes/class-custom-tshirt-product.php';
    require_once CUSTOM_TSHIRT_PLUGIN_PATH . 'includes/class-custom-tshirt-cart.php';
    require_once CUSTOM_TSHIRT_PLUGIN_PATH . 'includes/class-custom-tshirt-admin.php';
    require_once CUSTOM_TSHIRT_PLUGIN_PATH . 'includes/class-custom-tshirt-image-upload.php';
    
    // Inizializzazione classi
    new Custom_Tshirt_Product();
    new Custom_Tshirt_Cart();
    new Custom_Tshirt_Admin();
    new Custom_Tshirt_Image_Upload();
}

// Hook per il caricamento del plugin
add_action('plugins_loaded', 'custom_tshirt_load_files');

// Registra tipo di prodotto
function custom_tshirt_register_product_type() {
    // Assicurati che WooCommerce sia caricato
    if (!class_exists('WC_Product')) {
        return;
    }
    
    // Carica la classe del prodotto personalizzato
    require_once CUSTOM_TSHIRT_PLUGIN_PATH . 'includes/class-custom-tshirt-product-type.php';
}
add_action('init', 'custom_tshirt_register_product_type');

// Supporto per il tipo di prodotto personalizzato
add_filter('woocommerce_product_class', 'custom_tshirt_product_class', 10, 2);

function custom_tshirt_product_class($classname, $product_type) {
    if ($product_type === 'custom_tshirt') {
        $classname = 'WC_Product_Custom_Tshirt';
    }
    return $classname;
}

// Assicurati che il template del prodotto venga caricato
add_action('woocommerce_custom_tshirt_add_to_cart', 'custom_tshirt_designer_add_to_cart_template');

function custom_tshirt_designer_add_to_cart_template() {
    global $product;
    
    if ($product && $product->is_type('custom_tshirt')) {
        wc_get_template('single-product/add-to-cart/custom-tshirt.php', array(), '', CUSTOM_TSHIRT_PLUGIN_PATH . 'templates/');
    }
}

// Hook di debug per verificare il tipo di prodotto in pagina prodotto
add_action('woocommerce_before_single_product', 'custom_tshirt_debug_product');

function custom_tshirt_debug_product() {
    global $product;
    
    if (!is_a($product, 'WC_Product')) {
        return;
    }
    
    if ($product->is_type('custom_tshirt')) {
        // Assicurati che il template personalizzato sia caricato
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Prodotto di tipo custom_tshirt trovato!');
        }
    }
}

// Gestisci il template prodotto quando viene visualizzato
add_filter('woocommerce_product_add_to_cart_url', 'custom_tshirt_add_to_cart_url', 10, 2);
add_filter('woocommerce_product_add_to_cart_text', 'custom_tshirt_add_to_cart_text', 10, 2);
add_filter('woocommerce_product_supports', 'custom_tshirt_product_supports', 10, 3);

function custom_tshirt_add_to_cart_url($url, $product) {
    if ($product->is_type('custom_tshirt')) {
        return $product->get_permalink();
    }
    return $url;
}

function custom_tshirt_add_to_cart_text($text, $product) {
    if ($product->is_type('custom_tshirt')) {
        return __('Personalizza', 'custom-tshirt-designer');
    }
    return $text;
}

function custom_tshirt_product_supports($support, $feature, $product) {
    if ($product->is_type('custom_tshirt') && $feature === 'ajax_add_to_cart') {
        return false;
    }
    return $support;
}

// Attivazione plugin
register_activation_hook(__FILE__, 'custom_tshirt_activate');

function custom_tshirt_activate() {
    // Crea tabelle personalizzate se necessario
    global $wpdb;
    $table_name = $wpdb->prefix . 'e5rf_tshirt_designs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        image_path varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Crea la directory uploads se non esiste
    $upload_dir = wp_upload_dir();
    $custom_tshirt_dir = $upload_dir['basedir'] . '/custom-tshirts/';
    
    if (!file_exists($custom_tshirt_dir)) {
        wp_mkdir_p($custom_tshirt_dir);
    }
    
    // Aggiungi un file index.php vuoto per proteggere la directory
    if (!file_exists($custom_tshirt_dir . 'index.php')) {
        $index_file = fopen($custom_tshirt_dir . 'index.php', 'w');
        fwrite($index_file, '<?php // Silence is golden');
        fclose($index_file);
    }
    
    // Aggiungi un .htaccess per proteggere la directory
    if (!file_exists($custom_tshirt_dir . '.htaccess')) {
        $htaccess_content = "# Protezione directory\n";
        $htaccess_content .= "<IfModule mod_rewrite.c>\n";
        $htaccess_content .= "RewriteEngine On\n";
        $htaccess_content .= "RewriteCond %{HTTP_REFERER} !^https?://(www\.)?" . str_replace('www.', '', $_SERVER['HTTP_HOST']) . " [NC]\n";
        $htaccess_content .= "RewriteRule \.(jpg|jpeg|png|gif)$ - [NC,F,L]\n";
        $htaccess_content .= "</IfModule>\n";
        
        $htaccess_file = fopen($custom_tshirt_dir . '.htaccess', 'w');
        fwrite($htaccess_file, $htaccess_content);
        fclose($htaccess_file);
    }
}

// Disattivazione plugin
register_deactivation_hook(__FILE__, 'custom_tshirt_deactivate');

function custom_tshirt_deactivate() {
    // Non eliminiamo le tabelle o le immagini per non perdere dati
    // In una implementazione reale potremmo aggiungere un'opzione per rimuovere tutto
}

// Disinstallazione plugin
register_uninstall_hook(__FILE__, 'custom_tshirt_uninstall');

function custom_tshirt_uninstall() {
    // Questa funzione verrà eseguita solo quando il plugin viene disinstallato
    // Non viene eseguita quando il plugin viene disattivato
    
    // Rimuovi le opzioni salvate nel database
    delete_option('custom_tshirt_settings');
    
    // Rimuovi la tabella personalizzata
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}e5rf_tshirt_designs");
}