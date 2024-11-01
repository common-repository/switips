<?php
/**
* Plugin Name:       Switips
* Plugin URI:        
* Description:       Uploading order information
* Version:           1.2
* Requires at least: 4.2
* Requires PHP:      7.2
* Author:            Outcode
* Author URI:        https://outcode.ru/
* License:           GPL v2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       switips
* Domain Path:       /languages
*/


if ( ! defined( 'ABSPATH' ) ) exit;
defined('SWITIPS_BASE_FILE') || define('SWITIPS_BASE_FILE', __FILE__);

if (!class_exists('Switips')) {
  require_once( 'switips-core.php' );
}

__( 'Switips', 'switips' );
__( 'Uploading order information', 'switips' );


// подключение файла перевода
add_action( 'plugins_loaded', function(){
	load_plugin_textdomain( 'switips', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
} );

register_activation_hook(__FILE__, 'create_table_orders_switips');

function create_table_orders_switips()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "orders_switips";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
              id int(10) NOT NULL AUTO_INCREMENT,
              userId VARCHAR (255) NOT NULL,
              orderId VARCHAR (255) NOT NULL,
              status VARCHAR(255) NOT NULL,
              PRIMARY KEY  (id)
            ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

if ( !function_exists('switips_init') ) {
  add_action('init', 'switips_init');
  add_filter('mh_switips_settings', 'switips_settings');
  add_action('init', 'switips_user_id_cookie' );
  add_action('woocommerce_checkout_update_order_meta', 'switips_check_user', 10, 2 );
  add_action('woocommerce_order_status_changed', 'switips_check_status', 20);
  add_action( 'woocommerce_payment_complete', 'switips_payment_complete');

  add_action('woocommerce_product_options_general_product_data', 'switips_product_custom_fields');
  add_action('woocommerce_process_product_meta', 'switips_product_custom_fields_save');

  add_action('product_cat_add_form_fields', 'switips_add_product_category_custom_fields', 10, 1);
  add_action('product_cat_edit_form_fields', 'switips_edit_product_category_custom_fields', 10, 1);

  add_action('edited_product_cat', 'switips_save_product_category_custom_fields', 10, 1);
  add_action('create_product_cat', 'switips_save_product_category_custom_fields', 10, 1);

  function switips_init() {

        // common lib init
        include_once( 'common/SWCommon.php' );
        switips_common::initialize(
            'woocommerce-switips',
            'switips',
            SWITIPS_BASE_FILE,
            __('WooCommerce Switips', 'switips')
        );


    do_action('switips_init');
  }

  function switips_settings($arr) {
      $arr['url_api'] = array(
          'label' => __( 'URL API', 'switips' ),
          'tab' => __( 'Properties', 'switips' ),
          'type' => 'text',
          'default' => ""
      );
      $arr['merchant_id'] = array(
          'label' => __( 'Partner ID', 'switips' ),
          'tab' => __( 'Properties', 'switips' ),
          'type' => 'text',
          'default' => ""
      );

      $arr['secret_key'] = array(
          'label' => __( 'Secret key', 'switips' ),
          'tab' => __( 'Properties', 'switips' ),
          'type' => 'text',
          'default' => ""
      );

      $arr['currency'] = array(
          'label' => __( 'Currency', 'switips' ),
          'tab' => __( 'Properties', 'switips' ),
          'type' => 'select',
          'options' => get_woocommerce_currencies(),
          'default' => get_woocommerce_currency(),
      );
  
      return $arr;
  }

  function switips_option($name) {
      return apply_filters('mh_switips_setting_value', $name);
  }

  function switips_user_id_cookie() {
    $uid = (isset($_GET['uid'])) ? sanitize_text_field($_GET['uid']) : '';
    if($uid) {
      setcookie( 'SWITIPS_USER_ID', $uid, time() + ( 3600 * 24 * 15 ), '/' );
    }
  }


  function switips_status($woo_status) {
      switch ($woo_status) {
        case 'pending':
          return 'new';
          break;
        case 'processing':
          return 'paid';
          break;
        case 'on-hold':
          return 'new';
          break;
        case 'completed':
          return 'confirmed';
          break;
        case 'cancelled':
          return 'canceled';
          break;
        case 'refund':
          return 'canceled';
          break;
        case 'faild':
          return 'canceled';
          break;
      }
  }

  function switips_get_commission($order) {
      $commission = 0;
      foreach ($order->get_items() as $item_id => $item_data) {
        $item_quantity = $item_data->get_quantity();

        $product_commission = get_post_meta($item_data['product_id'], '_custom_product_commission_amount', true);
        $product_commission = $product_commission ? $product_commission : 0;
        $product = $item_data->get_product();

        if(!$product_commission) {
          $cats = get_the_terms( $item_data['product_id'], 'product_cat' );
          $cat_commission = array();
          foreach ($cats as $cat) {
            $cat_commission_amount = get_term_meta($cat->term_id, '_custom_product_category_commission_amount', true);
            if($cat_commission_amount) {
              if (is_numeric($cat_commission_amount)) {
                $cat_commission[] = (int)$cat_commission_amount;
              } elseif(strpos($cat_commission_amount, "%") !== false) {
                $procent = (int)str_replace("%", '', $cat_commission_amount);
                $cat_commission[] = round($procent * $product->get_price() / 100);
              }
              
            }
            $check_commission_amount = (count($cat_commission) > 0) ? max($cat_commission) : 0;
          }
          $commission = $commission + $check_commission_amount * $item_quantity;
        } else {
          if (is_numeric($product_commission)) {
            $commission = $commission + $product_commission * $item_quantity;
          } elseif(strpos($product_commission, "%") !== false) {
            $procent = (int)str_replace("%", '', $product_commission);
            $commission = $commission + round($product->get_price() * $procent / 100) * $item_quantity;
          }
          
        }
      }
      return $commission;
  }

  function switips_logs($args) {
      // log send
      $log = '[' . date('D M d H:i:s Y', time()) . '] ';
      $log .= json_encode($args, JSON_UNESCAPED_UNICODE);
      $log .= "\n";
      file_put_contents(dirname(__FILE__) . "/sw_log.log", $log, FILE_APPEND);
  }


  function switips_check_user( $order_id, $data){
      $uid = sanitize_text_field($_COOKIE['SWITIPS_USER_ID']);

      if($uid) {
        global $wpdb;
        $tableName = $wpdb->prefix . "orders_switips";

        $userId = $uid;
        $order = wc_get_order( $order_id );
        $order_data = $order->get_data();

        $args = [
          'merchant_id' => switips_option('merchant_id'),
          'user_id' => $userId,
          'campaign_id' => 0, 
          'category_id' => 0, 
          'transaction_id' => $order_id,
          'transaction_amount' => $order_data['total'],
          'currency' => $order_data['currency'] ? $order_data['currency'] : switips_option('currency'),
          'transaction_amount_currency' => $order_data['total'],
          'tt_date' => time(),
          'stat' => switips_status($order_data['status']),    
          'secret_key' => switips_option('secret_key'),     
          'commission_amount' => switips_get_commission($order),    
        ];

        $obj = new Switips(switips_option('url_api'));
        $ans = $obj -> sendRequestPost($args);

        switips_logs(array_merge($args,array('switips_status' => $ans->status, 'sw_error' => $ans->error)));

        $stat = $ans->status == 'ok' ? 'new' : 'error';

        $wpdb->insert(
            $tableName,
            array('userId' => $userId, 'orderId' => $order_id, 'status' => $stat),
            array('%s', '%s', '%s')
        );
      }
  }
  
  function switips_payment_complete( $order_id) {    
    switips_check_status($order_id, 'paid');
  }  

  function switips_check_status ($order_id, $paid = '') {
    global $wpdb;
    $exist_order = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "orders_switips WHERE orderId=" . $order_id);

    if($exist_order) {

      $order = wc_get_order( $exist_order[0]->orderId);
      $order_data = $order->get_data();

      $userId = $exist_order[0]->userId;
      $status = $exist_order[0]->status;

      $cur_status = switips_status($order_data['status']);
      
      if($paid == ''){
        if($status == $cur_status) {
          return;
        }

        if($status == 'new') {
            if($cur_status != 'new' && $cur_status != 'paid') {
              return;
            }
        } else if($status == 'paid') {
          if($cur_status == 'new' || $cur_status == 'paid') {
            return;
          }
        }
      }

      $args = [
        'merchant_id' => switips_option('merchant_id'),
        'user_id' => $userId,
        'campaign_id' => 0, 
        'category_id' => 0, 
        'transaction_id' => $order_id,
        'transaction_amount' => $order_data['total'],
        'currency' => $order_data['currency'] ? $order_data['currency'] : switips_option('currency'),
        'transaction_amount_currency' => $order_data['total'],
        'tt_date' => time(),
        'stat' => $cur_status,    
        'secret_key' => switips_option('secret_key'),     
        'commission_amount' => switips_get_commission($order),    
      ];

      $obj = new Switips(switips_option('url_api'));
      $ans = $obj -> sendRequestPost($args);

      $stat = $ans->status == 'ok' ? switips_status($order_data['status']) : 'error';

      switips_logs(array_merge($args,array('switips_status' => $ans->status, 'sw_error' => $ans->error)));

      $tableName = $wpdb->prefix . "orders_switips";

      $status = ($paid == '') ? switips_status($order_data['status']) : $paid;
      $wpdb->update( $tableName,
        array( 'status' => $status),
        array( 'orderId' => $order_id )
      );
    }
  }

  function switips_product_custom_fields(){
      global $woocommerce, $post;
      echo '<div class="product_custom_field">';
      woocommerce_wp_text_input(
          array(
              'id' => '_custom_product_commission_amount',
              'placeholder' => '',
              'label' => __( 'Agent s commission', 'switips' ),
              'type' => 'text'
          )
      );
      echo __( '<p class="help">The amount of the agency fee is transferred to Switips. <br> If you indicate the% sign, the amount of remuneration will be calculated as% of the value of the goods <br>Example: <b> 10% </b></p>', 'switips' );
      echo '</div>';
  }

  function switips_product_custom_fields_save($post_id){
      $woocommerce_custom_product_commission_amount = sanitize_text_field($_POST['_custom_product_commission_amount']);
      if (!empty($woocommerce_custom_product_commission_amount)) {
        update_post_meta($post_id, '_custom_product_commission_amount', esc_attr($woocommerce_custom_product_commission_amount));
      }
      esc_attr($woocommerce_custom_product_commission_amount);

  }

  function switips_add_product_category_custom_fields() {
      ?>
      <div class="form-field">
          <label for="wh_meta_title"><?php echo __( 'Agent s commission', 'switips' ); ?></label>
          <input type="text" name="_custom_product_category_commission_amount" id="_custom_product_category_commission_amount" />
          <?php echo __( '<p class="help">The amount of the agency fee is transferred to Switips. <br> If you indicate the% sign, the amount of remuneration will be calculated as% of the value of the goods <br>Example: <b> 10% </b></p>', 'switips' ); ?>          
      </div>
      <?php
  }

  function switips_edit_product_category_custom_fields($term) {
      $term_id = $term->term_id;
      $sw_commission = get_term_meta($term_id, '_custom_product_category_commission_amount', true);
      ?>
      <tr class="form-field">
          <th scope="row" valign="top"><label><?php echo __( 'Agent s commission', 'switips' ); ?></label></th>
          <td>
              <input type="text" name="_custom_product_category_commission_amount" id="_custom_product_category_commission_amount" value="<?php echo esc_attr($sw_commission) ? esc_attr($sw_commission) : ''; ?>" />
              <?php echo __( '<p class="help">The amount of the agency fee is transferred to Switips. <br> If you indicate the% sign, the amount of remuneration will be calculated as% of the value of the goods <br>Example: <b> 10% </b></p>', 'switips' ); ?>          
          </td>
      </tr>
      <?php
  }

  function woocommerce_save_product_category_custom_fields($term_id) {
    $sw_commission = filter_input(INPUT_POST, '_custom_product_category_commission_amount');
    update_term_meta($term_id, '_custom_product_category_commission_amount', $sw_commission);
  }
}
?>