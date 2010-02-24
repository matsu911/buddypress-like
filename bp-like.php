<?php
/*
Plugin Name: BuddyPress Like
Plugin URI: http://bplike.wordpress.com
Description: Gives users of a BuddyPress site the ability to 'like' activities, and soon other social elements of the site.
Author: Alex Hempton-Smith
Version: 0.0.3
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

define ( 'BP_LIKE_IS_INSTALLED', 1 );
define ( 'BP_LIKE_VERSION', '0.0.3' );
define ( 'BP_LIKE_DB_VERSION', '1' );

/**
 * bp_like_install()
 *
 * Installs and/or upgrades the database content
 * NB: Not used yet, might be useful later on
 */
function bp_like_install() {
	update_site_option( 'bp-like-db-version', BP_LIKE_DB_VERSION );
}

/**
 * bp_like_check_installed()
 *
 * Checks to see if the DB tables exist or if you are running an old version
 * of the component. If it matches, it will run the installation function.
 */
function bp_like_check_installed() {
	global $wpdb;

	if ( !is_site_admin() )
		return false;

	if ( get_site_option('bp-like-db-version') < BP_LIKE_DB_VERSION )
		bp_like_install();

	/* They have been using a pre-release version, nuke the data */
	if ( !get_site_option('bp-like-db-version') ) {
		$wpdb->query("DELETE FROM $wpdb->bp_activity_meta WHERE meta_key = 'liked_count'");
		$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key = 'bp_liked_activities'");
	};
}
add_action( 'admin_menu', 'bp_like_check_installed' );

function bp_like_process_ajax() {
	if ( $_POST['type'] == 'like' )
		bp_like_activity_add_user_like( (int) str_replace( 'like-activity-', '', $_POST['id'] ) );
	
	if ( $_POST['type'] == 'unlike' )
		bp_like_activity_remove_user_like( (int) str_replace( 'unlike-activity-', '', $_POST['id'] ) );

	if ( $_POST['type'] == 'view-likes' )
		bp_like_activity_get_user_likes( (int) str_replace( 'view-likes-', '', $_POST['id'] ) );

	die();
}
add_action( 'wp_ajax_activity_like', 'bp_like_process_ajax' );

function bp_like_activity_add_user_like( $activity_id, $user_id = false ) {
	global $bp;
	
	if (!$activity_id)
		return false;

	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;
	
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

	$action = bp_core_get_userlink( $user_id ) . ' liked ' . $author . ' <a href="' . bp_activity_get_permalink($activity_id) . '">activity</a>';
	
	bp_activity_add( array( 'action' => $action, 'component' => 'bp-like', 'type' => 'activity_liked', 'user_id' => $user_id, 'item_id' => $activity_id ) );

	echo 'Unlike';
	if ($liked_count)
		echo ' (' . $liked_count . ')';
}

function bp_like_activity_remove_user_like( $activity_id, $user_id = false ) {
	global $bp;
	
	if ( !$activity_id )
		return false;

	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;

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

	echo 'Like';
	if ($liked_count)
		echo ' (' . $liked_count . ')';
}

function bp_like_activity_get_user_likes( $activity_id, $user_id = false ) {
	global $bp;

	if (!$activity_id)
		return false;
	
	if (!$user_id)
		$user_id = $bp->loggedin_user->id;
	
	if (!bp_activity_get_meta( $activity_id, 'liked_count' ))
		return false;
	
	$users_who_like = array_keys(bp_activity_get_meta( $activity_id, 'liked_count' ));
	$liked_count = count($users_who_like);
	
	echo $liked_count; if ($liked_count == 1) { echo ' person likes'; } else { echo ' people like'; }; echo ' this: '; 
	
	foreach($users_who_like as $user):
		echo bp_core_get_userlink($user) . ' ';
	endforeach;

}

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

/* Insert the Javascript */
function bp_like_list_scripts ( ) {
  wp_enqueue_script( "bp-like", path_join(WP_PLUGIN_URL, basename( dirname( __FILE__ ) )."/bp-like.min.js"), array( 'jquery' ) );
}
add_action('wp_print_scripts', 'bp_like_list_scripts');

function bp_like_header_js() {
	echo '<script type="text/javascript">var bplike_ajaxurl = "' . get_bloginfo('url') . '";</script>';
}
add_action('wp_head', 'bp_like_header_js');

/* Insert the CSS - a workaround until we can disable metadata on specific activities */
function bp_like_css() {
?>
<style type="text/css">
	.bp-like.activity_liked .activity-meta, .bp-like.activity_liked a.view { display: none; }
	.users-who-like { display:none; padding-top: 14px; }
</style>
<?php	
}
add_action('wp_head', 'bp_like_css');