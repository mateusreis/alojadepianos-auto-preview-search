<?php
/**
 * Plugin Name: A Loja de Pianos Auto Preview Search
 * Plugin URI: https://example.com/woocommerce-auto-preview-search
 * Description: Adds an autocomplete search for products with inline product previews including image, title, and price.
 * Version: 1.0.2
 * Author: Mateus Reis
 * Author URI: https://www.mateusreuis.com.br
 * Text Domain: wc-auto-preview-search
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.6.0
 */

// instalar plugin woocommerce-auto-preview-search
// configurar o shortcode
// [wc_auto_preview_search placeholder="BUSCAR PRODUTOS..." submit_text="BUSCAR"]
// dá pra navegar com o teclado e tem auto foco 


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_Auto_Preview_Search {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_wc_auto_preview_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_wc_auto_preview_search', array($this, 'ajax_search'));
        
        // Add shortcode
        add_shortcode('wc_auto_preview_search', array($this, 'search_shortcode'));
        
        // Add widget
        add_action('widgets_init', array($this, 'register_widget'));

        add_action('wp_enqueue_scripts',  array($this, 'my_plugin_enqueue_search_block_css'));
    }
    

    public function my_plugin_enqueue_search_block_css() {
        // Register the block style
        wp_register_style(
            'wp-block-search-inline-css',
            false, // Indicates it's an inline style
            array(),
            null // Version (null means WordPress will handle it)
        );
    
        // Enqueue the block style
        wp_enqueue_style('wp-block-search-inline-css');
    }

    
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

  
        
        wp_enqueue_style(
            'wc-auto-preview-search-styles',
            plugins_url('assets/css/style.css', __FILE__),
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'jquery-ui-autocomplete'
        );
        
        wp_enqueue_script(
            'wc-auto-preview-search-script',
            plugins_url('assets/js/script.js', __FILE__),
            array('jquery', 'jquery-ui-autocomplete'),
            '1.0.0',
            true
        );
        
        wp_localize_script(
            'wc-auto-preview-search-script',
            'wc_auto_preview_search',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_auto_preview_search_nonce')
            )
        );
    }
    
    /**
     * AJAX search handler
     */
    public function ajax_search() {
        check_ajax_referer('wc_auto_preview_search_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_GET['term']);
        $results = array();
        
        if (strlen($search_term) < 2) {
            wp_send_json($results);
            return;
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 4, // no máximo 4
            's' => $search_term,
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '=',
                )
            )
        );
        
        $products = new WP_Query($args);
        
        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                global $product;
                
                if (!is_a($product, 'WC_Product')) {
                    $product = wc_get_product(get_the_ID());
                }
                
                if (!$product) {
                    continue;
                }
                
                $image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'thumbnail');
                $image_url = $image ? $image[0] : wc_placeholder_img_src('thumbnail');
                
                $results[] = array(
                    'id' => get_the_ID(),
                    'label' => get_the_title(), // Used for matching in autocomplete
                    'value' => get_the_title(), // Value to fill in the search input
                    'title' => get_the_title(), // Full title for display
                    'url' => get_permalink(),
                    // 'price' => $product->get_price_html(),
                    'price' => "a partir de " . wc_price($product->get_price()),
                    'image' => $image_url,
                );
            }
        }
        
        wp_reset_postdata();
        wp_send_json($results);
    }
    
    /**
     * Shortcode for the search form
     */
    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Buscar produtos...', 'wc-auto-preview-search'),
            'submit_text' => __('BUSCAR', 'wc-auto-preview-search')
        ), $atts);
        
        ob_start();
        ?>

        <div class="wc-auto-preview-search">

            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="wp-block-search__button-outside wp-block-search__text-button wp-block-search">
            <div class="wp-block-search__outside-wrapper  ">    
                <label class="wp-block-search__label screen-reader-text" for="wp-block-search__input-5">Pesquisar</label>
                <div class="wp-block-search__inside-wrapper ">
                    <input class="wp-block-search__input wc-auto-preview-search-input" id="wc-auto-preview-search-input" placeholder="<?php echo $atts['placeholder']; ?>" value="" type="search" name="s" required="">
                    <input type="hidden" name="post_type" value="product">
                    <button aria-label="Search" class="wp-block-search__button wp-element-button wc-auto-preview-search-submit" type="submit"><?php echo $atts['submit_text']; ?></button>
                </div>
            </div>
            </form>
       
        </div>

        <?php
        return ob_get_clean();
    }
    
    /**
     * Register widget
     */
    public function register_widget() {
        register_widget('WC_Auto_Preview_Search_Widget');
    }
}

/**
 * Widget Class
 */
class WC_Auto_Preview_Search_Widget extends WP_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'wc_auto_preview_search_widget',
            __('WC Auto Preview Search', 'wc-auto-preview-search'),
            array(
                'description' => __('Display an autocomplete product search with preview results.', 'wc-auto-preview-search')
            )
        );
    }
    
    /**
     * Widget frontend
     */
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? apply_filters('widget_title', $instance['title']) : '';
        $placeholder = !empty($instance['placeholder']) ? $instance['placeholder'] : __('Search products...', 'wc-auto-preview-search');
        $submit_text = !empty($instance['submit_text']) ? $instance['submit_text'] : __('Search', 'wc-auto-preview-search');
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        echo do_shortcode('[wc_auto_preview_search placeholder="' . esc_attr($placeholder) . '" submit_text="' . esc_attr($submit_text) . '"]');
        
        echo $args['after_widget'];
    }
    
    /**
     * Widget backend
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $placeholder = !empty($instance['placeholder']) ? $instance['placeholder'] : __('Search products...', 'wc-auto-preview-search');
        $submit_text = !empty($instance['submit_text']) ? $instance['submit_text'] : __('Search', 'wc-auto-preview-search');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'wc-auto-preview-search'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('placeholder')); ?>"><?php esc_html_e('Placeholder:', 'wc-auto-preview-search'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('placeholder')); ?>" name="<?php echo esc_attr($this->get_field_name('placeholder')); ?>" type="text" value="<?php echo esc_attr($placeholder); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('submit_text')); ?>"><?php esc_html_e('Submit Button Text:', 'wc-auto-preview-search'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('submit_text')); ?>" name="<?php echo esc_attr($this->get_field_name('submit_text')); ?>" type="text" value="<?php echo esc_attr($submit_text); ?>">
        </p>
        <?php
    }
    
    /**
     * Sanitize widget form values as they are saved
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['placeholder'] = (!empty($new_instance['placeholder'])) ? sanitize_text_field($new_instance['placeholder']) : '';
        $instance['submit_text'] = (!empty($new_instance['submit_text'])) ? sanitize_text_field($new_instance['submit_text']) : '';
        
        return $instance;
    }
}

// Initialize plugin
function wc_auto_preview_search_init() {
    // Make sure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_auto_preview_search_woocommerce_notice');
        return;
    }
    
    // Create necessary files
    // wc_auto_preview_search_create_files();
    
    // Initialize the main plugin class
    new WC_Auto_Preview_Search();
}
add_action('plugins_loaded', 'wc_auto_preview_search_init');

/**
 * WooCommerce not active notice
 */
function wc_auto_preview_search_woocommerce_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce Auto Preview Search requires WooCommerce to be installed and active.', 'wc-auto-preview-search'); ?></p>
    </div>
    <?php
}