<?php
/*
Plugin Name: email comment notifications to editors
Plugin URI: https://github.com/robertkristie
Description: This is the custom email management tool
Version: 1.0
Author: Rob Kristie
Author URI: https://github.com/robertkristie
*/

/*
	Emails for when a comment is put in moderation for editors of that category
*/
function moderation($comment_id) {
	global $wpdb;
	//$mna_settings = mna_read_options();
	//if( !$mna_settings[moderate_author] ) return true; // Exit if option disabled
	$comment = get_comment($comment_id);
	if( '0' != $comment->comment_approved ) return true; // Exit if comment is approved	
	$post = get_post($comment->comment_post_ID); 
	$authorid = $post->post_author;	
	$authorname = get_the_author_meta( 'display_name', $authorid );   
	$category = get_the_category( $post );
	$siteurl = get_option('siteurl');
	$catid = $category[0]->cat_ID;
	$catname = $category[0]->cat_name;
	$catvalue = '"' . $catid . '"';
	$myrows = $wpdb->get_results( "SELECT * FROM wp_usermeta WHERE meta_key = 'user_cats' AND meta_value LIKE '%$catvalue%'" );
	$editors = array();	
	foreach ($myrows as $myrow) {
		$user_id = $myrow->user_id;
		$userlevel1 = 'wp_user_level';
  		$single = true;
  		$user_roler = get_user_meta( $user_id, $userlevel1, $single );  	
  		if ($user_roler >6){
  			$user_info = get_userdata($user_id);
  			$editoremail = $user_info ->user_email;
  			$editors[] = $editoremail; 
  		}
	}
	$admins = get_users('role=administrator');
	foreach ($admins as $admin) {
		$user_id = $admin->ID;
  		$key = 'admin_email';
  		$single = true;
  		$user_admin = get_user_meta( $user_id, $key, $single ); 
  		if ($user_admin =='true') {
  			$user_info = get_userdata($user_id);
  			$editoremail = $user_info ->user_email;
  			$editors[] = $editoremail;  		
  		}		
	}
	$emails = implode(", ",$editors);
	

	$notify_message = sprintf( __('A new comment on the post #%1$s "%2$s" is waiting for your approval'), $post->ID, $post->post_title ) . "\r\n";
	$notify_message .= sprintf( __('Post Author : %s'), $authorname ) . "\r\n";
	$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
	$notify_message .= sprintf( __('Author : %1$s '), $comment->comment_author) . "\r\n";
	$notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "\r\n";
	$notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "\r\n";
	$notify_message .= __('Comment: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
	$notify_message .= sprintf( __('Approve it: %s'),  "http://blogs.cisco.com/wp-admin/comment.php?action=mac&c=$comment_id" ) . "\r\n";
	$notify_message .= sprintf( __('Delete it: %s'), "http://blogs.cisco.com/wp-admin/comment.php?action=cdc&c=$comment_id" ) . "\r\n";
	$notify_message .= sprintf( __('Spam it: %s'), "http://blogs.cisco.com/wp-admin/comment.php?action=cdc&dt=spam&c=$comment_id" ) . "\r\n";
	

	$subject = 'A comment has been posted and needs moderation in the ' . $catname . ' blog';


	$notify_message = apply_filters('comment_moderation_text', $notify_message, $comment_id);
	$subject = apply_filters('comment_moderation_subject', $subject, $comment_id);

	@wp_mail($emails, $subject, $notify_message);

	return true;
}

add_action('comment_post', 'moderation');

/*
	emails for when a comment in moderation is approved
*/


function approved_alert($comment) {
	global $wpdb;
	$comment2 = get_comment($comment);
	$comment_id = $comment2->comment_ID;
	$commentid = $comment2->comment_post_ID;
	$post = get_post($comment->comment_post_ID); 
	$authorid = $post->post_author;	
	$authorname = get_the_author_meta( 'display_name', $authorid );  
    $category = get_the_category( $commentid  );
    $catid = $category[0]->cat_ID;
    $catname = $category[0]->cat_name;
    $catvalue = '"' . $catid . '"';
	$myrows = $wpdb->get_results( "SELECT * FROM wp_usermeta WHERE meta_key = 'user_cats' AND meta_value LIKE '%$catvalue%'" );
	$editors = array();	
	
	foreach ($myrows as $myrow) {
		$user_id = $myrow->user_id;
		$userlevel1 = 'wp_user_level';
  		$single = true;
  		$user_roler = get_user_meta( $user_id, $userlevel1, $single );  	
  		if ($user_roler >6){
  			$user_info = get_userdata($user_id);
  			$editoremail = $user_info ->user_email;
  			$editors[] = $editoremail; 
  		}
	}

	$admins = get_users('role=administrator');
	foreach ($admins as $admin) {
		$user_id = $admin->ID;
  		$key = 'admin_email';
  		$single = true;
  		$user_admin = get_user_meta( $user_id, $key, $single ); 
  		if ($user_admin =='true') {
  			$user_info = get_userdata($user_id);
  			$editoremail = $user_info ->user_email;
  			$editors[] = $editoremail;  		
  		}		
	}
	$emails = implode(", ",$editors);
	$notify_message = sprintf( __('A comment on the post #%1$s "%2$s" has been approved'), $post->ID, $post->post_title ) . "\r\n";
	$notify_message .= sprintf( __('Post Author : %s'), $authorname ) . "\r\n";
	$notify_message .= get_permalink($comment2->comment_post_ID) . "\r\n\r\n";
	$notify_message .= sprintf( __('Author : %1$s '), $comment2->comment_author) . "\r\n";
	$notify_message .= sprintf( __('E-mail : %s'), $comment2->comment_author_email ) . "\r\n";
	$notify_message .= sprintf( __('URL    : %s'), $comment2->comment_author_url ) . "\r\n";
	$notify_message .= __('Comment: ') . "\r\n" . $comment2->comment_content . "\r\n\r\n";
	$notify_message .= sprintf( __('Delete it: %s'), "http://blogs.cisco.com/wp-admin/comment.php?action=cdc&c=$comment_id" ) . "\r\n";
	$notify_message .= sprintf( __('Spam it: %s'), "http://blogs.cisco.com/wp-admin/comment.php?action=cdc&dt=spam&c=$comment_id" ) . "\r\n";
	$subject = 'A comment has been approved in the ' . $catname . ' blog';
	@wp_mail($emails, $subject, $notify_message);   	
}

add_action('comment_unapproved_to_approved', 'approved_alert');

/*
	emails for when a comment is auto approved
*/

function comment_alert($comment) {
	global $wpdb;
	$comment2 = get_comment($comment);
	$approved = ($comment2->comment_approved);
	$commentid = $comment2->comment_post_ID;
	$comment_id = $comment2->comment_ID;
	$post = get_post($comment2->comment_post_ID); 
	$authorid = $post->post_author;	
	$authorname = get_the_author_meta( 'display_name', $authorid );  
    $category = get_the_category( $commentid);
    $catid = $category[0]->cat_ID;
    $catname = $category[0]->cat_name;
    $catvalue = '"' . $catid . '"';
	$myrows = $wpdb->get_results( "SELECT * FROM wp_usermeta WHERE meta_key = 'user_cats' AND meta_value LIKE '%$catvalue%'" );
	$editors = array();	
	
	if ($approved ==1){
		foreach ($myrows as $myrow) {
			$user_id = $myrow->user_id;
			$userlevel1 = 'wp_user_level';
  			$single = true;
  			$user_roler = get_user_meta( $user_id, $userlevel1, $single );  	
  			if ($user_roler >6){
  				$user_info = get_userdata($user_id);
  				$editoremail = $user_info ->user_email;
  				$editors[] = $editoremail; 
  			}
		}

		$admins = get_users('role=administrator');
		foreach ($admins as $admin) {
			$user_id = $admin->ID;
  			$key = 'admin_email';
  			$single = true;
  			$user_admin = get_user_meta( $user_id, $key, $single ); 
  			if ($user_admin =='true') {
  				$user_info = get_userdata($user_id);
  				$editoremail = $user_info ->user_email;
  				$editors[] = $editoremail;  		
  			}		
		}
		$emails = implode(", ",$editors);
		$notify_message = sprintf( __('A comment on the post #%1$s "%2$s" has been auto approved'), $post->ID, $post->post_title ) . "\r\n";
		$notify_message .= sprintf( __('Post Author : %s'), $authorname ) . "\r\n";
		$notify_message .= get_permalink($comment2->comment_post_ID) . "\r\n\r\n";
		$notify_message .= sprintf( __('Author : %1$s '), $comment2->comment_author) . "\r\n";
		$notify_message .= sprintf( __('E-mail : %s'), $comment2->comment_author_email ) . "\r\n";
		$notify_message .= sprintf( __('URL    : %s'), $comment2->comment_author_url ) . "\r\n";
		$notify_message .= __('Comment: ') . "\r\n" . $comment2->comment_content . "\r\n\r\n";
		$notify_message .= sprintf( __('Delete it: %s'), "http://blogs.cisco.com/wp-admin/comment.php?action=cdc&c=$comment_id" ) . "\r\n";
		$notify_message .= sprintf( __('Spam it: %s'), "http://blogs.cisco.com/wp-admin/comment.php?action=cdc&dt=spam&c=$comment_id" ) . "\r\n";
		$subject = 'A comment has been posted and auto approved in the ' . $catname . ' blog';

		@wp_mail($emails, $subject, $notify_message);
	}	
}

add_action ('comment_post', 'comment_alert');


/*
	Adds settings page to select admins who want t0 receive all emails
*/

add_action( 'admin_menu', 'email_menu' );

function email_menu() {
	add_users_page( 'Admins to recieve emails', 'Admins', 'manage_options', 'admins', 'email_options' );
}
function email_options(){
	$admins = get_users('role=administrator');
	echo '<p>Select the admins you wish to receive comment notification emails</p>'; ?>
	<ul>
		<?php foreach( $admins as $admin) {
			echo '<li>';
				$user_id = $admin->ID;
  				$key = 'admin_email';
  				$single = true;
  				$user_admin = get_user_meta( $user_id, $key, $single ); 
				?>
				<input value="true" name="admin_email" type="checkbox">
					<?php if ($user_admin =='true') { ?> checked="checked"<?php  } ?>
				</input>
				<?php echo $admin->first_name; echo " "; echo $admin->last_name;
			echo '</li>';
		} ?>
	</ul>
<?php
}
?>
