<?php
/**
 * Plugin Name: Moresell Connect
 * Plugin Uri: https://github.com/junaidzx90/moresell-connect
 * Author: Junayed
 * Author Uri: https://www.fiverr.com/junaidzx90
 * Description: This plugin is a part of the "Moresell" plugin.
 * Version: 0.0.1
 * Text Domain:       moresell-connect
 * Domain Path:       /languages
*/

add_action("rest_api_init", "moresell_custom_api_for_inser_product");
add_action( 'woocommerce_order_status_completed', 'moresell_order_processing', 10, 1);
add_action("admin_menu", "moresell_connect_menupage");

require __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;
$url = get_option('moresell_parent_site_url','');
if(substr($url , -1)=='/'){
    $url = rtrim($url,"/");
}
$_woo = new Client(
    $url, 
    get_option('moresell_consumar_key',''), 
    get_option('moresell_consumer_secret',''),
    [
        'version' => 'wc/v3',
    ]
);

// Menu page
function moresell_connect_menupage(){
    add_submenu_page( 'options-general.php', 'Moresell Connect', 'Moresell Connect', 'manage_options', 'moresell-connect', 'moresell_connect_view');
}

// View page
function moresell_connect_view(){
    require_once plugin_dir_path( __FILE__ )."inc/moresell-admin-view.php";
}

/**
 * POST REQU API
 */
function moresell_custom_api_for_inser_product(){
    register_rest_route( 'ms/v1','create',[
        'methods' => 'POST',
        'callback' => 'moresell_requests_for_product',
        'permission_callback' => '__return_true'
    ]);
}

/**
 * Attach images to product (feature/ gallery)
 */
function attach_product_thumbnail($post_id, $url, $flag){
    /*
    * If allow_url_fopen is enable in php.ini then use this
    */
    $image_url = $url;
    $url_array = explode('/',$url);
    $image_name = $url_array[count($url_array)-1];
    $image_data = file_get_contents($image_url); // Get image data

    $upload_dir = wp_upload_dir(); // Set upload folder
    $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); //    Generate unique name
    $filename = basename( $unique_file_name ); // Create image file name

    // Check folder permission and define file location
    if( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    // Create the image file on the server
    file_put_contents( $file, $image_data );

    // Check image file type
    $wp_filetype = wp_check_filetype( $filename, null );

    // Set attachment data
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    // Create the attachment
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );

    // Include image.php
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Define attachment metadata
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

    // Assign metadata to attachment
    wp_update_attachment_metadata( $attach_id, $attach_data );

    // asign to feature image
    if( $flag == 0){
        // And finally assign featured image to post
        set_post_thumbnail( $post_id, $attach_id );
    }

    // assign to the product gallery
    if( $flag == 1 ){
        // Add gallery image to product
        $attach_id_array = get_post_meta($post_id,'_product_image_gallery', true);
        $attach_id_array .= ','.$attach_id;
        update_post_meta($post_id,'_product_image_gallery',$attach_id_array);
    }

}

/**
 * Category Update
 */
function moresell_category_update($product_id,$cat){
    $pid = $product_id;
    $cat_name = $cat;
    $taxonomy = 'product_cat';
    $append = true ;

    $cat  = get_term_by('name', $cat_name , $taxonomy);

    if($cat == false){
        $cat = wp_insert_term($cat_name, $taxonomy);
        $cat_id = $cat['term_id'] ;
    }else{
        $cat_id = $cat->term_id ;
    }
    //setting post category 
    $res = wp_set_post_terms($pid,array($cat_id),$taxonomy ,$append);
    return true;
}

// API Callback
function moresell_requests_for_product($data){
    global $wpdb;
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

    moresell_category_update($post_id,$data['categories']);
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
    update_post_meta( $post_id, '_weight', '11' );
    update_post_meta( $post_id, '_length', '11' );
    update_post_meta( $post_id, '_width', !empty($data['dimensions']->width )?$data['dimensions']->width:'');
    update_post_meta( $post_id, '_height', !empty($data['dimensions']->height )?$data['dimensions']->height:'');
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
    
    if(!empty($data['images'])){
        foreach($data['images'] as $image ){
            attach_product_thumbnail($post_id,$image,1);
        }
    }

    // set product is simple/variable/grouped
    attach_product_thumbnail($post_id,$data['attached'],0);

    return 'Success';
}

// SEND POST REQUEST
function send_post_to_json($data){
    $purl = get_option('moresell_parent_site_url','');
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
function moresell_order_processing($order_id){
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