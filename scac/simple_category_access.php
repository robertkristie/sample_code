<?php
/*
Plugin Name: Simple Category Access Control
Plugin URI: https://github.com/robertkristie
Description: Author category access.
Version: 1.0
Author: Rob Kristie
Author URI: https://github.com/robertkristie
*/

/*
	Shows checkboxes for each category an admin can assign an author or editor to
*/
add_action( 'show_user_profile', 'show_category_fields' );
add_action( 'edit_user_profile', 'show_category_fields');
 
function show_category_fields( $user ) { 
	if (current_user_can('administrator')){?>
 		<table class="form-table">
			<tr>
				<th><label for="user_cats">Categories</label></th>
				<td>
					<?php $user_cats = get_the_author_meta( 'user_cats', $user->ID ); ?>
					<ul>
						<?php 
						$categories = get_categories('orderby=name');
						foreach ($categories as $category){ ?>	
							<li><input value="<?php echo $category->cat_ID; ?>" name="user_cats[]" <?php if (is_array($user_cats)) { if (in_array($category->cat_ID,$user_cats)) { ?>checked="checked"<?php } }?> type="checkbox" /> <?php echo $category->cat_name ?>
							</li>
						<?php } ?>
					</ul>
				</td>			
			</tr>
 		</table>
	<?php }
	else { ?>
		<div style = "display:none;visibility:hidden;">
			<table class="form-table">
				<tr>
					<th><label for="user_cats">Categories</label></th>
					<td>
						<?php $user_cats = get_the_author_meta( 'user_cats', $user->ID ); ?>
						<ul>
							<?php 
							$categories = get_categories('orderby=name');
							foreach ($categories as $category){ ?>	
							<li><input value="<?php echo $category->cat_ID; ?>" name="user_cats[]" <?php if (is_array($user_cats)) { if (in_array($category->cat_ID,$user_cats)) { ?>checked="checked"<?php } }?> type="checkbox" /> <?php echo $category->cat_name ?>
							</li>
							<?php } ?>
						</ul>
					</td>			
				</tr>
 			</table>
 		</div>
	<?php
	}
}

/*
	Saves categories allowed for user in user_meta
*/
 
add_action( 'personal_options_update', 'save_category_fields' );
add_action( 'edit_user_profile_update', 'save_category_fields' );
 
function save_category_fields( $user_id ) {
	if ( !current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}
	update_usermeta( $user_id, 'user_cats', $_POST['user_cats'] );
}
 

/*
 	Show authors only their posts
 */

function posts_for_current_author($query) {
	global $user_level;

	if($query->is_admin && $user_level < 5) {
		global $user_ID;
		$query->set('author',  $user_ID);
		unset($user_ID);
	}
	unset($user_level);

	return $query;
}
add_filter('pre_get_posts', 'posts_for_current_author');


/*
	Show only the Comments MADE BY the current logged user and the Comments MADE TO his/hers posts or to categories current user manages
 
 */
function comment_list_by_user($clauses) {

	global $user_ID; 
	get_currentuserinfo();
	if (current_user_can('author')||current_user_can('contributor')) {
		
			global $user_ID, $wpdb;
			$clauses['join'] = ", wp_posts";
			$clauses['where'] .= " AND wp_posts.post_author = ".$user_ID." AND wp_comments.comment_post_ID = wp_posts.ID";		
		return $clauses;
	}
	else {
		
			global $wpdb;
			$clauses['join'] = ", wp_posts";
			$clauses['where'] .= " AND wp_comments.comment_post_ID = wp_posts.ID";
			return $clauses;
		
		
	}

};

if (is_admin()) {
add_filter('comments_clauses', 'comment_list_by_user');
}


/*
	Hide all categories with CSS, show allowed categories
*/
function hide_the_categories(){

	if( is_admin() ) {
    	global $pagenow;
     	if( 'edit.php' == $pagenow || 'post-new.php' == $pagenow || 'post.php' == $pagenow) {
    		if( current_user_can('author') || current_user_can('editor') || current_user_can('contributor') ) {
    			?>
    			<style type="text/css">
        			ul#categorychecklist li, ul#categorychecklist-pop li{
            		display:none;
        			}
    			</style>
				<?php
			}
			global $wp_roles;
    		$current_user = wp_get_current_user();
    		//$roles = $current_user->roles;
    		//$role = array_shift($roles);
    		$user_id =$current_user->ID;
    		$key = "user_cats";
    		$single = "false";
    		$usercats = get_user_meta($user_id, $key, $single);
    		if (!empty($usercats)) {
    			foreach ($usercats as $usercat) {
    			echo '<style type="text/css">';
    			echo 'ul#categorychecklist li#category-' . $usercat . '{';
    			echo 'display:block;';
    			echo '}';
    			echo '</style>';
			}	 
		}
       
    }
}


	
}

add_action( "admin_head", "hide_the_categories" );


/*
	Admin page to see which user is assigned to which categories
*/

add_action( 'admin_menu', 'roles_menu' );

function roles_menu() {
	add_users_page( 'Roles By Category', 'Roles', 'manage_options', 'roles', 'roles_options' );
}

function roles_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div>';
		$args=array(
  			'orderby' => 'name',
  			'order' => 'ASC'
  		);
		$categories=get_categories($args);
  		foreach($categories as $category) {
  			echo '<h3>'; 
  			echo $category->name;
  			echo '</h3>';
  			$cat_id = $category->cat_ID;
  			$catvalue = '"' . $cat_id . '"';
  			//echo $catvalue;
  			global $wpdb;
  			$myrows = $wpdb->get_results( "SELECT * FROM wp_usermeta WHERE meta_key = 'user_cats' AND meta_value LIKE '%$catvalue%'" );
  			$authors = array();
			$editors = array();	
			//echo $cat_id;  			
  			foreach ($myrows as $myrow) {
				$user_id = $myrow->user_id;
				$lastname = 'last_name';
				$firstname = 'first_name';
				$userlevel1 = 'wp_user_level';
  				$single = true;
  				$user_first = get_user_meta( $user_id, $firstname, $single );
  				$user_last = get_user_meta( $user_id, $lastname, $single );
  				$user_roler = get_user_meta( $user_id, $userlevel1, $single );
  				$username = $user_first . " " . $user_last;
  				//echo $username;
  				if ($user_roler >6){
  					$editors[] = $username; 
  				}
  				else {
  					$authors[] = $username;
  				}
			}
			echo '<b>Editors</b>';
			echo '<br />';
			echo implode(", ",$editors);
			echo '<br />';
			echo '<b>Authors</b>';
			echo '<br />';
			echo implode(", ",$authors);
			echo '<hr>';
  		}
	echo '</div>';
}

/*
	Auto checks checkbox for first category available to user
*/
add_action( 'admin_head-post.php', 'disable_and_check_cat' );
add_action( 'admin_head-post-new.php', 'disable_and_check_cat' );


function disable_and_check_cat() {
	if( current_user_can('author') || current_user_can('editor') || current_user_can('contributor') ) {
		global $wp_roles;
    	$current_user = wp_get_current_user();
    	$user_id =$current_user->ID;
    	$key = "user_cats";
    	$single = "false";
    	$usercats = get_user_meta($user_id, $key, $single);
    	foreach ($usercats as $usercat) {
		?>
			<script type="text/javascript">
				var cat = <?php echo $usercat;?>;
				jQuery(document).ready(function($){
					var required_cat = $('input#in-category-' + cat + '');
					if( !required_cat.attr('checked')  )
						required_cat.attr('checked','checked');
				});
			</script>
		<?php
		}
	}
}

?>