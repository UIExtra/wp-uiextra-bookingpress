<?php
/*
Plugin Name: BookingPress - UIExtra
Description: Extension for BookingPress that shows services filtered by categories and a panel in the backend.
Version: 1.0
Author: Jose Sandor Clavijo Aguilar
*/

if (!defined('ABSPATH')) {
    exit; // Evita el acceso directo
}

class BookingPress_UIExtra {
    private static $instance;

    public static function instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('bookingpress_uiextra', [$this, 'render_shortcode']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function render_shortcode($atts) {
        global $wpdb;
        $atts = shortcode_atts([
			'categories' => '',
			'link_url_base' => '',
			'link_target' => '',
			'button_service_text' => 'Book now',
			'button_service_show' => false,
			'currency_symbol' => '$',
			'currency_symbol_position' => 'before',
			'title_tag' => 'h3',
            'title_union_clear' => '',
			//'' => '',
		], $atts, 'bookingpress_uiextra');
        $category_ids = explode(',', $atts['categories']);
        $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
        
		/*		
		bookingpress_service_id
		bookingpress_category_id
		bookingpress_service_name
		bookingpress_service_price
		bookingpress_service_duration_val
		bookingpress_service_duration_unit
		bookingpress_service_description
		bookingpress_service_position
		bookingpress_service_expiration_date -> 2000-12-30
		bookingpress_servicedate_created
		*/
        $query = $wpdb->prepare(
            'SELECT s.*
            FROM '.$wpdb->prefix.'bookingpress_services s
            JOIN '.$wpdb->prefix.'bookingpress_servicesmeta sm ON s.bookingpress_service_id = sm.bookingpress_service_id
            WHERE s.bookingpress_category_id IN ('.$placeholders.') 
            AND sm.bookingpress_servicemeta_name = "show_service_on_site" 
            AND sm.bookingpress_servicemeta_value = "true"',
            $category_ids
        );

        $services = $wpdb->get_results($query);
        
        if (!$services) return '<p>No services are available.</p>';
        
		$link_target = '';
		if(!empty($atts['link_target'])){
			$link_target = ' target="'.$atts['link_target'].'"';	
		}
		
		$currency_symbol_before = '<span class="uie-bkp-service__price-symbol">'.$atts['currency_symbol'].'</span>';
		$currency_symbol_after = '';
		if($atts['currency_symbol_position'] == 'after'){
			$currency_symbol_before = '';
			$currency_symbol_after = '<span class="uie-bkp-service__price-symbol">'.$atts['currency_symbol'].'</span>';
		}
		
		$listDuration = ['m'=>'Mins','h'=>'Hours','d'=>'Days'];

        $name_aux = '';
		
        $i = 0;
        $output = '<div class="uie-bkp-service-wrap">';
        $output .= '<ul class="uie-bkp-service-container">';
        foreach ($services as $service) {
            $i++;
			$duration = $service->bookingpress_service_duration_val.' <span class="uie-bkp-service__duration-unit">'.$listDuration[$service->bookingpress_service_duration_unit].'</span>';
            
            $name_clear = $service->bookingpress_service_name;

            if(!empty($atts['title_union_clear'])){
                $aux_clear = explode(',', $atts['title_union_clear']);
                $name_clear = trim(str_replace($aux_clear, '', $name_clear));
            }

            if($name_aux != $name_clear) {
                $name_aux = $name_clear;
                if($i>1){
                    $output .= '</div>';
			        $output .= '</li>';
                }
                $output .= '<li class="uie-bkp-service">';
                $output .= '<'.$atts['title_tag'].' class="uie-bkp-service__title">'.$name_clear.'</'.$atts['title_tag'].'>';
                $output .= '<div class="uie-bkp-service__desc">'.$service->bookingpress_service_description.'</div>';
                $output .= '<div class="uie-bkp-service__content">';
            }
            $output .= '<div class="uie-bkp-service__detail">';
			$output .= '<div class="uie-bkp-service__price-duration">';
            $output .= '<span class="uie-bkp-service__price">'.$currency_symbol_before.$service->bookingpress_service_price.$currency_symbol_after.'</span> <span class="uie-bkp-service__separator">·</span> <span class="uie-bkp-service__duration">'.$duration.'</span></div>';
			if($atts['button_service_show'] == 'true'){
				$atts['link_url_base'] = rtrim($atts['link_url_base'], '/') . '/';
				$output .= ' <a class="uie-button" href="'.$atts['link_url_base'].'?s_id='.$service->bookingpress_service_id.'"'.$link_target.'>'.$atts['button_service_text'].'</a>';
			}
            $output .= '</div>';
        }
        $output .= '</div>';
		$output .= '</li>';

        $output .= '</ul></div>';
        
        return $output;
    }

    public function add_admin_menu() {
        add_menu_page('BookingPress UIExtra', 'BookingPress UIExtra', 'manage_options', 'bookingpress_uiextra', [$this, 'admin_services_page'], 'dashicons-admin-generic');
        add_submenu_page('bookingpress_uiextra', 'Shortcodes', 'Shortcodes', 'manage_options', 'bookingpress_uiextra_shortcodes', [$this, 'admin_shortcodes_page']);
    }

    public function enqueue_scripts() {
		$v = '2.503.23a';
		$v = '.'.date('His');//Cache avoidance
		
		wp_enqueue_style( 'uie-bkp-style', plugins_url('/assets/css/uie-bkp-style.css', __FILE__), '', $v);		
    }

    public function enqueue_admin_scripts() {
		wp_enqueue_script('jquery');
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], null, true);
        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css');
    }

    public function admin_services_page() {
        global $wpdb;
        $services = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'bookingpress_services');
        ?>
        <div class="wrap">
            <h1>List of services</h1>
            <table id="services_table" class="display">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Description</th>
                        <th>Date of Creation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo esc_html($service->bookingpress_service_id); ?></td>
                            <td><?php echo esc_html($service->bookingpress_service_name); ?></td>
                            <td><?php echo esc_html($service->bookingpress_service_price); ?></td>
                            <td><?php echo esc_html($service->bookingpress_service_duration_val . ' ' . $service->bookingpress_service_duration_unit); ?></td>
                            <td><?php echo esc_html($service->bookingpress_service_description); ?></td>
                            <td><?php echo esc_html($service->bookingpress_servicedate_created); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#services_table').DataTable();
            });
        </script>
        <?php
    }

    public function admin_shortcodes_page() {
        ?>
        <div class="wrap">
            <h1>Use of Shortcodes</h1>
            <p><strong>[bookingpress_uiextra categories="1,2,3"]</strong> - Displays the services of the specified categories.</p>
        </div>
        <?php
    }
}

BookingPress_UIExtra::instance();
