<?php
/**
 * Custom T-Shirt Image Upload Class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Custom_Tshirt_Image_Upload {
    
    public function __construct() {
        // AJAX per il caricamento immagini
        add_action('wp_ajax_custom_tshirt_upload_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_nopriv_custom_tshirt_upload_image', array($this, 'ajax_upload_image'));
    }
    
    /**
     * Gestione AJAX per upload immagini
     */
    public function ajax_upload_image() {
        // Debug - Registra i dati ricevuti
        error_log('Custom T-Shirt: Upload richiesto');
        
        // Verifica security nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'custom_tshirt_nonce')) {
            error_log('Custom T-Shirt: Verifica nonce fallita');
            wp_send_json_error(array('message' => __('Errore di sicurezza. Ricarica la pagina e riprova.', 'custom-tshirt-designer')));
            return;
        }
        
        // Verifica se il file è stato caricato
        if (!isset($_FILES['file'])) {
            error_log('Custom T-Shirt: Nessun file caricato');
            wp_send_json_error(array('message' => __('Nessun file caricato', 'custom-tshirt-designer')));
            return;
        }
        
        // Ottieni product_id se fornito
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        error_log('Custom T-Shirt: Product ID: ' . $product_id);
        
        // Configurazione upload
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/custom-tshirts/';
        $upload_url = $upload_dir['baseurl'] . '/custom-tshirts/';
        
        // Crea la directory se non esiste
        if (!file_exists($upload_path)) {
            if (!wp_mkdir_p($upload_path)) {
                error_log('Custom T-Shirt: Errore nella creazione della directory: ' . $upload_path);
                wp_send_json_error(array('message' => __('Impossibile creare la directory per i caricamenti. Contatta l\'amministratore.', 'custom-tshirt-designer')));
                return;
            }
            
            // Crea un file .htaccess per proteggere la directory
            $htaccess_file = $upload_path . '.htaccess';
            if (!file_exists($htaccess_file)) {
                $htaccess_content = "# Protezione directory\n";
                $htaccess_content .= "<IfModule mod_rewrite.c>\n";
                $htaccess_content .= "RewriteEngine On\n";
                $htaccess_content .= "RewriteCond %{HTTP_REFERER} !^https?://(www\.)?" . str_replace( 'www.', '', $_SERVER['HTTP_HOST'] ) . " [NC]\n";
                $htaccess_content .= "RewriteRule \.(jpg|jpeg|png|gif)$ - [NC,F,L]\n";
                $htaccess_content .= "</IfModule>\n";
                
                file_put_contents($htaccess_file, $htaccess_content);
            }
        }
        
        // Controlla dimensione massima
        $max_size = 5; // Default 5MB
        if ($product_id) {
            $max_upload_size = get_post_meta($product_id, '_custom_tshirt_max_upload_size', true);
            if (!empty($max_upload_size)) {
                $max_size = absint($max_upload_size);
            }
        }
        
        if ($_FILES['file']['size'] > $max_size * 1024 * 1024) {
            error_log('Custom T-Shirt: File troppo grande: ' . ($_FILES['file']['size'] / (1024 * 1024)) . 'MB');
            wp_send_json_error(array('message' => sprintf(__('Il file è troppo grande. La dimensione massima consentita è %d MB.', 'custom-tshirt-designer'), $max_size)));
            return;
        }
        
        // Controlla il tipo di file
        $file_type = wp_check_filetype(basename($_FILES['file']['name']));
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        
        error_log('Custom T-Shirt: Tipo file: ' . $file_type['type']);
        
        // Filtro tipi in base alle impostazioni del prodotto
        if ($product_id) {
            $allowed_formats = get_post_meta($product_id, '_custom_tshirt_allowed_formats', true);
            
            if ($allowed_formats === 'jpg_png') {
                $allowed_types = array('image/jpeg', 'image/png');
            } elseif ($allowed_formats === 'jpg_only') {
                $allowed_types = array('image/jpeg');
            } elseif ($allowed_formats === 'png_only') {
                $allowed_types = array('image/png');
            }
        }
        
        if (!in_array($file_type['type'], $allowed_types)) {
            error_log('Custom T-Shirt: Tipo file non permesso');
            wp_send_json_error(array('message' => __('Tipo di file non supportato. I formati consentiti sono JPG, PNG e GIF.', 'custom-tshirt-designer')));
            return;
        }
        
        // Genera un nome file unico
        $filename = uniqid() . '.' . $file_type['ext'];
        $file_path = $upload_path . $filename;
        
        // Sposta il file caricato
        if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            // Salva nel database
            global $wpdb;
            $table_name = $wpdb->prefix . 'e5rf_tshirt_designs';
            
            $user_id = get_current_user_id();
            $image_path = 'custom-tshirts/' . $filename;
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'image_path' => $image_path
                )
            );
            
            if ($result === false) {
                error_log('Custom T-Shirt: Errore inserimento DB: ' . $wpdb->last_error);
            }
            
            // Ritorna il percorso dell'immagine
            error_log('Custom T-Shirt: Upload completato con successo');
            wp_send_json_success(array(
                'url' => $upload_url . $filename,
                'path' => $image_path
            ));
        } else {
            error_log('Custom T-Shirt: Errore nello spostamento del file');
            wp_send_json_error(array('message' => __('Errore nel caricamento del file. Controlla i permessi della directory.', 'custom-tshirt-designer')));
        }
    }
}