<?php
/**
 * Custom T-Shirt Add to Cart Template
 *
 * Override questo template creando un file 'add-to-cart/custom-tshirt.php' nella cartella 'woocommerce'
 * del tuo tema.
 */

defined('ABSPATH') || exit;

global $product;

// Assicurati che si tratti del tipo di prodotto corretto
if (!$product->is_type('custom_tshirt')) {
    return;
}

$colors = get_post_meta($product->get_id(), '_custom_tshirt_colors', true);
$colors_array = array();
if (!empty($colors)) {
    $colors_array = array_filter(array_map('trim', explode("\n", $colors)));
}

$sizes = get_post_meta($product->get_id(), '_custom_tshirt_sizes', true);
$sizes_array = array();
if (!empty($sizes)) {
    $sizes_array = array_filter(array_map('trim', explode("\n", $sizes)));
}

$visual_colors = get_post_meta($product->get_id(), '_custom_tshirt_visual_colors', true);
$size_guide = get_post_meta($product->get_id(), '_custom_tshirt_size_guide', true);
$custom_instructions = get_post_meta($product->get_id(), '_custom_tshirt_custom_instructions', true);

do_action('woocommerce_before_add_to_cart_form');
?>

<form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype="multipart/form-data">
    <div class="custom-tshirt-designer">
        <h3><?php _e('Personalizza la tua maglietta', 'custom-tshirt-designer'); ?></h3>
        
        <?php if (!empty($custom_instructions)) : ?>
            <div class="custom-tshirt-instructions">
                <?php echo wpautop($custom_instructions); ?>
            </div>
        <?php endif; ?>
        
        <div class="custom-tshirt-upload-area">
            <label><?php _e('Carica la tua immagine:', 'custom-tshirt-designer'); ?></label>
            <input type="file" name="custom_tshirt_image" id="custom_tshirt_image" accept="image/*" required>
            <p class="description"><?php _e('Carica l\'immagine che vuoi stampare sulla maglietta', 'custom-tshirt-designer'); ?></p>
            <div id="image_preview"></div>
        </div>
        
        <!-- Campo nascosto che conterrà il percorso dell'immagine caricata -->
        <input type="hidden" name="custom_tshirt_image_path" id="custom_tshirt_image_path">
    </div>
    
    <?php do_action('woocommerce_before_add_to_cart_button'); ?>
    
    <!-- Contenitore per tutte le varianti, inclusa la principale -->
    <div id="custom_tshirt_variants_container">
        <!-- Variante principale (sempre presente) -->
        <div class="custom-tshirt-variant main-variant">
            <div class="variant-header">
                <h4><?php _e('Variante #1', 'custom-tshirt-designer'); ?></h4>
            </div>
            <div class="variant-options">
                <div class="variant-type">
                    <label><?php _e('Tipo:', 'custom-tshirt-designer'); ?></label>
                    <select name="custom_tshirt_type" id="custom_tshirt_type" required>
                        <option value=""><?php _e('Seleziona tipo', 'custom-tshirt-designer'); ?></option>
                        <?php 
                        // Carica tutti i tipi di magliette
                        $tshirt_types = get_post_meta($product->get_id(), '_custom_tshirt_types', true);
                        $tshirt_types = !empty($tshirt_types) ? json_decode($tshirt_types, true) : array();
                        
                        // Se non ci sono tipi, usa i vecchi campi basic/deluxe per retrocompatibilità
                        if (empty($tshirt_types)) {
                            $basic_price = get_post_meta($product->get_id(), '_custom_tshirt_basic_price', true);
                            $deluxe_price = get_post_meta($product->get_id(), '_custom_tshirt_deluxe_price', true);
                            
                            if (!empty($basic_price)) {
                                echo '<option value="basic">' . __('Basic', 'custom-tshirt-designer') . ' (€' . esc_html($basic_price) . ')</option>';
                            }
                            
                            if (!empty($deluxe_price)) {
                                echo '<option value="deluxe">' . __('Deluxe', 'custom-tshirt-designer') . ' (€' . esc_html($deluxe_price) . ')</option>';
                            }
                        } else {
                            // Mostra tutti i tipi disponibili
                            foreach ($tshirt_types as $type) {
                                $type_id = isset($type['id']) ? $type['id'] : '';
                                $type_name = isset($type['name']) ? $type['name'] : '';
                                $type_price = isset($type['price']) ? $type['price'] : '';
                                $type_desc = isset($type['description']) ? $type['description'] : '';
                                
                                if (!empty($type_name) && !empty($type_price)) {
                                    echo '<option value="' . esc_attr($type_id) . '" data-price="' . esc_attr($type_price) . '" data-description="' . esc_attr($type_desc) . '">' . esc_html($type_name) . ' (€' . esc_html($type_price) . ')</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="variant-color">
                    <label><?php _e('Colore:', 'custom-tshirt-designer'); ?></label>
                    <?php if ($visual_colors === 'yes') : ?>
                        <div class="color-selector">
                            <?php 
                            function custom_tshirt_get_color_code($color_name) {
                                $color_map = array(
                                    'bianco' => '#FFFFFF',
                                    'nero' => '#000000',
                                    'rosso' => '#FF0000',
                                    'blu' => '#0000FF',
                                    'verde' => '#008000',
                                    'giallo' => '#FFFF00',
                                    'arancione' => '#FFA500',
                                    'viola' => '#800080',
                                    'rosa' => '#FFC0CB',
                                    'grigio' => '#808080',
                                    'marrone' => '#A52A2A',
                                    'azzurro' => '#ADD8E6',
                                    'turchese' => '#40E0D0',
                                    'argento' => '#C0C0C0',
                                    'oro' => '#FFD700',
                                    'beige' => '#F5F5DC'
                                );
                                
                                $color_name = strtolower(trim($color_name));
                                return isset($color_map[$color_name]) ? $color_map[$color_name] : '#CCCCCC';
                            }
                            
                            foreach ($colors_array as $color) : 
                                $color_code = custom_tshirt_get_color_code($color);
                            ?>
                                <div class="color-option" data-color="<?php echo esc_attr($color); ?>">
                                    <span class="color-swatch" style="background-color: <?php echo esc_attr($color_code); ?>"></span>
                                    <span class="color-name"><?php echo esc_html($color); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="custom_tshirt_color" id="custom_tshirt_color" required>
                    <?php else : ?>
                        <select name="custom_tshirt_color" id="custom_tshirt_color" required>
                            <option value=""><?php _e('Seleziona colore', 'custom-tshirt-designer'); ?></option>
                            <?php foreach ($colors_array as $color): ?>
                                <option value="<?php echo esc_attr($color); ?>"><?php echo esc_html($color); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                
                <div class="variant-size">
                    <label><?php _e('Taglia:', 'custom-tshirt-designer'); ?></label>
                    <select name="custom_tshirt_size" id="custom_tshirt_size" required>
                        <option value=""><?php _e('Seleziona taglia', 'custom-tshirt-designer'); ?></option>
                        <?php foreach ($sizes_array as $size): ?>
                            <option value="<?php echo esc_attr($size); ?>"><?php echo esc_html($size); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="variant-quantity">
                    <label><?php _e('Quantità:', 'custom-tshirt-designer'); ?></label>
                    <input type="number" name="quantity" value="1" min="1" step="1" class="input-text qty" required>
                </div>
            </div>
        </div>
        <!-- Qui verranno aggiunte dinamicamente le altre varianti -->
    </div>
    
    <div class="custom-tshirt-actions">
        <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html($product->single_add_to_cart_text()); ?></button>
        <button type="button" id="add_another_variant" class="button alt"><?php _e('Aggiungi altra variante', 'custom-tshirt-designer'); ?></button>
    </div>
    
    <?php if (!empty($size_guide)) : ?>
        <div class="size-guide-section">
            <a href="#" class="toggle-size-guide"><?php _e('Guida alle taglie', 'custom-tshirt-designer'); ?></a>
            <div class="size-guide-content" style="display: none;">
                <?php echo wpautop($size_guide); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php do_action('woocommerce_after_add_to_cart_button'); ?>
</form>

<?php do_action('woocommerce_after_add_to_cart_form'); ?>