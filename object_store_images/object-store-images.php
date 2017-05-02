<?php 

/*
Plugin Name: Object store images
Plugin URI: https://github.com/robertkristie
Description: Insert post images and upload to Cisco bucket.
Author: Rob Kristie
Version: 1.0
Author URI: https://github.com/robertkristie
*/

/* 
    Pseudo Amazon stuff to upload the images to bucket
*/

function send_to_bucket($attachment, $filename) {
    $aws_key = get_option('aws_key');
    $aws_secret = get_option('secret_key');
    $aws_can_id = get_option('can_id');
    $aws_can_name = get_option('can_name');
    $host = get_option('bucket_host');
    // require the amazon sdk for php library
    require_once 'aws-sdk-for-php-master/sdk.class.php';
    // Instantiate the S3 class and point it at the desired host
    $Connection = new AmazonS3(array(
        'key' => $aws_key,
        'secret' => $aws_secret,
        'canonical_id' => $aws_can_id,
        'canonical_name' => $aws_can_name,
    ));
    $Connection->set_hostname($host);
    $Connection->allow_hostname_override(false);
    $Connection->enable_path_style(true);
    $bucketname = get_option('bucket_name');

    $Connection->create_object($bucketname, $filename, array('fileUpload' => "$attachment", 'contentType'  => 'text/plain', 'https' => true, ));
    $Connection->set_object_acl($bucketname, $filename , AmazonS3::ACL_PUBLIC);
    $opt['https'] = true;   

    /*
        This is the return URL if needed
        $plans_url = $Connection->get_object_url($bucketname , $filename, 0, $opt);
    */
}
/*
    This changes WP code to replace /wp-content/uploads with the URL to the bucket for attached files
*/
add_filter('wp_get_attachment_url', 'clrs_get_attachment_url', 10, 2);

function clrs_get_attachment_url($url, $post_id) {
        $path = get_post_meta($post_id, '_wp_attached_file', true);
        $bucketname = get_option('bucket_name');
        $bucket_url = 'https://alln-extcloud-storage.cisco.com/' . $bucketname . '/' . $path;
        return $bucket_url;
}

/*
    This uploads the original file to the bucket
*/
add_action("add_attachment", 'analyze_attachment');

function analyze_attachment($attachment_ID){   

    // Get file info       
    $attachment = get_attached_file($attachment_ID);
    $filename = basename ( get_attached_file( $attachment_ID ) ); 
    // AWS stuff
    send_to_bucket($attachment, $filename);
}

/*
    This uploads each thumb file WP auto creates whenever a new image is uploaded.  It then deletes the local sized fies, and finally deletes the original uploaded image
*/
add_action('added_post_meta', 'upload_thumbnails', null, 4); 

function upload_thumbnails($meta_id, $object_id, $meta_key, $meta_value) {

    // Get file info
    if($meta_key =='_wp_attachment_metadata') {
        $upload_dir = wp_upload_dir();
        $file_dir = $upload_dir['basedir'];
        $img_sizes = $meta_value['sizes'];
        foreach($img_sizes as $img_size) {
            $filename = $img_size['file'];
            $attachment = $file_dir . '/' . $filename;

            // AWS stuff
            send_to_bucket($attachment, $filename);

            //Delete file from Server
            unlink($attachment);
                
        }

        // Delete original file 
         $og_file = basename( get_attached_file( $object_id ) ); 
         $og_path =  $file_dir . '/' . $og_file; 
         unlink($og_path); 
    }
}

/*
    This runs during the rob_added_action inside the crop-thumbnails plugin in /functions/save.php.  It runs when a image crop is saved, uploads the cropped image to the bucket, and then deletes the local file.
*/
add_action('rob_added_action', 'add_crop_filename', null, 4); 

function add_crop_filename($post_id, $_full_filepath ) {

    // Get filename and path
    $upload_dir = wp_upload_dir();
    $file_dir = $upload_dir['basedir'];
    $attachment = $_full_filepath;
    $filename = str_replace($file_dir . "/" ,"",$attachment);

    // AWS stuff
    send_to_bucket($attachment, $filename);

    // Delete file from server
    unlink($attachment);
    
}

