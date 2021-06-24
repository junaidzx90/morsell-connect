<?php
/**
 * Plugin Name: MobilityBuy Connect
 * Plugin Uri: https://github.com/junaidzx90/mobilitybuy-connect
 * Author: Junayed
 * Author Uri: https://www.fiverr.com/junaidzx90
 * Description: This plugin is a part of the "MobilityBuy" plugin.
 * Version: 1.0.2
 * Text Domain:       mobilitybuy-connect
 * Domain Path:       /languages
*/

add_action("rest_api_init", "mobilitybuy_custom_api_for_inser_product");
add_action( 'woocommerce_order_status_completed', 'mobilitybuy_order_processing', 10, 1);
add_action("admin_menu", "mobilitybuy_connect_menupage");
add_action('woocommerce_product_options_pricing', 'mobilitybuy_add_admin_scripts');

require __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;
$url = get_option('mobilitybuy_parent_site_url','');
if(substr($url , -1)=='/'){
    $url = rtrim($url,"/");
}
$_woo = new Client(
    $url, 
    get_option('mobilitybuy_consumar_key',''), 
    get_option('mobilitybuy_consumer_secret',''),
    [
        'version' => 'wc/v3',
    ]
);

register_activation_hook( __FILE__, 'activate_mobilitybuy_connect' );
function activate_mobilitybuy_connect(){
    flush_rewrite_rules(  );
}
// Menu page
function mobilitybuy_connect_menupage(){
    add_submenu_page( 'options-general.php', 'MobilityBuy Connect', 'MobilityBuy Connect', 'manage_options', 'mobilitybuy-connect', 'mobilitybuy_connect_view');
}

// View page
function mobilitybuy_connect_view(){
    require_once plugin_dir_path( __FILE__ )."inc/mobilitybuy-admin-view.php";
}

/**
 * POST REQU API
 */
function mobilitybuy_custom_api_for_inser_product(){
    register_rest_route( 'ms/v1','create',[
        'methods' => 'POST',
        'callback' => 'mobilitybuy_requests_for_product',
        'permission_callback' => '__return_true'
    ]);
}

function mobilitybuy_add_admin_scripts( $hook ) {
    global $post;
    if(get_post_meta($post->ID,'original_product_id')){
        ?>
        <script type="text/javascript">
        document.getElementById("_regular_price").setAttribute('name', '_price');
        document.getElementById("_sale_price").setAttribute('name', '_price');
        document.getElementById("_regular_price").setAttribute('readonly', true);
        document.getElementById("_sale_price").setAttribute('readonly', true);
        document.getElementById("_regular_price").setAttribute('disabled', true);
        document.getElementById("_sale_price").setAttribute('disabled', true);
        </script>
        <?php
    }
}

/**
 * Attach images to product (feature)
 */
