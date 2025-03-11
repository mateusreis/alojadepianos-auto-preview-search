<?php
/**
 * Plugin Name: WooCommerce Auto Preview Search
 * Plugin URI: https://example.com/woocommerce-auto-preview-search
 * Description: Adds an autocomplete search for products with inline product previews including image, title, and price.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wc-auto-preview-search
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.6.0
 */

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
            'posts_per_page' => 10,
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
                    'price' => $product->get_price_html(),
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
            'placeholder' => __('Search products...', 'wc-auto-preview-search'),
            'submit_text' => __('Search', 'wc-auto-preview-search')
        ), $atts);
        
        ob_start();
        ?>
        <div class="wc-auto-preview-search-container">
            <form class="wc-auto-preview-search-form" action="<?php echo esc_url(home_url('/')); ?>" method="get">
                <input type="text" name="s" class="wc-auto-preview-search-input" placeholder="<?php echo esc_attr($atts['placeholder']); ?>" autocomplete="off" />
                <input type="hidden" name="post_type" value="product" />
                <button type="submit" class="wc-auto-preview-search-submit"><?php echo esc_html($atts['submit_text']); ?></button>
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

// Create CSS and JS files
function wc_auto_preview_search_create_files() {
    // Create plugin directories if they don't exist
    if (!file_exists(plugin_dir_path(__FILE__) . 'assets/css')) {
        wp_mkdir_p(plugin_dir_path(__FILE__) . 'assets/css');
    }
    
    if (!file_exists(plugin_dir_path(__FILE__) . 'assets/js')) {
        wp_mkdir_p(plugin_dir_path(__FILE__) . 'assets/js');
    }
    
    // Create CSS file if it doesn't exist
    $css_file = plugin_dir_path(__FILE__) . 'assets/css/style.css';
    if (!file_exists($css_file)) {
        $css_content = <<<CSS
.wc-auto-preview-search-container {
    position: relative;
    max-width: 100%;
    margin-bottom: 20px;
}

.wc-auto-preview-search-form {
    display: flex;
    position: relative;
}

.wc-auto-preview-search-input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 4px 0 0 4px;
    font-size: 14px;
    line-height: 1.5;
}

.wc-auto-preview-search-submit {
    padding: 10px 15px;
    background: #77a464;
    color: white;
    border: none;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
    font-size: 14px;
    line-height: 1.5;
}

.wc-auto-preview-search-submit:hover {
    background: #669c50;
}

/* Custom UI Autocomplete Styling */
.ui-autocomplete {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1000;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-height: 400px;
    overflow-y: auto;
    width: 100% !important;
    padding: 0;
}

.ui-autocomplete .ui-menu-item {
    padding: 0;
    border-bottom: 1px solid #f0f0f0;
    margin: 0;
}

.ui-autocomplete .ui-menu-item:last-child {
    border-bottom: none;
}

.ui-autocomplete .ui-menu-item-wrapper {
    padding: 0;
    cursor: pointer;
}

.wc-product-preview {
    display: flex;
    align-items: center;
    padding: 10px;
    transition: background-color 0.2s;
}

.wc-product-preview:hover {
    background-color: #f9f9f9;
}

.wc-product-preview-image {
    flex: 0 0 60px;
    margin-right: 15px;
}

.wc-product-preview-image img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 3px;
    display: block;
}

.wc-product-preview-content {
    flex: 1;
    min-width: 0; /* Helps with text truncation */
}

.wc-product-preview-title {
    font-weight: bold;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #333;
}

.wc-product-preview-price {
    color: #77a464;
    font-weight: bold;
}

.ui-helper-hidden-accessible {
    display: none;
}

.ui-state-active .wc-product-preview,
.ui-state-focus .wc-product-preview {
    background-color: #f9f9f9;
}

@media (max-width: 767px) {
    .wc-auto-preview-search-form {
        flex-direction: column;
    }
    
    .wc-auto-preview-search-input {
        border-radius: 4px;
        margin-bottom: 10px;
    }
    
    .wc-auto-preview-search-submit {
        border-radius: 4px;
    }
    
    .wc-product-preview-image {
        flex: 0 0 50px;
    }
    
    .wc-product-preview-image img {
        width: 50px;
        height: 50px;
    }
}
CSS;
        file_put_contents($css_file, $css_content);
    }
    
    // Create JS file if it doesn't exist
    $js_file = plugin_dir_path(__FILE__) . 'assets/js/script.js';
    if (!file_exists($js_file)) {
        $js_content = <<<JS
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize autocomplete
        $('.wc-auto-preview-search-input').autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: wc_auto_preview_search.ajax_url,
                    dataType: 'json',
                    data: {
                        action: 'wc_auto_preview_search',
                        term: request.term,
                        nonce: wc_auto_preview_search.nonce
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },
            minLength: 2,
            position: { my: 'left top+5', at: 'left bottom' },
            select: function(event, ui) {
                // Redirect to product page on select
                window.location.href = ui.item.url;
                return false;
            }
        }).data('ui-autocomplete')._renderItem = function(ul, item) {
            // Custom rendering of items with product preview
            var html = '<div class="wc-product-preview">';
            html += '<div class="wc-product-preview-image">';
            html += '<img src="' + item.image + '" alt="' + item.title + '">';
            html += '</div>';
            html += '<div class="wc-product-preview-content">';
            html += '<div class="wc-product-preview-title">' + item.title + '</div>';
            html += '<div class="wc-product-preview-price">' + item.price + '</div>';
            html += '</div>';
            html += '</div>';
            
            return $('<li>')
                .data('ui-autocomplete-item', item)
                .append(html)
                .appendTo(ul);
        };
    });
})(jQuery);
JS;
        file_put_contents($js_file, $js_content);
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
    wc_auto_preview_search_create_files();
    
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