function custom_admin_thumb_size($thumb_size){
  return array(460,230);
}
add_filter( 'admin_post_thumbnail_size', 'custom_admin_thumb_size');

/*
    filtering srcset
*/
add_filter( 'wp_calculate_image_srcset', 'dq_add_custom_image_srcset', 10, 5 );
function dq_add_custom_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ){

    $image_basename = wp_basename( $image_meta['file'] );
    $bucketname = get_option('bucket_name');
    $bucket_url = 'https://alln-extcloud-storage.cisco.com/' . $bucketname . '/';
    $srcset = '';
    foreach ( $sources as $source ) {
        $srcset .= str_replace( ' ', '%20', $bucket_url ) . ' ' . $source['value'] . $source['descriptor'] . ', ';
    }
    return rtrim( $srcset, ', ' );
}

/*
    This uploads and deletes the custom user avatars
*/

add_action( 'profile_update', 'userphoto_bucket', 10, 2 );
add_action( 'edit_user_profile_update', 'userphoto_bucket', 10, 2 );

function userphoto_bucket( $user_id ) {
    $user_full_image = get_user_meta( $user_id, 'userphoto_image_file', true );
    $user_thumb_image = get_user_meta( $user_id, 'userphoto_thumb_file', true );
    $upload_dir = wp_upload_dir();
    $file_dir = $upload_dir['basedir'];
    $upload_path =  $file_dir . '/userphoto/' . $user_full_image;
    $upload_path_thumb =  $file_dir . '/userphoto/' . $user_thumb_image;  
    if (file_exists( $upload_path )) {
        update_user_meta($user_id, 'testing_new', $user_full_image);
        $filename = 'userphoto/' . $user_full_image;
        $attachment = $upload_path;
        send_to_bucket($attachment, $filename);
        $filename2 = 'userphoto/' . $user_thumb_image;
        $attachment2 = $upload_path_thumb;
        send_to_bucket($attachment2, $filename2);
        unlink($attachment2);
        unlink($attachment);
    }
}

/*
    Options
*/

add_action( 'admin_init', function() {
    register_setting( 'object_store_group', 'aws_key' );
    register_setting( 'object_store_group', 'secret_key' );
    register_setting( 'object_store_group', 'can_id' );
    register_setting( 'object_store_group', 'can_name' );
    register_setting( 'object_store_group', 'bucket_host' );
    register_setting( 'object_store_group', 'bucket_name' );
});

add_action('admin_menu', function() {
    add_options_page( 'Object Store', 'Object Store options', 'manage_options', 'object_store_images', 'object_store_settings_page' );
});

function object_store_settings_page() { ?>
    <div>
        <h2>Object Store settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'object_store_group' ); ?>
            <table>
                <tr valign="top">
                    <td><label for="myplugin_option_name">Key</label></td>
                    <td><input type="text" id="aws_key_id" name="aws_key" value="<?php echo get_option('aws_key'); ?>" /></td>
                </tr>
                <tr valign="top">
                    <td><label for="myplugin_option_name">Secret Key</label></td>
                    <td><input type="text" id="aws_secret_key" name="secret_key" value="<?php echo get_option('secret_key'); ?>" /></td>
                </tr>
                <tr valign="top">
                    <td><label for="myplugin_option_name">Canonical ID</label></td>
                    <td><input type="text" id="aws_can_id" name="can_id" value="<?php echo get_option('can_id'); ?>" /></td>
                </tr>
                <tr valign="top">
                    <td><label for="myplugin_option_name">Canonical Name</label></td>
                    <td><input type="text" id="aws_can_name" name="can_name" value="<?php echo get_option('can_name'); ?>" /></td>
                </tr>
                <tr valign="top">
                    <td><label for="myplugin_option_name">Host</label></td>
                    <td><input type="text" id="aws_host" name="bucket_host" value="<?php echo get_option('bucket_host'); ?>" /></td>
                </tr>
                <tr valign="top">
                    <td><label for="myplugin_option_name">Bucket Name</label></td>
                    <td><input type="text" id="aws_bucket_name" name="bucket_name" value="<?php echo get_option('bucket_name'); ?>" /></td>
                </tr>
            </table>
            <?php  submit_button(); ?>
        </form>
    </div>
<?php
} 
?>