function attach_product_thumbnail($post_id, $url, $flag){

    $image_url = $url;
    $url_array = explode('/',$url);
    $image_name = $url_array[count($url_array)-1];
    $image_data = file_get_contents($image_url);

    $upload_dir = wp_upload_dir();
    $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name );
    $filename = basename( $unique_file_name );

    if( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents( $file, $image_data );
    $wp_filetype = wp_check_filetype( $filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

    wp_update_attachment_metadata( $attach_id, $attach_data );

    if( $flag == 1 ){
        $attach_id_array = get_post_meta($post_id,'_product_image_gallery', true);
        $imgs = $attach_id_array .= ','.$attach_id;
        update_post_meta($post_id,'_product_image_gallery',$imgs);
    }
    if( $flag == 0){
        set_post_thumbnail( $post_id, $attach_id );
    }

    return true;
}

/**
 * Category Update
 */
function mobilitybuy_category_update($product_id,$cats){
    $cats_list = [];
    $taxonomy = 'product_cat';

    $parents = [];
    foreach( $cats as $term ) {
        $parents[$term['term_id']] = $term['name'];
    }

    $ids = [];
    foreach( $cats as $term ) {
        $cats_list[] = $term;
        $parent = $term['parent'];

        if($parent){
            $name = '';
            $haschildid = 0;
            if(array_key_exists($parent,$parents)){
                $name = $parents[$parent];

                if(!$haschild = term_exists( $name, $taxonomy )){
                    $insid = wp_insert_term(
                        $name, 
                        $taxonomy
                    );
                    $haschildid = $insid['term_id'];
                    $ids[] = $insid['term_id'];
                }else{
                    $chid = wp_update_term( 
                        $haschild['term_id'], 
                        $taxonomy
                    );
                    $haschildid = $haschild['term_id'];
                    $ids[] = $haschild['term_id'];
                }
            }
            
            if($has = term_exists( $term['name'], $taxonomy )){
                wp_update_term( 
                    $has['term_id'], 
                    $taxonomy,
                    array(
                        'parent'=> $haschildid,
                    )
                );
                $ids[] = $has['term_id'];
            }else{
                $tid = wp_insert_term(
                    $term['name'], 
                    $taxonomy,
                    array(
                        'parent'=> $haschildid,
                        'slug' => $term['name']
                    )
                );
                $ids[] = $tid['term_id'];
            }
        }else{
            if($has = term_exists( $term['name'], $taxonomy )){
                wp_update_term( 
                    $has['term_id'], 
                    $taxonomy
                );
                $ids[] = $has['term_id'];
            }else{
                $tid = wp_insert_term(
                    $term['name'],
                    $taxonomy
                );
                $ids[] = $tid['term_id'];
            }
        }
        
    }

    $terms = get_the_terms( $product_id, $taxonomy);
    foreach($terms as $thisid){
        wp_remove_object_terms( $product_id, $thisid->name, $taxonomy );
    }
    wp_set_post_terms( $product_id, $ids, $taxonomy, true );
}

// API Callback
function mobilitybuy_requests_for_product($data){
    global $wpdb;
    $save_data = false;

    if($data['categories']){
        $cattos = [];
        foreach($data['categories'] as $catto){
            $cattos[] = $catto['name'];
        }
    }

    $expectedcats = get_option('expected_categories','');

    $definedcats = explode(',',$expectedcats);
    
    foreach($definedcats as $define){
        if(in_array($define,$cattos)){
            $save_data = true;
        }else{
            $save_data = false;
        }
    }
    
    if($save_data){
        $post_id = 0;
        if($post_id = $wpdb->get_var("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'original_product_id' AND meta_value = {$data['original_pid']}") ){
            wp_update_post(array(
                'ID' => intval($post_id),
                'post_title' => $data['name'],
                'post_type' => 'product',
                'post_status' => $data['status'],
                'post_content' => $data['description'],
                'post_excerpt' => $data['short_description'],
            ));

            $args = array(
                'post_type'   => 'attachment',
                'numberposts' => -1,
                'post_status' => 'any',
                'post_parent' => $post_id
            );
            
            $attachments = get_posts( $args );
            
            if ( $attachments ) {
                foreach ( $attachments as $attachment ) {
                    wp_delete_attachment( $attachment->ID);
                }
            }
        }else{
            $post_id = wp_insert_post(array(
                'post_title' => $data['name'],
                'post_type' => 'product',
                'post_status' => $data['status'],
                'post_content' => $data['description'],
                'post_excerpt' => $data['short_description'],
            ));   
        }

        if(!empty($data['the_tags'])){
            wp_set_object_terms($post_id, $data['the_tags'], 'product_tag');
        }

        mobilitybuy_category_update($post_id,$data['categories']);
        wp_set_object_terms( $post_id, $data['type'], 'product_type' );
        update_post_meta( $post_id, '_visibility', 'visible' );
        update_post_meta( $post_id, '_stock_status', 'instock');
        update_post_meta( $post_id, '_stock', $data['_stock']);
        update_post_meta( $post_id, 'total_sales', $data['total_sales'] );
        update_post_meta( $post_id, '_downloadable', $data['downloadable'] );
        update_post_meta( $post_id, '_virtual', $data['virtual'] );
        update_post_meta( $post_id, '_price', $data['price'] );
        update_post_meta( $post_id, '_regular_price', $data['regular_price'] );
        update_post_meta( $post_id, '_sale_price', $data['sale_price'] );
        update_post_meta( $post_id, '_purchase_note', $data['purchase_note'] );
        update_post_meta( $post_id, '_purchasable', $data['purchasable'] );
        update_post_meta( $post_id, '_featured', $data['featured'] );
        update_post_meta( $post_id, '_download_limit', $data['download_limit'] );
        update_post_meta( $post_id, '_download_expiry', $data['download_expiry'] );
        update_post_meta( $post_id, '_weight', !empty($data['_weight'] )?$data['_weight']:'');
        update_post_meta( $post_id, '_length', !empty($data['_length'] )?$data['_length']:'');
        update_post_meta( $post_id, '_width', !empty($data['_width'] )?$data['_width']:'');
        update_post_meta( $post_id, '_height', !empty($data['_height'] )?$data['_height']:'');
        update_post_meta( $post_id, '_sku', $data['_sku'] );
        update_post_meta( $post_id, '_product_attributes', $data['attributes'] );
        update_post_meta( $post_id, '_sale_price_dates_from', $data['date_on_sale_from'] );
        update_post_meta( $post_id, '_sale_price_dates_to', $data['date_on_sale_to'] );
        update_post_meta( $post_id, '_sold_individually', '' );
        update_post_meta( $post_id, '_manage_stock', $data['manage_stock'] );
        update_post_meta( $post_id, '_product_image_gallery', $data['gallery_img']);
        update_post_meta( $post_id, '_downloadable_files', $data['downloadable_files']);
        update_post_meta( $post_id, '_product_attributes', $data['product_attributes']);
        update_post_meta( $post_id, '_download_limit', $data['download_limit']);
        update_post_meta( $post_id, 'original_product_id', $data['original_pid']);

        // set product is simple/variable/grouped
        if(!empty($data['attached'])){
            $res = attach_product_thumbnail($post_id,$data['attached'],0);
        }
        
        if(!empty($data['images'])){
            foreach($data['images'] as $image ){
                attach_product_thumbnail($post_id,$image,1);
            }
        }
        return 'success';

    }else{
        return true;
    }
}

// SEND POST REQUEST
function send_post_to_json($data){
    $purl = get_option('mobilitybuy_parent_site_url','');
    if(substr($purl , -1)=='/'){
        $purl = rtrim($purl,"/");
    }

    $url = $purl.'/wp-json/ms/v1/infocreate';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $res = 'Response code: ' . $response_code;
    curl_close($ch);
    return $result;
}

/**
 * Order update to parent site
 */
function mobilitybuy_order_processing($order_id){
    global $_woo;
    $order = wc_get_order( $order_id );
    $items = $order->get_items();
    $details = $order->get_data();

    foreach ( $items as $item ) {
        $product_id = $item->get_product_id();
        $original_id = get_post_meta( $product_id, 'original_product_id', true );

        if($original_id){
            $data = [
                'payment_method' => $details['payment_method'],
                'payment_method_title' => $details['payment_method_title'],
                'set_paid' => true,
                'billing' => [
                    'first_name' => $details['billing']['first_name'],
                    'last_name' => $details['billing']['last_name'],
                    'address_1' => $details['billing']['address_1'],
                    'address_2' => $details['billing']['address_2'],
                    'city' => $details['billing']['city'],
                    'state' => $details['billing']['state'],
                    'postcode' => $details['billing']['postcode'],
                    'country' => $details['billing']['country'],
                    'email' => $details['billing']['email'],
                    'phone' => $details['billing']['phone']
                ],
                'shipping' => [
                    'first_name' => $details['shipping']['first_name'],
                    'last_name' => $details['shipping']['last_name'],
                    'address_1' => $details['shipping']['address_1'],
                    'address_2' => $details['shipping']['address_2'],
                    'city' => $details['shipping']['city'],
                    'state' => $details['shipping']['state'],
                    'postcode' => $details['shipping']['postcode'],
                    'country' => $details['shipping']['country']
                ],
                'line_items' => [
                    [
                        'product_id' => $item->get_product_id(),
                        'quantity' => $item->get_quantity()
                    ]
                ],
                'shipping_lines' => [
                    [
                        'method_id' => 'flat_rate',
                        'method_title' => 'Flat Rate',
                        'total' => $order->get_total()
                    ]
                ]
            ];

            $ordered = $_woo->post('orders', $data);
            send_post_to_json(http_build_query(['order_id' => $ordered->id, 'sold_by' => get_home_url()]));
        }
    }
}