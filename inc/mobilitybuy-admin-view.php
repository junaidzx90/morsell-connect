<h3>Parent Site Woocommerce key's</h3>
<hr>
<?php

if(isset($_POST['keys_save'])){
    if(isset($_POST['parent_site_url'])){
        $mobilitybuy_parent_site_url = esc_url( $_POST['parent_site_url'] );
        update_option('mobilitybuy_parent_site_url', $mobilitybuy_parent_site_url);
    }
    if(isset($_POST['consumar_key'])){
        $consumar_key = sanitize_key( $_POST['consumar_key'] );
        update_option('mobilitybuy_consumar_key', $consumar_key);
    }
    if(isset($_POST['consumer_secret'])){
        $consumer_secret = sanitize_key( $_POST['consumer_secret'] );
        update_option('mobilitybuy_consumer_secret', $consumer_secret);
    }
}

?>
<div class="parentsitekeys">
    <div class="widefat">
        <form action="" method="post">
            <table id="wookeys">
                <tbody>
                    <tr>
                        <th><label for="parent_site_url">Parent URL</label></th>
                        <td><input type="text" name="parent_site_url" id="parent_site_url" value="<?php echo get_option('mobilitybuy_parent_site_url','') ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="consumar_key">Consumer key</label></th>
                        <td><input type="text" name="consumar_key" id="consumar_key" value="<?php echo get_option('mobilitybuy_consumar_key','') ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="consumer_secret">Consumer secret</label></th>
                        <td><input type="text" name="consumer_secret" id="consumer_secret" value="<?php echo get_option('mobilitybuy_consumer_secret','') ?>"></td>
                    </tr>
                    <tr>
                        <th>
                        <button name="keys_save" class="button button-secondary">Save</button>
                        </th>
                    </tr>
                </tbody>
            </table>
            
        </form>
    </div>
</div>