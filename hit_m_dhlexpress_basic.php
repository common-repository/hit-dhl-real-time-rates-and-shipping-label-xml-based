<?php
/**
 * Plugin Name: HIT DHL Real-time rates and shipping label
 * Plugin URI: https://hittechmarket.com/
 * Description: Realtime Shipping Rates, Shipping label, commercial invoice included.
 * Version: 1.0.1
 * Author: HITTECH MARKET
 * Author URI: https://hittechmarket.com/
 * Developer: hittechmarket
 * Developer URI: https://hittechmarket.com/
 * Text Domain: hit_m_dhlexpress
 * Domain Path: /i18n/languages/
 *
 * WC requires at least: 2.6
 * WC tested up to: 5.8
 *
 *
 * @package WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WC_PLUGIN_FILE.
if ( ! defined( 'HIT_M_DHLEXPRESS_PLUGIN_FILE' ) ) {
	define( 'HIT_M_DHLEXPRESS_PLUGIN_FILE', __FILE__ );
}

function hit_m_woo_dhl_express_plugin_activation( $plugin ) {
    if( $plugin == plugin_basename( __FILE__ ) ) {
        $setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
    	// Don't forget to exit() because wp_redirect doesn't exit automatically
    	exit( wp_redirect( admin_url( 'admin.php?page=' . $setting_value  . '&tab=shipping&section=hit_m_dhlexpress' ) ) );
    }
}
add_action( 'activated_plugin', 'hit_m_woo_dhl_express_plugin_activation' );

// Include the main WooCommerce class.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	if( !class_exists('hit_m_dhlexpress_parent') ){
		Class hit_m_dhlexpress_parent
		{
			private $errror = '';
			public function __construct() {
				add_action( 'woocommerce_shipping_init', array($this,'hit_m_dhlexpress_init') );
				add_filter( 'woocommerce_shipping_methods', array($this,'hit_m_dhlexpress_method') );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'hit_m_dhlexpress_plugin_action_links' ) );
				add_action( 'add_meta_boxes', array($this, 'create_dhl_m_shipping_meta_box' ));
				add_action( 'save_post', array($this, 'hit_create_dhl_m_shipping'), 10, 1 );
				// add_action( 'save_post', array($this, 'hit_create_dhl_return_shipping'), 10, 1 );
				// add_filter( 'bulk_actions-edit-shop_order', array($this, 'hit_bulk_order_menu'), 10, 1 );
				add_filter( 'handle_bulk_actions-edit-shop_order', array($this, 'hit_bulk_create_order'), 10, 3 );
				add_action( 'admin_notices', array($this, 'shipo_bulk_label_action_admin_notice' ) );
				add_filter( 'woocommerce_product_data_tabs', array($this,'hit_product_data_tab') );
				add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_options' ));
				add_filter( 'woocommerce_product_data_panels', array($this,'hit_product_option_view') );
				add_action( 'admin_menu', array($this, 'hit_dhl_menu_page' ));
				add_filter( 'manage_edit-shop_order_columns', array($this, 'hit_m_wc_new_order_column') );
				add_action( 'manage_shop_order_posts_custom_column', array( $this, 'show_buttons_to_downlaod_shipping_label') );
				add_action('admin_print_styles', array($this, 'hits_admin_scripts'));
				
				$general_settings = get_option('hit_m_dhlexpress_main_settings');
				$general_settings = empty($general_settings) ? array() : $general_settings;

				if(isset($general_settings['hit_m_dhlexpress_v_enable']) && $general_settings['hit_m_dhlexpress_v_enable'] == 'yes' ){
					add_action( 'woocommerce_product_options_shipping', array($this,'hit_choose_vendor_address' ));
					add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_meta' ));

					// Edit User Hooks
					add_action( 'edit_user_profile', array($this,'hit_define_dhl_credentails') );
					add_action( 'edit_user_profile_update', array($this, 'save_user_fields' ));

				}
			
			}
			public function hits_admin_scripts() {
		        global $wp_scripts;
		        wp_enqueue_script('wc-enhanced-select');
		        wp_enqueue_script('chosen');
		        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');

		    }
			
			function hit_m_wc_new_order_column( $columns ) {
				$columns['hit_dhlexpress'] = 'DHL Express';
				return $columns;
			}
			
			function show_buttons_to_downlaod_shipping_label( $column ) {
				global $post;
				
				if ( 'hit_dhlexpress' === $column ) {
			
					$order    = wc_get_order( $post->ID );
					$json_data = get_option('hit_m_dhl_values_'.$post->ID);
					
					if(!empty($json_data)){
						$array_data = json_decode( $json_data, true );
						// echo '<pre>';print_r($array_data);die();
						if(isset($array_data[0])){
							foreach ($array_data as $key => $value) {
								echo '<a href="'.esc_url($value['label']).'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-printer" style="vertical-align:sub;"></span></a> ';
								echo ' <a href="'.esc_url($value['invoice']).'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-pdf" style="vertical-align:sub;"></span></a><br/>';
							}	
						}else{
							echo '<a href="'.esc_url($array_data['label']).'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-printer" style="vertical-align:sub;"></span></a> ';
							echo ' <a href="'.esc_url($array_data['invoice']).'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-pdf" style="vertical-align:sub;"></span></a>';
						}
					}else{
						echo '-';
					}
				}
			}
			
			function hit_dhl_menu_page() {
				
				add_submenu_page( 'options-general.php', 'HIT - Manual DHL Express Config', 'HIT - Manual DHL Express Config', 'manage_options', 'hit-m-dhl-express-configuration', array($this, 'my_admin_page_contents') ); 

			}
			
			function my_admin_page_contents(){
				include_once('controllors/views/hit_m_dhlexpress_settings_view.php');
			}

			public function hit_product_data_tab( $tabs) {

				$tabs['hits_product_options'] = array(
					'label'		=> __( 'HIT - DHL Manual Options', 'hit_m_dhlexpress' ),
					'target'	=> 'hit_dhl_product_options',
					// 'class'		=> array( 'show_if_simple', 'show_if_variable' ),
				);
			
				return $tabs;
			
			}

			public function hit_save_product_options( $post_id ){
				if( isset($_POST['hit_m_dhl_cc']) ){
					$cc = sanitize_text_field($_POST['hit_m_dhl_cc']);
					update_post_meta( $post_id, 'hit_m_dhl_cc', (string) esc_html( $cc ) );
					// print_r($post_id);die();
				}
			}

			public function hit_product_option_view(){
				global $woocommerce, $post;
				$hits_dhl_saved_cc = get_post_meta( $post->ID, 'hit_m_dhl_cc', true);
				?>
				<div id='hit_dhl_product_options' class='panel woocommerce_options_panel'>
					<div class='options_group'>
						<p class="form-field">
							<label for="hit_m_dhl_cc"><?php _e( 'Enter Commodity code', 'hit_m_dhlexpress' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Enter commodity code for product (20 charcters max).','hit_m_dhlexpress') ?>"></span>
							<input type='text' id='hit_m_dhl_cc' name='hit_m_dhl_cc' maxlength="20" <?php echo (!empty($hits_dhl_saved_cc) ? 'value="'.esc_html($hits_dhl_saved_cc).'"' : '');?> style="width: 30%;">
						</p>
					</div>
				</div>
				<?php
			}

			public function hit_bulk_order_menu( $actions ) {
				// echo "<pre>";print_r($actions);die();
				$actions['create_label_shipo'] = __( 'Create Labels - HITShipo', 'hit_m_dhlexpress' );
				return $actions;
			}

			public function hit_bulk_create_order($redirect_to, $action, $order_ids){
				
			}

			function shipo_bulk_label_action_admin_notice() {
				if(isset($_GET['success_lbl']) && isset($_GET['failed_lbl'])){
					printf( '<div id="message" class="updated fade"><p>
						Generated labels: '. esc_html($_GET['success_lbl']) .' Failed Label: '. esc_html($_GET['failed_lbl']).' </p></div>');
				}

			}

			public function save_user_fields($user_id){
				if(isset($_POST['hit_m_dhlexpress_country'])){
					$general_settings['hit_m_dhlexpress_site_id'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_site_id']) ? $_POST['hit_m_dhlexpress_site_id'] : '');
					$general_settings['hit_m_dhlexpress_site_pwd'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_site_pwd']) ? $_POST['hit_m_dhlexpress_site_pwd'] : '');
					$general_settings['hit_m_dhlexpress_acc_no'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_acc_no']) ? $_POST['hit_m_dhlexpress_acc_no'] : '');
					$general_settings['hit_m_dhlexpress_import_no'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_import_no']) ? $_POST['hit_m_dhlexpress_import_no'] : '');
					$general_settings['hit_m_dhlexpress_shipper_name'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_shipper_name']) ? $_POST['hit_m_dhlexpress_shipper_name'] : '');
					$general_settings['hit_m_dhlexpress_company'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_company']) ? $_POST['hit_m_dhlexpress_company'] : '');
					$general_settings['hit_m_dhlexpress_mob_num'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_mob_num']) ? $_POST['hit_m_dhlexpress_mob_num'] : '');
					$general_settings['hit_m_dhlexpress_email'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_email']) ? $_POST['hit_m_dhlexpress_email'] : '');
					$general_settings['hit_m_dhlexpress_address1'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_address1']) ? $_POST['hit_m_dhlexpress_address1'] : '');
					$general_settings['hit_m_dhlexpress_address2'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_address2']) ? $_POST['hit_m_dhlexpress_address2'] : '');
					$general_settings['hit_m_dhlexpress_city'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_city']) ? $_POST['hit_m_dhlexpress_city'] : '');
					$general_settings['hit_m_dhlexpress_state'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_state']) ? $_POST['hit_m_dhlexpress_state'] : '');
					$general_settings['hit_m_dhlexpress_zip'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_zip']) ? $_POST['hit_m_dhlexpress_zip'] : '');
					$general_settings['hit_m_dhlexpress_country'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_country']) ? $_POST['hit_m_dhlexpress_country'] : '');
					$general_settings['hit_m_dhlexpress_gstin'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_gstin']) ? $_POST['hit_m_dhlexpress_gstin'] : '');
					$general_settings['hit_m_dhlexpress_con_rate'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_con_rate']) ? $_POST['hit_m_dhlexpress_con_rate'] : '');
					$general_settings['hit_m_dhlexpress_def_dom'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_def_dom']) ? $_POST['hit_m_dhlexpress_def_dom'] : '');

					$general_settings['hit_m_dhlexpress_def_inter'] = sanitize_text_field(isset($_POST['hit_m_dhlexpress_def_inter']) ? $_POST['hit_m_dhlexpress_def_inter'] : '');

					update_post_meta($user_id,'hit_m_dhlexpress_vendor_settings',$general_settings);
				}

			}

			public function hit_define_dhl_credentails( $user ){
				global $dhl_core;
				$main_settings = get_option('hit_m_dhlexpress_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				$allow = false;
				
				if(!isset($main_settings['hit_m_dhlexpress_v_roles'])){
					return;
				}else{
					foreach ($user->roles as $value) {
						if(in_array($value, $main_settings['hit_m_dhlexpress_v_roles'])){
							$allow = true;
						}
					}
				}
				
				if(!$allow){
					return;
				}

				$general_settings = get_post_meta($user->ID,'hit_m_dhlexpress_vendor_settings',true);
				$general_settings = empty($general_settings) ? array() : $general_settings;
				$countires =  array(
									'AF' => 'Afghanistan',
									'AL' => 'Albania',
									'DZ' => 'Algeria',
									'AS' => 'American Samoa',
									'AD' => 'Andorra',
									'AO' => 'Angola',
									'AI' => 'Anguilla',
									'AG' => 'Antigua and Barbuda',
									'AR' => 'Argentina',
									'AM' => 'Armenia',
									'AW' => 'Aruba',
									'AU' => 'Australia',
									'AT' => 'Austria',
									'AZ' => 'Azerbaijan',
									'BS' => 'Bahamas',
									'BH' => 'Bahrain',
									'BD' => 'Bangladesh',
									'BB' => 'Barbados',
									'BY' => 'Belarus',
									'BE' => 'Belgium',
									'BZ' => 'Belize',
									'BJ' => 'Benin',
									'BM' => 'Bermuda',
									'BT' => 'Bhutan',
									'BO' => 'Bolivia',
									'BA' => 'Bosnia and Herzegovina',
									'BW' => 'Botswana',
									'BR' => 'Brazil',
									'VG' => 'British Virgin Islands',
									'BN' => 'Brunei',
									'BG' => 'Bulgaria',
									'BF' => 'Burkina Faso',
									'BI' => 'Burundi',
									'KH' => 'Cambodia',
									'CM' => 'Cameroon',
									'CA' => 'Canada',
									'CV' => 'Cape Verde',
									'KY' => 'Cayman Islands',
									'CF' => 'Central African Republic',
									'TD' => 'Chad',
									'CL' => 'Chile',
									'CN' => 'China',
									'CO' => 'Colombia',
									'KM' => 'Comoros',
									'CK' => 'Cook Islands',
									'CR' => 'Costa Rica',
									'HR' => 'Croatia',
									'CU' => 'Cuba',
									'CY' => 'Cyprus',
									'CZ' => 'Czech Republic',
									'DK' => 'Denmark',
									'DJ' => 'Djibouti',
									'DM' => 'Dominica',
									'DO' => 'Dominican Republic',
									'TL' => 'East Timor',
									'EC' => 'Ecuador',
									'EG' => 'Egypt',
									'SV' => 'El Salvador',
									'GQ' => 'Equatorial Guinea',
									'ER' => 'Eritrea',
									'EE' => 'Estonia',
									'ET' => 'Ethiopia',
									'FK' => 'Falkland Islands',
									'FO' => 'Faroe Islands',
									'FJ' => 'Fiji',
									'FI' => 'Finland',
									'FR' => 'France',
									'GF' => 'French Guiana',
									'PF' => 'French Polynesia',
									'GA' => 'Gabon',
									'GM' => 'Gambia',
									'GE' => 'Georgia',
									'DE' => 'Germany',
									'GH' => 'Ghana',
									'GI' => 'Gibraltar',
									'GR' => 'Greece',
									'GL' => 'Greenland',
									'GD' => 'Grenada',
									'GP' => 'Guadeloupe',
									'GU' => 'Guam',
									'GT' => 'Guatemala',
									'GG' => 'Guernsey',
									'GN' => 'Guinea',
									'GW' => 'Guinea-Bissau',
									'GY' => 'Guyana',
									'HT' => 'Haiti',
									'HN' => 'Honduras',
									'HK' => 'Hong Kong',
									'HU' => 'Hungary',
									'IS' => 'Iceland',
									'IN' => 'India',
									'ID' => 'Indonesia',
									'IR' => 'Iran',
									'IQ' => 'Iraq',
									'IE' => 'Ireland',
									'IL' => 'Israel',
									'IT' => 'Italy',
									'CI' => 'Ivory Coast',
									'JM' => 'Jamaica',
									'JP' => 'Japan',
									'JE' => 'Jersey',
									'JO' => 'Jordan',
									'KZ' => 'Kazakhstan',
									'KE' => 'Kenya',
									'KI' => 'Kiribati',
									'KW' => 'Kuwait',
									'KG' => 'Kyrgyzstan',
									'LA' => 'Laos',
									'LV' => 'Latvia',
									'LB' => 'Lebanon',
									'LS' => 'Lesotho',
									'LR' => 'Liberia',
									'LY' => 'Libya',
									'LI' => 'Liechtenstein',
									'LT' => 'Lithuania',
									'LU' => 'Luxembourg',
									'MO' => 'Macao',
									'MK' => 'Macedonia',
									'MG' => 'Madagascar',
									'MW' => 'Malawi',
									'MY' => 'Malaysia',
									'MV' => 'Maldives',
									'ML' => 'Mali',
									'MT' => 'Malta',
									'MH' => 'Marshall Islands',
									'MQ' => 'Martinique',
									'MR' => 'Mauritania',
									'MU' => 'Mauritius',
									'YT' => 'Mayotte',
									'MX' => 'Mexico',
									'FM' => 'Micronesia',
									'MD' => 'Moldova',
									'MC' => 'Monaco',
									'MN' => 'Mongolia',
									'ME' => 'Montenegro',
									'MS' => 'Montserrat',
									'MA' => 'Morocco',
									'MZ' => 'Mozambique',
									'MM' => 'Myanmar',
									'NA' => 'Namibia',
									'NR' => 'Nauru',
									'NP' => 'Nepal',
									'NL' => 'Netherlands',
									'NC' => 'New Caledonia',
									'NZ' => 'New Zealand',
									'NI' => 'Nicaragua',
									'NE' => 'Niger',
									'NG' => 'Nigeria',
									'NU' => 'Niue',
									'KP' => 'North Korea',
									'MP' => 'Northern Mariana Islands',
									'NO' => 'Norway',
									'OM' => 'Oman',
									'PK' => 'Pakistan',
									'PW' => 'Palau',
									'PA' => 'Panama',
									'PG' => 'Papua New Guinea',
									'PY' => 'Paraguay',
									'PE' => 'Peru',
									'PH' => 'Philippines',
									'PL' => 'Poland',
									'PT' => 'Portugal',
									'PR' => 'Puerto Rico',
									'QA' => 'Qatar',
									'CG' => 'Republic of the Congo',
									'RE' => 'Reunion',
									'RO' => 'Romania',
									'RU' => 'Russia',
									'RW' => 'Rwanda',
									'SH' => 'Saint Helena',
									'KN' => 'Saint Kitts and Nevis',
									'LC' => 'Saint Lucia',
									'VC' => 'Saint Vincent and the Grenadines',
									'WS' => 'Samoa',
									'SM' => 'San Marino',
									'ST' => 'Sao Tome and Principe',
									'SA' => 'Saudi Arabia',
									'SN' => 'Senegal',
									'RS' => 'Serbia',
									'SC' => 'Seychelles',
									'SL' => 'Sierra Leone',
									'SG' => 'Singapore',
									'SK' => 'Slovakia',
									'SI' => 'Slovenia',
									'SB' => 'Solomon Islands',
									'SO' => 'Somalia',
									'ZA' => 'South Africa',
									'KR' => 'South Korea',
									'SS' => 'South Sudan',
									'ES' => 'Spain',
									'LK' => 'Sri Lanka',
									'SD' => 'Sudan',
									'SR' => 'Suriname',
									'SZ' => 'Swaziland',
									'SE' => 'Sweden',
									'CH' => 'Switzerland',
									'SY' => 'Syria',
									'TW' => 'Taiwan',
									'TJ' => 'Tajikistan',
									'TZ' => 'Tanzania',
									'TH' => 'Thailand',
									'TG' => 'Togo',
									'TO' => 'Tonga',
									'TT' => 'Trinidad and Tobago',
									'TN' => 'Tunisia',
									'TR' => 'Turkey',
									'TC' => 'Turks and Caicos Islands',
									'TV' => 'Tuvalu',
									'VI' => 'U.S. Virgin Islands',
									'UG' => 'Uganda',
									'UA' => 'Ukraine',
									'AE' => 'United Arab Emirates',
									'GB' => 'United Kingdom',
									'US' => 'United States',
									'UY' => 'Uruguay',
									'UZ' => 'Uzbekistan',
									'VU' => 'Vanuatu',
									'VE' => 'Venezuela',
									'VN' => 'Vietnam',
									'YE' => 'Yemen',
									'ZM' => 'Zambia',
									'ZW' => 'Zimbabwe',
								);
				 $_dhl_carriers = array(
					//"Public carrier name" => "technical name",
					'1'                    => 'DOMESTIC EXPRESS 12:00',
					'2'                    => 'B2C',
					'3'                    => 'B2C',
					'4'                    => 'JETLINE',
					'5'                    => 'SPRINTLINE',
					'7'                    => 'EXPRESS EASY',
					'8'                    => 'EXPRESS EASY',
					'9'                    => 'EUROPACK',
					'B'                    => 'BREAKBULK EXPRESS',
					'C'                    => 'MEDICAL EXPRESS',
					'D'                    => 'EXPRESS WORLDWIDE',
					'E'                    => 'EXPRESS 9:00',
					'F'                    => 'FREIGHT WORLDWIDE',
					'G'                    => 'DOMESTIC ECONOMY SELECT',
					'H'                    => 'ECONOMY SELECT',
					'I'                    => 'DOMESTIC EXPRESS 9:00',
					'J'                    => 'JUMBO BOX',
					'K'                    => 'EXPRESS 9:00',
					'L'                    => 'EXPRESS 10:30',
					'M'                    => 'EXPRESS 10:30',
					'N'                    => 'DOMESTIC EXPRESS',
					'O'                    => 'DOMESTIC EXPRESS 10:30',
					'P'                    => 'EXPRESS WORLDWIDE',
					'Q'                    => 'MEDICAL EXPRESS',
					'R'                    => 'GLOBALMAIL BUSINESS',
					'S'                    => 'SAME DAY',
					'T'                    => 'EXPRESS 12:00',
					'U'                    => 'EXPRESS WORLDWIDE',
					'V'                    => 'EUROPACK',
					'W'                    => 'ECONOMY SELECT',
					'X'                    => 'EXPRESS ENVELOPE',
					'Y'                    => 'EXPRESS 12:00'	
				);			

				 echo '<hr><h3 class="heading">HIT DHL Express Manual - Vendor config</h3>';
				    ?>
				    
				    <table class="form-table">
						<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Integration Team will give this details to you.','hit_m_dhlexpress') ?>"></span>	<?php _e('DHL XML API Site ID','hit_m_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hit_m_dhlexpress') ?> </p>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_site_id" value="<?php echo (isset($general_settings['hit_m_dhlexpress_site_id'])) ? esc_html($general_settings['hit_m_dhlexpress_site_id']) : ''; ?>">
						</td>

					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Integration Team will give this details to you.','hit_m_dhlexpress') ?>"></span>	<?php _e('DHL XML API Password','hit_m_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hit_m_dhlexpress') ?> </p>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_site_pwd" value="<?php echo (isset($general_settings['hit_m_dhlexpress_site_pwd'])) ? esc_html($general_settings['hit_m_dhlexpress_site_pwd']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Integration Team will give this details to you.','hit_m_dhlexpress') ?>"></span>	<?php _e('DHL Account Number','hit_m_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hit_m_dhlexpress') ?> </p>
						</td>
						<td>
							
							<input type="text" name="hit_m_dhlexpress_acc_no" value="<?php echo (isset($general_settings['hit_m_dhlexpress_acc_no'])) ? esc_html($general_settings['hit_m_dhlexpress_acc_no']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('This is for proceed with return labels.','hit_m_dhlexpress') ?>"></span>	<?php _e('DHL Import Account Number','hit_m_dhlexpress') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hit_m_dhlexpress') ?> </p>
						</td>
						<td>
							
							<input type="text" name="hit_m_dhlexpress_import_no" value="<?php echo (isset($general_settings['hit_m_dhlexpress_import_no'])) ? esc_html($general_settings['hit_m_dhlexpress_import_no']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipping Person Name','hit_m_dhlexpress') ?>"></span>	<?php _e('Shipper Name','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_shipper_name" value="<?php echo (isset($general_settings['hit_m_dhlexpress_shipper_name'])) ? esc_html($general_settings['hit_m_dhlexpress_shipper_name']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Company Name.','hit_m_dhlexpress') ?>"></span>	<?php _e('Company Name','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_company" value="<?php echo (isset($general_settings['hit_m_dhlexpress_company'])) ? esc_html($general_settings['hit_m_dhlexpress_company']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Mobile / Contact Number.','hit_m_dhlexpress') ?>"></span>	<?php _e('Contact Number','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_mob_num" value="<?php echo (isset($general_settings['hit_m_dhlexpress_mob_num'])) ? esc_html($general_settings['hit_m_dhlexpress_mob_num']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Email Address of the Shipper.','hit_m_dhlexpress') ?>"></span>	<?php _e('Email Address','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_email" value="<?php echo (isset($general_settings['hit_m_dhlexpress_email'])) ? esc_html($general_settings['hit_m_dhlexpress_email']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 1 of the Shipper from Address.','hit_m_dhlexpress') ?>"></span>	<?php _e('Address Line 1','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_address1" value="<?php echo (isset($general_settings['hit_m_dhlexpress_address1'])) ? esc_html($general_settings['hit_m_dhlexpress_address1']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 2 of the Shipper from Address.','hit_m_dhlexpress') ?>"></span>	<?php _e('Address Line 2','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_address2" value="<?php echo (isset($general_settings['hit_m_dhlexpress_address2'])) ? esc_html($general_settings['hit_m_dhlexpress_address2']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%;padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('City of the Shipper from address.','hit_m_dhlexpress') ?>"></span>	<?php _e('City','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_city" value="<?php echo (isset($general_settings['hit_m_dhlexpress_city'])) ? esc_html($general_settings['hit_m_dhlexpress_city']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('State of the Shipper from address.','hit_m_dhlexpress') ?>"></span>	<?php _e('State (Two Digit String)','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_state" value="<?php echo (isset($general_settings['hit_m_dhlexpress_state'])) ? esc_html($general_settings['hit_m_dhlexpress_state']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Postal/Zip Code.','hit_m_dhlexpress') ?>"></span>	<?php _e('Postal/Zip Code','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_zip" value="<?php echo (isset($general_settings['hit_m_dhlexpress_zip'])) ? esc_html($general_settings['hit_m_dhlexpress_zip']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Country of the Shipper from Address.','hit_m_dhlexpress') ?>"></span>	<?php _e('Country','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<select name="hit_m_dhlexpress_country" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($countires as $key => $value)
								{

									if(isset($general_settings['hit_m_dhlexpress_country']) && ($general_settings['hit_m_dhlexpress_country'] == $key))
									{
										echo "<option value=".esc_html($key)." selected='true'>".esc_html($value)." [". esc_html($dhl_core[$key]['currency']) ."]</option>";
									}
									else
									{
										echo "<option value=".esc_html($key).">".esc_html($value)." [". esc_html($dhl_core[$key]['currency']) ."]</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('GSTIN/VAT No.','hit_m_dhlexpress') ?>"></span>	<?php _e('GSTIN/VAT No','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_gstin" value="<?php echo (isset($general_settings['hit_m_dhlexpress_gstin'])) ? esc_html($general_settings['hit_m_dhlexpress_gstin']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Conversion Rate from Site Currency to DHL Currency.','hit_m_dhlexpress') ?>"></span>	<?php _e('Conversion Rate from Site Currency to DHL Currency ( Ignore if auto conversion is Enabled )','hit_m_dhlexpress') ?></h4>
						</td>
						<td>
							<input type="text" name="hit_m_dhlexpress_con_rate" value="<?php echo (isset($general_settings['hit_m_dhlexpress_con_rate'])) ? esc_html($general_settings['hit_m_dhlexpress_con_rate']) : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default Domestic Express Shipping.','hit_m_dhlexpress') ?>"></span>	<?php _e('Default Domestic Service','hit_m_dhlexpress') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','hit_m_dhlexpress') ?></p>
						</td>
						<td>
							<select name="hit_m_dhlexpress_def_dom" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_dhl_carriers as $key => $value)
								{
									if(isset($general_settings['hit_m_dhlexpress_def_dom']) && ($general_settings['hit_m_dhlexpress_def_dom'] == $key))
									{
										echo "<option value=".esc_html($key)." selected='true'>[".esc_html($key)."] ".esc_html($value)."</option>";
									}
									else
									{
										echo "<option value=".esc_html($key).">[".esc_html($key)."] ".esc_html($value)."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default International Shipping.','hit_m_dhlexpress') ?>"></span>	<?php _e('Default International Service','hit_m_dhlexpress') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','hit_m_dhlexpress') ?></p>
						</td>
						<td>
							<select name="hit_m_dhlexpress_def_inter" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_dhl_carriers as $key => $value)
								{
									if(isset($general_settings['hit_m_dhlexpress_def_inter']) && ($general_settings['hit_m_dhlexpress_def_inter'] == $key))
									{
										echo "<option value=".esc_html($key)." selected='true'>[".esc_html($key)."] ".esc_html($value)."</option>";
									}
									else
									{
										echo "<option value=".esc_html($key).">[".esc_html($key)."] ".esc_html($value)."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
				    </table>
				    <hr>
				    <?php
			}
			public function hit_save_product_meta( $post_id ){
				if(isset( $_POST['dhl_express_shipment'])){
					$dhl_express_shipment = sanitize_text_field($_POST['dhl_express_shipment']);
					if( !empty( $dhl_express_shipment ) )
					update_post_meta( $post_id, 'dhl_m_express_address', (string) esc_html( $dhl_express_shipment ) );	
				}
							
			}
			public function hit_choose_vendor_address(){
				global $woocommerce, $post;
				$hit_multi_vendor = get_option('hit_multi_vendor');
				$hit_multi_vendor = empty($hit_multi_vendor) ? array() : $hit_multi_vendor;
				$selected_addr = get_post_meta( $post->ID, 'dhl_m_express_address', true);

				$main_settings = get_option('hit_m_dhlexpress_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				if(!isset($main_settings['hit_m_dhlexpress_v_roles']) || empty($main_settings['hit_m_dhlexpress_v_roles'])){
					return;
				}
				$v_users = get_users( [ 'role__in' => $main_settings['hit_m_dhlexpress_v_roles'] ] );
				
				?>
				<div class="options_group">
				<p class="form-field dhl_express_shipment">
					<label for="dhl_express_shipment"><?php _e( 'DHL Express Manual Account', 'woocommerce' ); ?></label>
					<select id="dhl_express_shipment" style="width:240px;" name="dhl_express_shipment" class="wc-enhanced-select" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>">
						<option value="default" >Default Account</option>
						<?php
							if ( $v_users ) {
								foreach ( $v_users as $value ) {
									echo '<option value="' .  esc_html($value->data->ID)  . '" '.($selected_addr == $value->data->ID ? 'selected="true"' : '').'>' . esc_html($value->data->display_name) . '</option>';
								}
							}
						?>
					</select>
					</p>
				</div>
				<?php
			}

			public function hit_m_dhlexpress_init()
			{
				include_once("controllors/hit_m_dhlexpress_init.php");
			}
			
			public function hit_m_dhlexpress_method( $methods )
			{
				if (is_admin() && !is_ajax() || apply_filters('hit_m_shipping_method_enabled', true)) {
					$methods['hit_m_dhlexpress'] = 'hit_m_dhlexpress'; 
				}

				return $methods;
			}
			
			public function hit_m_dhlexpress_plugin_action_links($links)
			{
				$setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
				$plugin_links = array(
					'<a href="' . admin_url( 'admin.php?page=' . $setting_value  . '&tab=shipping&section=hit_m_dhlexpress' ) . '" style="color:green;">' . __( 'Configure', 'hit_m_dhlexpress' ) . '</a>',
					'<a href="https://app.hitshipo.com/support" target="_blank" >' . __('Support', 'hit_m_dhlexpress') . '</a>'
					);
				return array_merge( $plugin_links, $links );
			}
			public function create_dhl_m_shipping_meta_box() {
	       		add_meta_box( 'hit_create_dhl_m_shipping', __('HIT - DHL Manual Shipping Label','hit_m_dhlexpress'), array($this, 'create_dhl_shipping_label_genetation'), 'shop_order', 'side', 'core' );
	       		// add_meta_box( 'hit_create_dhl_return_shipping', __('DHL Return Label','hit_m_dhlexpress'), array($this, 'create_dhl_return_label_genetation'), 'shop_order', 'side', 'core' );
		    }
		    public function create_dhl_shipping_label_genetation($post){
		    	echo '<center><b style="color:red">Purchase the premium version to get label generation feature</b></center><br>';
		    	echo '<a target="_blank" href="https://hittechmarket.com/downloads/dhl-express-shipping-for-wordpress/" class="button button-primary" style="background:#FFCC00; color: #D40511;border-color: #FFCC00;box-shadow: 0px 1px 0px #FFCC00;text-shadow: 0px 1px 0px #D40511;margin-top:3px; width:100%; text-align:center;">Upgrade to Premium</a>';
		    }

		    // Save the data of the Meta field
			public function hit_create_dhl_m_shipping( $order_id ) {

			}

		    // Save the data of the Meta field
			public function hit_create_dhl_return_shipping( $order_id ) {
			
		    }

		    public function hit_get_dhl_packages($package, $general_settings, $orderCurrency, $chk = true)
			{
				switch ($general_settings['hit_m_dhlexpress_packing_type']) {
					case 'box':
						return $this->box_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'weight_based':
						return $this->weight_based_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'per_item':
					default:
						return $this->per_item_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
				}
			}
			private function per_item_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				$to_ship = array();
				$group_id = 1;

				// Get weight of order
				foreach ($package as $item_id => $values) {
					$product_data = $values;
					$get_prod = wc_get_product($values['product_id']);

					$group = array();
					$insurance_array = array(
						'Amount' => round($product_data['price']),
						'Currency' => $orderCurrency
					);

					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$dhl_per_item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					} else {
						$dhl_per_item_weight = 0.001;
					}

					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => $dhl_per_item_weight,
							'Units' => (isset($general_settings['hit_m_dhlexpress_weight_unit']) && $general_settings['hit_m_dhlexpress_weight_unit'] == 'KG_CM') ? 'KG' : 'LBS'
						),
						'packed_products' => array($values)
					);

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['length']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['length'])) {

						$group['Dimensions'] = array(
							'Length' => max(1, round($product_data['length'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3)),
							'Units' => (isset($general_settings['hit_m_dhlexpress_weight_unit']) && $general_settings['hit_m_dhlexpress_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
						);
					} elseif (isset($parent_prod_data['width']) && isset($parent_prod_data['height']) && isset($parent_prod_data['length']) && !empty($parent_prod_data['width']) && !empty($parent_prod_data['height']) && !empty($parent_prod_data['length'])) {
						$group['Dimensions'] = array(
							'Length' => max(1, round($parent_prod_data['length'], 3)),
							'Width' => max(1, round($parent_prod_data['width'], 3)),
							'Height' => max(1, round($parent_prod_data['height'], 3)),
							'Units' => (isset($general_settings['hit_m_dhlexpress_weight_unit']) && $general_settings['hit_m_dhlexpress_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
						);
					}

					$group['packtype'] = 'BOX';

					$group['InsuredValue'] = $insurance_array;

					$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];

					for ($i = 0; $i < $chk_qty; $i++)
						$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			private function weight_based_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				// echo '<pre>';
				// print_r($package);
				// die();
				if (!class_exists('WeightPack')) {
					include_once 'controllors/classes/weight_pack/class-hit-weight-packing.php';
				}
				$max_weight = isset($general_settings['hit_m_dhlexpress_max_weight']) && $general_settings['hit_m_dhlexpress_max_weight'] != ''  ? $general_settings['hit_m_dhlexpress_max_weight'] : 10;
				$weight_pack = new WeightPack('pack_ascending');
				$weight_pack->set_max_weight($max_weight);

				$package_total_weight = 0;
				$insured_value = 0;

				$ctr = 0;
				foreach ($package as $item_id => $values) {
					$ctr++;
					$product_data = $values;


					if (!isset($product_data['weight']) || empty($product_data['weight'])) {
						$product_data['weight'] = 0.001;
					}

					$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];

					$weight_pack->add_item($product_data['weight'], $values, $chk_qty);
				}

				$pack   =   $weight_pack->pack_items();
				$errors =   $pack->get_errors();
				if (!empty($errors)) {
					//do nothing
					return;
				} else {
					$boxes    =   $pack->get_packed_boxes();
					$unpacked_items =   $pack->get_unpacked_items();

					$insured_value        =   0;

					$packages      =   array_merge($boxes, $unpacked_items); // merge items if unpacked are allowed
					$package_count  =   sizeof($packages);
					// get all items to pass if item info in box is not distinguished
					$packable_items =   $weight_pack->get_packable_items();
					$all_items    =   array();
					if (is_array($packable_items)) {
						foreach ($packable_items as $packable_item) {
							$all_items[]    =   $packable_item['data'];
						}
					}
					//pre($packable_items);
					$order_total = '';

					$to_ship  = array();
					$group_id = 1;
					foreach ($packages as $package) { //pre($package);
						$packed_products = array();
						if (($package_count  ==  1) && isset($order_total)) {
							$insured_value  =  (isset($product_data['product_price']) ? $product_data['product_price'] : $product_data['price']) * (isset($values['product_quantity']) ? $values['product_quantity'] : $values['quantity']);
						} else {
							$insured_value  =   0;
							if (!empty($package['items'])) {
								foreach ($package['items'] as $item) {

									$insured_value        =   $insured_value; //+ $item->price;
								}
							} else {
								if (isset($order_total) && $package_count) {
									$insured_value  =   $order_total / $package_count;
								}
							}
						}
						$packed_products    =   isset($package['items']) ? $package['items'] : $all_items;
						// Creating package request
						$package_total_weight   = $package['weight'];

						$insurance_array = array(
							'Amount' => $insured_value,
							'Currency' => $orderCurrency
						);

						$group = array(
							'GroupNumber' => $group_id,
							'GroupPackageCount' => 1,
							'Weight' => array(
								'Value' => round($package_total_weight, 3),
								'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'KG' : 'LBS'
							),
							'packed_products' => $packed_products,
						);
						$group['InsuredValue'] = $insurance_array;
						$group['packtype'] = 'BOX';

						$to_ship[] = $group;
						$group_id++;
					}
				}
				return $to_ship;
			}
			private function box_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				if (!class_exists('HIT_Boxpack')) {
					include_once 'controllors/classes/hit-box-packing.php';
				}
				$boxpack = new HIT_Boxpack();
				$boxes = isset($general_settings['hit_m_dhlexpress_boxes']) ? $general_settings['hit_m_dhlexpress_boxes'] : array();
				if (empty($boxes)) {
					return false;
				}
				// $boxes = unserialize($boxes);
				// Define boxes
				foreach ($boxes as $key => $box) {
					if (!$box['enabled']) {
						continue;
					}
					$box['pack_type'] = !empty($box['pack_type']) ? $box['pack_type'] : 'BOX';

					$newbox = $boxpack->add_box($box['length'], $box['width'], $box['height'], $box['box_weight'], $box['pack_type']);

					if (isset($box['id'])) {
						$newbox->set_id(current(explode(':', $box['id'])));
					}

					if ($box['max_weight']) {
						$newbox->set_max_weight($box['max_weight']);
					}

					if ($box['pack_type']) {
						$newbox->set_packtype($box['pack_type']);
					}
				}

				// Add items
				foreach ($package as $item_id => $values) {

					$product_data = $values;

					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					}

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['depth']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['depth'])) {
						$item_dimension = array(
							'Length' => max(1, round($product_data['depth'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3))
						);
					}

					if (isset($item_weight) && isset($item_dimension)) {

						// $dimensions = array($values['depth'], $values['height'], $values['width']);
						$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];
						for ($i = 0; $i < $chk_qty; $i++) {
							$boxpack->add_item($item_dimension['Width'], $item_dimension['Height'], $item_dimension['Length'], $item_weight, round($product_data['price']), array(
								'data' => $values
							));
						}
					} else {
						//    $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'wf-shipping-dhl'), $item_id), 'error');
						return;
					}
				}

				// Pack it
				$boxpack->pack();
				$packages = $boxpack->get_packages();
				$to_ship = array();
				$group_id = 1;
				foreach ($packages as $package) {
					if ($package->unpacked === true) {
						//$this->debug('Unpacked Item');
					} else {
						//$this->debug('Packed ' . $package->id);
					}

					$dimensions = array($package->length, $package->width, $package->height);

					sort($dimensions);
					$insurance_array = array(
						'Amount' => round($package->value),
						'Currency' => $orderCurrency
					);


					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => round($package->weight, 3),
							'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'KG' : 'LBS'
						),
						'Dimensions' => array(
							'Length' => max(1, round($dimensions[2], 3)),
							'Width' => max(1, round($dimensions[1], 3)),
							'Height' => max(1, round($dimensions[0], 3)),
							'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'CM' : 'IN'
						),
						'InsuredValue' => $insurance_array,
						'packed_products' => array(),
						'package_id' => $package->id,
						'packtype' => 'BOX'
					);

					if (!empty($package->packed) && is_array($package->packed)) {
						foreach ($package->packed as $packed) {
							$group['packed_products'][] = $packed->get_meta('data');
						}
					}

					if (!isset($package->packed)) {
						foreach ($package->unpacked as $unpacked) {
							$group['packed_products'][] = $unpacked->get_meta('data');
						}
					}

					$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			public function hit_m_dhlexpress_is_eu_country ($countrycode, $destinationcode) {
				$eu_countrycodes = array(
					'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 
					'ES', 'FI', 'FR', 'GB', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
					'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
					'HR', 'GR'

				);
				return(in_array($countrycode, $eu_countrycodes) && in_array($destinationcode, $eu_countrycodes));
			}
			function hit_get_country_name($country_code){
			  $countires =  array(
			      'AF' => 'Afghanistan',
			      'AX' => 'Aland Islands',
			      'AL' => 'Albania',
			      'DZ' => 'Algeria',
			      'AS' => 'American Samoa',
			      'AD' => 'Andorra',
			      'AO' => 'Angola',
			      'AI' => 'Anguilla',
			      'AQ' => 'Antarctica',
			      'AG' => 'Antigua and Barbuda',
			      'AR' => 'Argentina',
			      'AM' => 'Armenia',
			      'AW' => 'Aruba',
			      'AU' => 'Australia',
			      'AT' => 'Austria',
			      'AZ' => 'Azerbaijan',
			      'BS' => 'Bahamas',
			      'BH' => 'Bahrain',
			      'BD' => 'Bangladesh',
			      'BB' => 'Barbados',
			      'BY' => 'Belarus',
			      'BE' => 'Belgium',
			      'BZ' => 'Belize',
			      'BJ' => 'Benin',
			      'BM' => 'Bermuda',
			      'BT' => 'Bhutan',
			      'BO' => 'Bolivia',
			      'BQ' => 'Bonaire, Saint Eustatius and Saba',
			      'BA' => 'Bosnia and Herzegovina',
			      'BW' => 'Botswana',
			      'BV' => 'Bouvet Island',
			      'BR' => 'Brazil',
			      'IO' => 'British Indian Ocean Territory',
			      'VG' => 'British Virgin Islands',
			      'BN' => 'Brunei',
			      'BG' => 'Bulgaria',
			      'BF' => 'Burkina Faso',
			      'BI' => 'Burundi',
			      'KH' => 'Cambodia',
			      'CM' => 'Cameroon',
			      'CA' => 'Canada',
			      'CV' => 'Cape Verde',
			      'KY' => 'Cayman Islands',
			      'CF' => 'Central African Republic',
			      'TD' => 'Chad',
			      'CL' => 'Chile',
			      'CN' => 'China',
			      'CX' => 'Christmas Island',
			      'CC' => 'Cocos Islands',
			      'CO' => 'Colombia',
			      'KM' => 'Comoros',
			      'CK' => 'Cook Islands',
			      'CR' => 'Costa Rica',
			      'HR' => 'Croatia',
			      'CU' => 'Cuba',
			      'CW' => 'Curacao',
			      'CY' => 'Cyprus',
			      'CZ' => 'Czech Republic',
			      'CD' => 'Democratic Republic of the Congo',
			      'DK' => 'Denmark',
			      'DJ' => 'Djibouti',
			      'DM' => 'Dominica',
			      'DO' => 'Dominican Republic',
			      'TL' => 'East Timor',
			      'EC' => 'Ecuador',
			      'EG' => 'Egypt',
			      'SV' => 'El Salvador',
			      'GQ' => 'Equatorial Guinea',
			      'ER' => 'Eritrea',
			      'EE' => 'Estonia',
			      'ET' => 'Ethiopia',
			      'FK' => 'Falkland Islands',
			      'FO' => 'Faroe Islands',
			      'FJ' => 'Fiji',
			      'FI' => 'Finland',
			      'FR' => 'France',
			      'GF' => 'French Guiana',
			      'PF' => 'French Polynesia',
			      'TF' => 'French Southern Territories',
			      'GA' => 'Gabon',
			      'GM' => 'Gambia',
			      'GE' => 'Georgia',
			      'DE' => 'Germany',
			      'GH' => 'Ghana',
			      'GI' => 'Gibraltar',
			      'GR' => 'Greece',
			      'GL' => 'Greenland',
			      'GD' => 'Grenada',
			      'GP' => 'Guadeloupe',
			      'GU' => 'Guam',
			      'GT' => 'Guatemala',
			      'GG' => 'Guernsey',
			      'GN' => 'Guinea',
			      'GW' => 'Guinea-Bissau',
			      'GY' => 'Guyana',
			      'HT' => 'Haiti',
			      'HM' => 'Heard Island and McDonald Islands',
			      'HN' => 'Honduras',
			      'HK' => 'Hong Kong',
			      'HU' => 'Hungary',
			      'IS' => 'Iceland',
			      'IN' => 'India',
			      'ID' => 'Indonesia',
			      'IR' => 'Iran',
			      'IQ' => 'Iraq',
			      'IE' => 'Ireland',
			      'IM' => 'Isle of Man',
			      'IL' => 'Israel',
			      'IT' => 'Italy',
			      'CI' => 'Ivory Coast',
			      'JM' => 'Jamaica',
			      'JP' => 'Japan',
			      'JE' => 'Jersey',
			      'JO' => 'Jordan',
			      'KZ' => 'Kazakhstan',
			      'KE' => 'Kenya',
			      'KI' => 'Kiribati',
			      'XK' => 'Kosovo',
			      'KW' => 'Kuwait',
			      'KG' => 'Kyrgyzstan',
			      'LA' => 'Laos',
			      'LV' => 'Latvia',
			      'LB' => 'Lebanon',
			      'LS' => 'Lesotho',
			      'LR' => 'Liberia',
			      'LY' => 'Libya',
			      'LI' => 'Liechtenstein',
			      'LT' => 'Lithuania',
			      'LU' => 'Luxembourg',
			      'MO' => 'Macao',
			      'MK' => 'Macedonia',
			      'MG' => 'Madagascar',
			      'MW' => 'Malawi',
			      'MY' => 'Malaysia',
			      'MV' => 'Maldives',
			      'ML' => 'Mali',
			      'MT' => 'Malta',
			      'MH' => 'Marshall Islands',
			      'MQ' => 'Martinique',
			      'MR' => 'Mauritania',
			      'MU' => 'Mauritius',
			      'YT' => 'Mayotte',
			      'MX' => 'Mexico',
			      'FM' => 'Micronesia',
			      'MD' => 'Moldova',
			      'MC' => 'Monaco',
			      'MN' => 'Mongolia',
			      'ME' => 'Montenegro',
			      'MS' => 'Montserrat',
			      'MA' => 'Morocco',
			      'MZ' => 'Mozambique',
			      'MM' => 'Myanmar',
			      'NA' => 'Namibia',
			      'NR' => 'Nauru',
			      'NP' => 'Nepal',
			      'NL' => 'Netherlands',
			      'NC' => 'New Caledonia',
			      'NZ' => 'New Zealand',
			      'NI' => 'Nicaragua',
			      'NE' => 'Niger',
			      'NG' => 'Nigeria',
			      'NU' => 'Niue',
			      'NF' => 'Norfolk Island',
			      'KP' => 'North Korea',
			      'MP' => 'Northern Mariana Islands',
			      'NO' => 'Norway',
			      'OM' => 'Oman',
			      'PK' => 'Pakistan',
			      'PW' => 'Palau',
			      'PS' => 'Palestinian Territory',
			      'PA' => 'Panama',
			      'PG' => 'Papua New Guinea',
			      'PY' => 'Paraguay',
			      'PE' => 'Peru',
			      'PH' => 'Philippines',
			      'PN' => 'Pitcairn',
			      'PL' => 'Poland',
			      'PT' => 'Portugal',
			      'PR' => 'Puerto Rico',
			      'QA' => 'Qatar',
			      'CG' => 'Republic of the Congo',
			      'RE' => 'Reunion',
			      'RO' => 'Romania',
			      'RU' => 'Russia',
			      'RW' => 'Rwanda',
			      'BL' => 'Saint Barthelemy',
			      'SH' => 'Saint Helena',
			      'KN' => 'Saint Kitts and Nevis',
			      'LC' => 'Saint Lucia',
			      'MF' => 'Saint Martin',
			      'PM' => 'Saint Pierre and Miquelon',
			      'VC' => 'Saint Vincent and the Grenadines',
			      'WS' => 'Samoa',
			      'SM' => 'San Marino',
			      'ST' => 'Sao Tome and Principe',
			      'SA' => 'Saudi Arabia',
			      'SN' => 'Senegal',
			      'RS' => 'Serbia',
			      'SC' => 'Seychelles',
			      'SL' => 'Sierra Leone',
			      'SG' => 'Singapore',
			      'SX' => 'Sint Maarten',
			      'SK' => 'Slovakia',
			      'SI' => 'Slovenia',
			      'SB' => 'Solomon Islands',
			      'SO' => 'Somalia',
			      'ZA' => 'South Africa',
			      'GS' => 'South Georgia and the South Sandwich Islands',
			      'KR' => 'South Korea',
			      'SS' => 'South Sudan',
			      'ES' => 'Spain',
			      'LK' => 'Sri Lanka',
			      'SD' => 'Sudan',
			      'SR' => 'Suriname',
			      'SJ' => 'Svalbard and Jan Mayen',
			      'SZ' => 'Swaziland',
			      'SE' => 'Sweden',
			      'CH' => 'Switzerland',
			      'SY' => 'Syria',
			      'TW' => 'Taiwan',
			      'TJ' => 'Tajikistan',
			      'TZ' => 'Tanzania',
			      'TH' => 'Thailand',
			      'TG' => 'Togo',
			      'TK' => 'Tokelau',
			      'TO' => 'Tonga',
			      'TT' => 'Trinidad and Tobago',
			      'TN' => 'Tunisia',
			      'TR' => 'Turkey',
			      'TM' => 'Turkmenistan',
			      'TC' => 'Turks and Caicos Islands',
			      'TV' => 'Tuvalu',
			      'VI' => 'U.S. Virgin Islands',
			      'UG' => 'Uganda',
			      'UA' => 'Ukraine',
			      'AE' => 'United Arab Emirates',
			      'GB' => 'United Kingdom',
			      'US' => 'United States',
			      'UM' => 'United States Minor Outlying Islands',
			      'UY' => 'Uruguay',
			      'UZ' => 'Uzbekistan',
			      'VU' => 'Vanuatu',
			      'VA' => 'Vatican',
			      'VE' => 'Venezuela',
			      'VN' => 'Vietnam',
			      'WF' => 'Wallis and Futuna',
			      'EH' => 'Western Sahara',
			      'YE' => 'Yemen',
			      'ZM' => 'Zambia',
			      'ZW' => 'Zimbabwe',
			    );
			  return $countires[$country_code];
			}
			function hit_get_local_product_code( $global_product_code, $origin_country='', $destination_country='' ){

			  $countrywise_local_product_code = array( 
			    'SA' => 'global_product_code',
			    'ZA' => 'global_product_code',
			    'CH' => 'global_product_code'
			  );

			  if( array_key_exists($origin_country, $countrywise_local_product_code) ){
			    return ($countrywise_local_product_code[$origin_country] == 'global_product_code') ? $global_product_code : $countrywise_local_product_code[$origin_country];

			  }
			  return $global_product_code;
			}

		}

		$dhl_core = array();
		$dhl_core['AD'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['AE'] = array('region' => 'AP', 'currency' =>'AED', 'weight' => 'KG_CM');
		$dhl_core['AF'] = array('region' => 'AP', 'currency' =>'AFN', 'weight' => 'KG_CM');
		$dhl_core['AG'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['AI'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['AL'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['AM'] = array('region' => 'AP', 'currency' =>'AMD', 'weight' => 'KG_CM');
		$dhl_core['AN'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'KG_CM');
		$dhl_core['AO'] = array('region' => 'AP', 'currency' =>'AOA', 'weight' => 'KG_CM');
		$dhl_core['AR'] = array('region' => 'AM', 'currency' =>'ARS', 'weight' => 'KG_CM');
		$dhl_core['AS'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['AT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['AU'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['AW'] = array('region' => 'AM', 'currency' =>'AWG', 'weight' => 'LB_IN');
		$dhl_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
		$dhl_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
		$dhl_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['BA'] = array('region' => 'AP', 'currency' =>'BAM', 'weight' => 'KG_CM');
		$dhl_core['BB'] = array('region' => 'AM', 'currency' =>'BBD', 'weight' => 'LB_IN');
		$dhl_core['BD'] = array('region' => 'AP', 'currency' =>'BDT', 'weight' => 'KG_CM');
		$dhl_core['BE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['BF'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['BG'] = array('region' => 'EU', 'currency' =>'BGN', 'weight' => 'KG_CM');
		$dhl_core['BH'] = array('region' => 'AP', 'currency' =>'BHD', 'weight' => 'KG_CM');
		$dhl_core['BI'] = array('region' => 'AP', 'currency' =>'BIF', 'weight' => 'KG_CM');
		$dhl_core['BJ'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['BM'] = array('region' => 'AM', 'currency' =>'BMD', 'weight' => 'LB_IN');
		$dhl_core['BN'] = array('region' => 'AP', 'currency' =>'BND', 'weight' => 'KG_CM');
		$dhl_core['BO'] = array('region' => 'AM', 'currency' =>'BOB', 'weight' => 'KG_CM');
		$dhl_core['BR'] = array('region' => 'AM', 'currency' =>'BRL', 'weight' => 'KG_CM');
		$dhl_core['BS'] = array('region' => 'AM', 'currency' =>'BSD', 'weight' => 'LB_IN');
		$dhl_core['BT'] = array('region' => 'AP', 'currency' =>'BTN', 'weight' => 'KG_CM');
		$dhl_core['BW'] = array('region' => 'AP', 'currency' =>'BWP', 'weight' => 'KG_CM');
		$dhl_core['BY'] = array('region' => 'AP', 'currency' =>'BYR', 'weight' => 'KG_CM');
		$dhl_core['BZ'] = array('region' => 'AM', 'currency' =>'BZD', 'weight' => 'KG_CM');
		$dhl_core['CA'] = array('region' => 'AM', 'currency' =>'CAD', 'weight' => 'LB_IN');
		$dhl_core['CF'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['CG'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['CH'] = array('region' => 'EU', 'currency' =>'CHF', 'weight' => 'KG_CM');
		$dhl_core['CI'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['CK'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_core['CL'] = array('region' => 'AM', 'currency' =>'CLP', 'weight' => 'KG_CM');
		$dhl_core['CM'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['CN'] = array('region' => 'AP', 'currency' =>'CNY', 'weight' => 'KG_CM');
		$dhl_core['CO'] = array('region' => 'AM', 'currency' =>'COP', 'weight' => 'KG_CM');
		$dhl_core['CR'] = array('region' => 'AM', 'currency' =>'CRC', 'weight' => 'KG_CM');
		$dhl_core['CU'] = array('region' => 'AM', 'currency' =>'CUC', 'weight' => 'KG_CM');
		$dhl_core['CV'] = array('region' => 'AP', 'currency' =>'CVE', 'weight' => 'KG_CM');
		$dhl_core['CY'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['CZ'] = array('region' => 'EU', 'currency' =>'CZK', 'weight' => 'KG_CM');
		$dhl_core['DE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['DJ'] = array('region' => 'EU', 'currency' =>'DJF', 'weight' => 'KG_CM');
		$dhl_core['DK'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_core['DM'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['DO'] = array('region' => 'AP', 'currency' =>'DOP', 'weight' => 'LB_IN');
		$dhl_core['DZ'] = array('region' => 'AM', 'currency' =>'DZD', 'weight' => 'KG_CM');
		$dhl_core['EC'] = array('region' => 'EU', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['EE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['EG'] = array('region' => 'AP', 'currency' =>'EGP', 'weight' => 'KG_CM');
		$dhl_core['ER'] = array('region' => 'EU', 'currency' =>'ERN', 'weight' => 'KG_CM');
		$dhl_core['ES'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['ET'] = array('region' => 'AU', 'currency' =>'ETB', 'weight' => 'KG_CM');
		$dhl_core['FI'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['FJ'] = array('region' => 'AP', 'currency' =>'FJD', 'weight' => 'KG_CM');
		$dhl_core['FK'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['FM'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['FO'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_core['FR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GA'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['GD'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['GE'] = array('region' => 'AM', 'currency' =>'GEL', 'weight' => 'KG_CM');
		$dhl_core['GF'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GG'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['GH'] = array('region' => 'AP', 'currency' =>'GHS', 'weight' => 'KG_CM');
		$dhl_core['GI'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['GL'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_core['GM'] = array('region' => 'AP', 'currency' =>'GMD', 'weight' => 'KG_CM');
		$dhl_core['GN'] = array('region' => 'AP', 'currency' =>'GNF', 'weight' => 'KG_CM');
		$dhl_core['GP'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GQ'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['GR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['GT'] = array('region' => 'AM', 'currency' =>'GTQ', 'weight' => 'KG_CM');
		$dhl_core['GU'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['GW'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['GY'] = array('region' => 'AP', 'currency' =>'GYD', 'weight' => 'LB_IN');
		$dhl_core['HK'] = array('region' => 'AM', 'currency' =>'HKD', 'weight' => 'KG_CM');
		$dhl_core['HN'] = array('region' => 'AM', 'currency' =>'HNL', 'weight' => 'KG_CM');
		$dhl_core['HR'] = array('region' => 'AP', 'currency' =>'HRK', 'weight' => 'KG_CM');
		$dhl_core['HT'] = array('region' => 'AM', 'currency' =>'HTG', 'weight' => 'LB_IN');
		$dhl_core['HU'] = array('region' => 'EU', 'currency' =>'HUF', 'weight' => 'KG_CM');
		$dhl_core['IC'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['ID'] = array('region' => 'AP', 'currency' =>'IDR', 'weight' => 'KG_CM');
		$dhl_core['IE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['IL'] = array('region' => 'AP', 'currency' =>'ILS', 'weight' => 'KG_CM');
		$dhl_core['IN'] = array('region' => 'AP', 'currency' =>'INR', 'weight' => 'KG_CM');
		$dhl_core['IQ'] = array('region' => 'AP', 'currency' =>'IQD', 'weight' => 'KG_CM');
		$dhl_core['IR'] = array('region' => 'AP', 'currency' =>'IRR', 'weight' => 'KG_CM');
		$dhl_core['IS'] = array('region' => 'EU', 'currency' =>'ISK', 'weight' => 'KG_CM');
		$dhl_core['IT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['JE'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_core['JM'] = array('region' => 'AM', 'currency' =>'JMD', 'weight' => 'KG_CM');
		$dhl_core['JO'] = array('region' => 'AP', 'currency' =>'JOD', 'weight' => 'KG_CM');
		$dhl_core['JP'] = array('region' => 'AP', 'currency' =>'JPY', 'weight' => 'KG_CM');
		$dhl_core['KE'] = array('region' => 'AP', 'currency' =>'KES', 'weight' => 'KG_CM');
		$dhl_core['KG'] = array('region' => 'AP', 'currency' =>'KGS', 'weight' => 'KG_CM');
		$dhl_core['KH'] = array('region' => 'AP', 'currency' =>'KHR', 'weight' => 'KG_CM');
		$dhl_core['KI'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['KM'] = array('region' => 'AP', 'currency' =>'KMF', 'weight' => 'KG_CM');
		$dhl_core['KN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['KP'] = array('region' => 'AP', 'currency' =>'KPW', 'weight' => 'LB_IN');
		$dhl_core['KR'] = array('region' => 'AP', 'currency' =>'KRW', 'weight' => 'KG_CM');
		$dhl_core['KV'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['KW'] = array('region' => 'AP', 'currency' =>'KWD', 'weight' => 'KG_CM');
		$dhl_core['KY'] = array('region' => 'AM', 'currency' =>'KYD', 'weight' => 'KG_CM');
		$dhl_core['KZ'] = array('region' => 'AP', 'currency' =>'KZF', 'weight' => 'LB_IN');
		$dhl_core['LA'] = array('region' => 'AP', 'currency' =>'LAK', 'weight' => 'KG_CM');
		$dhl_core['LB'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['LC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'KG_CM');
		$dhl_core['LI'] = array('region' => 'AM', 'currency' =>'CHF', 'weight' => 'LB_IN');
		$dhl_core['LK'] = array('region' => 'AP', 'currency' =>'LKR', 'weight' => 'KG_CM');
		$dhl_core['LR'] = array('region' => 'AP', 'currency' =>'LRD', 'weight' => 'KG_CM');
		$dhl_core['LS'] = array('region' => 'AP', 'currency' =>'LSL', 'weight' => 'KG_CM');
		$dhl_core['LT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['LU'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['LV'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['LY'] = array('region' => 'AP', 'currency' =>'LYD', 'weight' => 'KG_CM');
		$dhl_core['MA'] = array('region' => 'AP', 'currency' =>'MAD', 'weight' => 'KG_CM');
		$dhl_core['MC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MD'] = array('region' => 'AP', 'currency' =>'MDL', 'weight' => 'KG_CM');
		$dhl_core['ME'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MG'] = array('region' => 'AP', 'currency' =>'MGA', 'weight' => 'KG_CM');
		$dhl_core['MH'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['MK'] = array('region' => 'AP', 'currency' =>'MKD', 'weight' => 'KG_CM');
		$dhl_core['ML'] = array('region' => 'AP', 'currency' =>'COF', 'weight' => 'KG_CM');
		$dhl_core['MM'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['MN'] = array('region' => 'AP', 'currency' =>'MNT', 'weight' => 'KG_CM');
		$dhl_core['MO'] = array('region' => 'AP', 'currency' =>'MOP', 'weight' => 'KG_CM');
		$dhl_core['MP'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['MQ'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MR'] = array('region' => 'AP', 'currency' =>'MRO', 'weight' => 'KG_CM');
		$dhl_core['MS'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['MT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['MU'] = array('region' => 'AP', 'currency' =>'MUR', 'weight' => 'KG_CM');
		$dhl_core['MV'] = array('region' => 'AP', 'currency' =>'MVR', 'weight' => 'KG_CM');
		$dhl_core['MW'] = array('region' => 'AP', 'currency' =>'MWK', 'weight' => 'KG_CM');
		$dhl_core['MX'] = array('region' => 'AM', 'currency' =>'MXN', 'weight' => 'KG_CM');
		$dhl_core['MY'] = array('region' => 'AP', 'currency' =>'MYR', 'weight' => 'KG_CM');
		$dhl_core['MZ'] = array('region' => 'AP', 'currency' =>'MZN', 'weight' => 'KG_CM');
		$dhl_core['NA'] = array('region' => 'AP', 'currency' =>'NAD', 'weight' => 'KG_CM');
		$dhl_core['NC'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
		$dhl_core['NE'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['NG'] = array('region' => 'AP', 'currency' =>'NGN', 'weight' => 'KG_CM');
		$dhl_core['NI'] = array('region' => 'AM', 'currency' =>'NIO', 'weight' => 'KG_CM');
		$dhl_core['NL'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['NO'] = array('region' => 'EU', 'currency' =>'NOK', 'weight' => 'KG_CM');
		$dhl_core['NP'] = array('region' => 'AP', 'currency' =>'NPR', 'weight' => 'KG_CM');
		$dhl_core['NR'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['NU'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_core['NZ'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_core['OM'] = array('region' => 'AP', 'currency' =>'OMR', 'weight' => 'KG_CM');
		$dhl_core['PA'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['PE'] = array('region' => 'AM', 'currency' =>'PEN', 'weight' => 'KG_CM');
		$dhl_core['PF'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
		$dhl_core['PG'] = array('region' => 'AP', 'currency' =>'PGK', 'weight' => 'KG_CM');
		$dhl_core['PH'] = array('region' => 'AP', 'currency' =>'PHP', 'weight' => 'KG_CM');
		$dhl_core['PK'] = array('region' => 'AP', 'currency' =>'PKR', 'weight' => 'KG_CM');
		$dhl_core['PL'] = array('region' => 'EU', 'currency' =>'PLN', 'weight' => 'KG_CM');
		$dhl_core['PR'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['PT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['PW'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['PY'] = array('region' => 'AM', 'currency' =>'PYG', 'weight' => 'KG_CM');
		$dhl_core['QA'] = array('region' => 'AP', 'currency' =>'QAR', 'weight' => 'KG_CM');
		$dhl_core['RE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['RO'] = array('region' => 'EU', 'currency' =>'RON', 'weight' => 'KG_CM');
		$dhl_core['RS'] = array('region' => 'AP', 'currency' =>'RSD', 'weight' => 'KG_CM');
		$dhl_core['RU'] = array('region' => 'AP', 'currency' =>'RUB', 'weight' => 'KG_CM');
		$dhl_core['RW'] = array('region' => 'AP', 'currency' =>'RWF', 'weight' => 'KG_CM');
		$dhl_core['SA'] = array('region' => 'AP', 'currency' =>'SAR', 'weight' => 'KG_CM');
		$dhl_core['SB'] = array('region' => 'AP', 'currency' =>'SBD', 'weight' => 'KG_CM');
		$dhl_core['SC'] = array('region' => 'AP', 'currency' =>'SCR', 'weight' => 'KG_CM');
		$dhl_core['SD'] = array('region' => 'AP', 'currency' =>'SDG', 'weight' => 'KG_CM');
		$dhl_core['SE'] = array('region' => 'EU', 'currency' =>'SEK', 'weight' => 'KG_CM');
		$dhl_core['SG'] = array('region' => 'AP', 'currency' =>'SGD', 'weight' => 'KG_CM');
		$dhl_core['SH'] = array('region' => 'AP', 'currency' =>'SHP', 'weight' => 'KG_CM');
		$dhl_core['SI'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['SK'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['SL'] = array('region' => 'AP', 'currency' =>'SLL', 'weight' => 'KG_CM');
		$dhl_core['SM'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['SN'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['SO'] = array('region' => 'AM', 'currency' =>'SOS', 'weight' => 'KG_CM');
		$dhl_core['SR'] = array('region' => 'AM', 'currency' =>'SRD', 'weight' => 'KG_CM');
		$dhl_core['SS'] = array('region' => 'AP', 'currency' =>'SSP', 'weight' => 'KG_CM');
		$dhl_core['ST'] = array('region' => 'AP', 'currency' =>'STD', 'weight' => 'KG_CM');
		$dhl_core['SV'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['SY'] = array('region' => 'AP', 'currency' =>'SYP', 'weight' => 'KG_CM');
		$dhl_core['SZ'] = array('region' => 'AP', 'currency' =>'SZL', 'weight' => 'KG_CM');
		$dhl_core['TC'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['TD'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_core['TG'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_core['TH'] = array('region' => 'AP', 'currency' =>'THB', 'weight' => 'KG_CM');
		$dhl_core['TJ'] = array('region' => 'AP', 'currency' =>'TJS', 'weight' => 'KG_CM');
		$dhl_core['TL'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['TN'] = array('region' => 'AP', 'currency' =>'TND', 'weight' => 'KG_CM');
		$dhl_core['TO'] = array('region' => 'AP', 'currency' =>'TOP', 'weight' => 'KG_CM');
		$dhl_core['TR'] = array('region' => 'AP', 'currency' =>'TRY', 'weight' => 'KG_CM');
		$dhl_core['TT'] = array('region' => 'AM', 'currency' =>'TTD', 'weight' => 'LB_IN');
		$dhl_core['TV'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_core['TW'] = array('region' => 'AP', 'currency' =>'TWD', 'weight' => 'KG_CM');
		$dhl_core['TZ'] = array('region' => 'AP', 'currency' =>'TZS', 'weight' => 'KG_CM');
		$dhl_core['UA'] = array('region' => 'AP', 'currency' =>'UAH', 'weight' => 'KG_CM');
		$dhl_core['UG'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_core['US'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['UY'] = array('region' => 'AM', 'currency' =>'UYU', 'weight' => 'KG_CM');
		$dhl_core['UZ'] = array('region' => 'AP', 'currency' =>'UZS', 'weight' => 'KG_CM');
		$dhl_core['VC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['VE'] = array('region' => 'AM', 'currency' =>'VEF', 'weight' => 'KG_CM');
		$dhl_core['VG'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['VI'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_core['VN'] = array('region' => 'AP', 'currency' =>'VND', 'weight' => 'KG_CM');
		$dhl_core['VU'] = array('region' => 'AP', 'currency' =>'VUV', 'weight' => 'KG_CM');
		$dhl_core['WS'] = array('region' => 'AP', 'currency' =>'WST', 'weight' => 'KG_CM');
		$dhl_core['XB'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_core['XC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_core['XE'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
		$dhl_core['XM'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_core['XN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_core['XS'] = array('region' => 'AP', 'currency' =>'SIS', 'weight' => 'KG_CM');
		$dhl_core['XY'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
		$dhl_core['YE'] = array('region' => 'AP', 'currency' =>'YER', 'weight' => 'KG_CM');
		$dhl_core['YT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_core['ZA'] = array('region' => 'AP', 'currency' =>'ZAR', 'weight' => 'KG_CM');
		$dhl_core['ZM'] = array('region' => 'AP', 'currency' =>'ZMW', 'weight' => 'KG_CM');
		$dhl_core['ZW'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		
	}
	$hit_m_dhlexpress = new hit_m_dhlexpress_parent();
}
