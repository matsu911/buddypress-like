<?php
/*
Plugin Name: BuddyPress Like
Plugin URI: http://bplike.wordpress.com
Description: Gives users of a BuddyPress site the ability to 'like' activities, and soon other social elements of the site.
Author: Alex Hempton-Smith
Version: 0.0.5
Author URI: http://www.alexhemptonsmith.com
*/

/*** Make sure BuddyPress is loaded ********************************/
if ( !function_exists( 'bp_core_install' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	if ( is_plugin_active( 'buddypress/bp-loader.php' ) )
		require_once ( WP_PLUGIN_DIR . '/buddypress/bp-loader.php' );
	else
		return;
}
/*******************************************************************/

if ( !defined( 'BP_LIKE_SLUG' ) )
	define ( 'BP_LIKE_SLUG', 'like' );

define ( 'BP_LIKE_VERSION', '0.0.5' );
define ( 'BP_LIKE_DB_VERSION', '3' );

/**
 * bp_like_install()
 *
 * Installs or upgrades the database content
 *
 */
function bp_like_install() {

	$default_settings = array(
		'likers_visibility' => 'friends_names_others_numbers'
	);

	if ( get_site_option('bp-like-db-version') )
		delete_site_option('bp-like-db-version', BP_LIKE_DB_VERSION );

	if ( !get_site_option('bp_like_db_version') || get_site_option('bp_like_db_version') < BP_LIKE_DB_VERSION )
		update_site_option('bp_like_db_version', BP_LIKE_DB_VERSION );

	if ( !get_site_option('bp_like_settings') )
		update_site_option('bp_like_settings', $default_settings );
	
	add_action( 'admin_notices', 'bp_like_upgrade_notice' );
}

function bp_like_upgrade_notice() {
	if ( !is_site_admin() )
		return false;

	echo '<div id="message" class="updated fade bp-like-upgraded"><p style="line-height: 150%"><strong>BuddyPress Like</strong> has been successfully upgraded to version ' . BP_LIKE_VERSION . '.</p></div>';
}

/**
 * bp_like_check_installed()
 *
 * Checks to see if the DB tables exist or if you are running an old version
 * of the component. If it matches, it will run the installation function.
 *
 */
function bp_like_check_installed() {
	global $wpdb;

	if ( !is_site_admin() )
		return false;

	if ( !get_site_option('bp_like_settings') || get_site_option('bp-like-db-version') )
		bp_like_install();

	if ( get_site_option('bp_like_db_version') < BP_LIKE_DB_VERSION )
		bp_like_install();
}
add_action( 'admin_menu', 'bp_like_check_installed' );

/**
 * bp_like_get_settings()
 *
 * Returns settings from the database
 *
 */
function bp_like_get_settings( $option = false ) {
	
	$settings = get_site_option('bp_like_settings');
	
	if (!$option)
		return $settings;
		
	else
		return $settings[$option];
}

/**
 * bp_like_process_ajax()
 *
 * Runs the relevant function depending on what Ajax call has been made.
 *
 */
function bp_like_process_ajax() {
	global $bp;

	$id = preg_replace("/\D/","",$_POST['id']); 
	
	if ( $_POST['type'] == 'like' )
		bp_like_activity_add_user_like( $id );
	
	if ( $_POST['type'] == 'unlike' )
		bp_like_activity_remove_user_like( $id );

	if ( $_POST['type'] == 'view-likes' )
		echo bp_like_get_activity_likes( $id );

	die();
}
add_action( 'wp_ajax_activity_like', 'bp_like_process_ajax' );

/**
 * bp_like_activity_add_user_like()
 *
 * Registers that the user likes a given activity item.
 *
 */
function bp_like_activity_add_user_like( $activity_id, $user_id = false ) {
	global $bp;
	
	if (!$activity_id)
		return false;

	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;
	
	if ( $user_id == 0 ) {
		_e('You must be logged in.');
		return false;
	}
	
	/* Add to the users liked activities. */
	$user_likes = get_usermeta( $user_id, 'bp_liked_activities' );
	$user_likes[$activity_id] = 'activity_liked';
	update_usermeta( $user_id, 'bp_liked_activities', $user_likes );
	
	/* Add to the total likes for this activity. */
	$users_who_like = bp_activity_get_meta( $activity_id, 'liked_count' );
	$users_who_like[$user_id] = 'user_likes';
	bp_activity_update_meta( $activity_id, 'liked_count', $users_who_like );
	
	$liked_count = count($users_who_like);
	
	/* Add an update to their activity stream saying they liked this activity */
	$activity = bp_activity_get_specific( array( 'activity_ids' => $activity_id, 'component' => 'bp-like' ) );
	$author_id = $activity['activities'][0]->user_id;
	
	if ($user_id == $author_id)
		$author = 'their own';
	elseif ($user_id == 0)
		$author = 'an';
	else
		$author = bp_core_get_userlink( $author_id ) . "'s";

	$action = bp_core_get_userlink( $user_id ) . ' likes ' . $author . ' <a href="' . bp_activity_get_permalink($activity_id) . '">activity</a>';
	
	bp_activity_add( array( 'action' => $action, 'component' => 'bp-like', 'type' => 'activity_liked', 'user_id' => $user_id, 'item_id' => $activity_id ) );

	echo 'Unlike';
	if ($liked_count)
		echo ' (' . $liked_count . ')';
}

/**
 * bp_like_activity_remove_user_like()
 *
 * Registers that the user has unliked a given activity item.
 *
 */
function bp_like_activity_remove_user_like( $activity_id, $user_id = false ) {
	global $bp;
	
	if ( !$activity_id )
		return false;

	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;
	
	if ( $user_id == 0 ) {
		echo 'You must be logged in.';
		return false;
	}

	/* Remove this from the users liked activities. */
	$user_likes = get_usermeta( $user_id, 'bp_liked_activities' );
	unset( $user_likes[$activity_id] );
	update_usermeta( $user_id, 'bp_liked_activities', $user_likes );

	/* Update the total number of users who have liked this activity. */
	$users_who_like = bp_activity_get_meta( $activity_id, 'liked_count' );
	unset( $users_who_like[$user_id] );
	bp_activity_update_meta( $activity_id, 'liked_count', $users_who_like );
	
	$liked_count = count($users_who_like);

	/* Remove the update on the users profile from when they liked the activity. */
	$update_id = bp_activity_get_activity_id( array( 'item_id' => $activity_id, 'component' => 'bp-like' ) );
	bp_activity_delete( array( 'id' => $update_id, 'component' => 'bp-like' ) );
	
	$activity = bp_activity_get_specific( array( 'activity_ids' => $activity_id, 'component' => 'bp-like' ) );
	$author_id = $activity['activities'][0]->user_id;

	echo 'Like';
	if ($liked_count)
		echo ' (' . $liked_count . ')';
}

/**
 * bp_like_get_activity_likes()
 *
 * Outputs a list of users who have liked a given activity.
 *
 */
function bp_like_get_activity_likes( $activity_id = false, $user_id = false ) {
	global $bp;

	if ( !$activity_id ) 
		return false;
		
	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;

	if ( !bp_activity_get_meta( $activity_id, 'liked_count' ) )
		return false;

	$users_who_like = array_keys(bp_activity_get_meta( $activity_id, 'liked_count' ));
	$liked_count = count(bp_activity_get_meta( $activity_id, 'liked_count' ));
	$users_friends = friends_get_friend_user_ids($user_id);
	
	if (!empty($users_friends))
		$friends_who_like = array_intersect($users_who_like, $users_friends);
	
	$non_friends_who_like = $liked_count-count($friends_who_like);
	
	if (bp_like_get_activity_is_liked($activity_id, $user_id))
		$non_friends_who_like = $non_friends_who_like-1;

		if ( bp_like_get_settings('likers_visibility') == 'show_all' ) :
			
			if ( $liked_count == 1 && bp_like_get_activity_is_liked($activity_id, $user_id)) :
			
				$output .= 'You are the only person who likes this so far.';
			
			else :
			
				if (bp_like_get_activity_is_liked($activity_id, $user_id)) {
					$liked_count = $liked_count-1;
					$output .= 'You and ';
				}
			
				$output .= $liked_count;

				if (bp_like_get_activity_is_liked($activity_id, $user_id))
					$output .= ' other';

				if ($liked_count == 1) { 
					$output .= ' person likes';
				} else {
					$output .= ' people like';
				};
				$output .= ' this &middot '; 
	
				foreach($users_who_like as $user):
						if ($user_id == $user)
							$output .= '';
						else
							$output .= bp_core_get_userlink($user) . ' &middot ';
				endforeach;

			endif;

		elseif ( bp_like_get_settings('likers_visibility') == 'friends_names_others_numbers' ) :

			if ( !empty( $friends_who_like ) ) :

				if (bp_like_get_activity_is_liked($activity_id, $user_id))
					$output .= '<a href="' . bp_core_get_user_domain($user_id) . '" title="' . bp_core_get_user_displayname($user_id) . '">You</a> &middot ';

				foreach($friends_who_like as $friend) :
					$output .= bp_core_get_userlink($friend) . ' &middot ';
				endforeach;

				if ($non_friends_who_like) :

					$output .= ' and ' . $non_friends_who_like . ' other';

					if ($non_friends_who_like == 1)
						$output .= ' person';
					else
						$output .= ' people';
				
					$output .= ' like this.';
				
				else :

					if (count($friends_who_like) == 1 && !bp_like_get_activity_is_liked($activity_id, $user_id))
						$output .= ' likes this.';
					elseif (count($friends_who_like) == 1 && bp_like_get_activity_is_liked($activity_id, $user_id))
						$output .= ' like this.';
					else
						$output .= ' like this.';

				endif;

			elseif ( !count($friends_who_like) && $liked_count == 1 && bp_like_get_activity_is_liked($activity_id, $user_id)) :
			
				$output = 'You are the only person who likes this so far.';

			elseif (empty( $friends_who_like ) && $liked_count > 0) :
				
				if (bp_like_get_activity_is_liked($activity_id, $user_id))
					$liked_count = $liked_count-1;

				$output = 'None of your friends like this yet, but ';
				if (bp_like_get_activity_is_liked($activity_id, $user_id))
					$output .= ' you and ';
				$output .= $liked_count.' other';
				
				if ( $liked_count == 1 ) :
					$output .= ' person ';
					if (bp_like_get_activity_is_liked($activity_id, $user_id))
						$output .= 'do.';
					else
						$output .= 'does.';
				else :
					$output .= ' people do.';
				endif;
			endif;

		elseif ( bp_like_get_settings('likers_visibility') == 'just_numbers' ) :

			if ($liked_count == 1 && !bp_like_get_activity_is_liked($activity_id, $user_id)) :
				$output .= '1 person likes this.';
			elseif ($liked_count > 1 && !bp_like_get_activity_is_liked($activity_id, $user_id)) :
				$output .= $liked_count.' people like this.';
			elseif ($liked_count == 1 && bp_like_get_activity_is_liked($activity_id, $user_id)) :
				$output = 'You are the only person who likes this so far.';
			elseif ($liked_count == 2 && bp_like_get_activity_is_liked($activity_id, $user_id)) :
				$output = 'You and 1 other person like this.';
			elseif ($liked_count > 2 && bp_like_get_activity_is_liked($activity_id, $user_id)) :
				$liked_count = $liked_count-1;
				$output = 'You and ' . $liked_count . ' other people like this.';
			endif;

		endif;

	return $output;
}

/**
 * bp_like_get_activity_is_liked()
 *
 * Checks to see whether the user has liked a given activity.
 *
 */
function bp_like_get_activity_is_liked( $activity_id = false, $user_id = false ) {
	global $bp;

	if (!$activity_id)
		$activity_id = bp_get_activity_id();
	
	if (!$user_id)
		$user_id = $bp->loggedin_user->id;

	$user_likes = get_usermeta( $bp->loggedin_user->id, 'bp_liked_activities' );
	
	if (!$user_likes){
		return false;
	} elseif (!array_key_exists($activity_id, $user_likes)) {
		return false;
	} else {
		return true;
	};
}

/**
 * bp_like_activity_button()
 *
 * Inserts the 'Like/Unlike' and 'View likes/Hide likes' buttons into activities.
 *
 */
function bp_like_activity_button() {
	$activity = bp_activity_get_specific( array( 'activity_ids' => bp_get_activity_id() ) );
	$activity_type = $activity['activities'][0]->type;
	
	if ( is_user_logged_in() && $activity_type !== 'activity_liked' ) :
	if (bp_activity_get_meta( bp_get_activity_id(), 'liked_count' )) {
		$users_who_like = array_keys(bp_activity_get_meta( bp_get_activity_id(), 'liked_count' ));
		$liked_count = count($users_who_like);
	}
		if ( !bp_like_get_activity_is_liked() ) : ?>
		<a href="" class="like" id="like-activity-<?php bp_activity_id(); ?>" title="<?php _e( 'Like this item', 'buddypress' ) ?>"><?php _e( 'Like', 'buddypress' ); if ($liked_count) echo ' (' . $liked_count . ')'; ?></a>
				<?php else : ?>
		<a href="" class="unlike" id="unlike-activity-<?php bp_activity_id(); ?>" title="<?php _e( 'Unlike this item', 'buddypress' ) ?>"><?php _e( 'Unlike', 'buddypress' ); if ($liked_count) echo ' (' . $liked_count . ')'; ?></a>
		<?php endif;
		if ($users_who_like): ?>
		<a href="" class="view-likes" id="view-likes-<?php bp_activity_id(); ?>">View likes</a>
		<p class="users-who-like" id="users-who-like-<?php bp_activity_id(); ?>"></p>
		<?php
		endif;
	endif;
};
add_filter('bp_activity_entry_meta', 'bp_like_activity_button');

/**
 * bp_like_activity_filter()
 *
 * Adds 'Show Activity Likes' to activity stream filters.
 *
 */
function bp_like_activity_filter() {
	echo '<option value="activity_liked">Show Activity Likes</option>';
}
add_action('bp_activity_filter_settings', 'bp_like_activity_filter');
add_action('bp_member_activity_filter_settings', 'bp_like_activity_filter');
add_action('bp_group_activity_filter_settings', 'bp_like_activity_filter');

/**
 * bp_like_list_scripts()
 *
 * Includes the Javascript required for Ajax etc.
 *
 */
function bp_like_list_scripts() {
  wp_enqueue_script( "bp-like", path_join(WP_PLUGIN_URL, basename( dirname( __FILE__ ) )."/_inc/bp-like.dev.js"), array( 'jquery' ) );
}
add_action('wp_print_scripts', 'bp_like_list_scripts');

/**
 * bp_like_insert_head()
 *
 * Includes any CSS and/or Javascript needed in the <head>.
 *
 */
function bp_like_insert_head() {
?>
<style type="text/css">
	.bp-like.activity_liked .activity-meta, .bp-like.activity_liked a.view, .users-who-like { display: none; }
	
	/* For the default theme */
	#bp-default .users-who-like {
		margin: 10px 0 -10px 0;
		background: #eee;
		border-bottom: 1px solid #ddd;
		border-right: 1px solid #ddd;
		-moz-border-radius: 4px;
		-webkit-border-radius: 4px;
		border-radius: 4px;
		padding: 8px 8px 8px 12px;
		color: #888;
	}
	#bp-default .users-who-like a { color: #777; padding: 0; background: none; border: none; text-decoration: underline; font-size: 12px; }
	#bp-default .users-who-like a:hover { color: #222; }
	
</style>
<script type="text/javascript">
	var bp_like_terms_like = 'Like';
	var bp_like_terms_like_message = 'Like this item';
	var bp_like_terms_unlike_message = 'Unlike this item';
	var bp_like_terms_view_likes = 'View likes';
	var bp_like_terms_hide_likes = 'Hide likes';
	var bp_like_terms_unlike_1 = 'Unlike (1)';
</script>
<?php	
}
add_action('wp_head', 'bp_like_insert_head');

/**
 * bp_like_add_admin_page_menu()
 *
 * Adds "BuddyPress Like" to the main BuddyPress admin menu.
 *
 */
function bp_like_add_admin_page_menu() {
    add_submenu_page('bp-general-settings', 'BuddyPress Like', 'BuddyPress Like', 'manage_options', 'bp-like-settings', 'bp_like_admin_page');
}
add_action('admin_menu', 'bp_like_add_admin_page_menu');

/**
 * bp_like_admin_page()
 *
 * Outputs the admin settings page.
 *
 */
function bp_like_admin_page() {

    if( $_POST['bp_like_settings_updated'] ) {
		$likers_visibility = $_POST['bp_like_admin_likers_visibility'];
		update_site_option( 'bp_like_settings', array('likers_visibility' => $likers_visibility) );
		echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
	}

?>
<style type="text/css">
#icon-bp-like-settings {
	background: url('<?php echo plugins_url('/_inc/bp-like-icon32.png', __FILE__); ?>') no-repeat top left;
}
</style>

<div class="wrap">
  <div id="icon-bp-like-settings" class="icon32"><br /></div>
  <h2>BuddyPress Like Settings</h2>
  <form action="" method="post" id="bp-like-admin-form">
    <input type="hidden" name="bp_like_settings_updated" value="updated" />
    <h3>'View Likes' Visibility</h3>
    <p>You can choose how much information about the 'likers' of a particular item is shown;</p>
    <p>
      <input type="radio" name="bp_like_admin_likers_visibility" value="show_all" <?php if ( bp_like_get_settings('likers_visibility') == 'show_all' ) { echo 'checked="checked""'; }; ?> /> Show the name and profile link of all likers<br />
      <input type="radio" name="bp_like_admin_likers_visibility" value="friends_names_others_numbers" <?php if ( bp_like_get_settings('likers_visibility') == 'friends_names_others_numbers' ) { echo 'checked="checked""'; }; ?> /> Show the names of friends, and the number of non-friends<br />
      <input type="radio" name="bp_like_admin_likers_visibility" value="just_numbers" <?php if ( bp_like_get_settings('likers_visibility') == 'just_numbers' ) { echo 'checked="checked""'; }; ?> /> Show only the number of likers
    </p>
    <p class="submit">
      <input class="button-primary" type="submit" name="bp-like-admin-submit" id="bp-like-admin-submit" value="Save Changes"/>
    </p>
    <?php wp_nonce_field( 'bp-like-admin' ) ?>
  </form>
</div>
<?php
}