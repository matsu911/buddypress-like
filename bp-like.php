<?php
/*
Plugin Name: BuddyPress Like
Plugin URI: http://bplike.wordpress.com
Description: Gives users of a BuddyPress site the ability to 'like' activities, and soon other social elements of the site.
Author: Alex Hempton-Smith
Version: 0.0.1
Author URI: http://www.alexhemptonsmith.com
*/

function bplike_process_ajax() {
  if ( isset( $_POST['type'] ) ) {
    add_action( 'wp', 'bplike_process_activity_like' );
  }
}
add_action('init', 'bplike_process_ajax');

function bplike_process_activity_like() {

	if ( $_POST['type'] == 'like' )
		bplike_activity_add_user_like( (int) str_replace( 'like-activity-', '', $_POST['id'] ) );
	
	if ( $_POST['type'] == 'unlike' )
		bplike_activity_remove_user_like( (int) str_replace( 'unlike-activity-', '', $_POST['id'] ) );

	if ( $_POST['type'] == 'view-likes' )
		bplike_activity_get_user_likes( (int) str_replace( 'view-likes-', '', $_POST['id'] ) );

	die();
}

function bplike_activity_add_user_like( $activity_id, $user_id = false ) {
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

function bplike_activity_remove_user_like( $activity_id, $user_id = false ) {
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

function bplike_activity_get_user_likes( $activity_id, $user_id = false ) {
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

function bplike_get_activity_is_liked( $activity_id = false, $user_id = false ) {
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

function bplike_activity_button() {
	$activity = bp_activity_get_specific( array( 'activity_ids' => bp_get_activity_id() ) );
	$activity_type = $activity['activities'][0]->type;

	if ( is_user_logged_in() && $activity_type !== 'activity_liked' ) :
	if (bp_activity_get_meta( bp_get_activity_id(), 'liked_count' )) {
		$users_who_like = array_keys(bp_activity_get_meta( bp_get_activity_id(), 'liked_count' ));
		$liked_count = count($users_who_like);
	}
		if ( !bplike_get_activity_is_liked() ) : ?>
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
add_filter('bp_activity_entry_meta', 'bplike_activity_button');

/* Insert the Javascript */
function bplike_list_scripts ( ) {
  wp_enqueue_script( "bp-like", path_join(WP_PLUGIN_URL, basename( dirname( __FILE__ ) )."/bp-like.min.js"), array( 'jquery' ) );
}
add_action('wp_print_scripts', 'bplike_list_scripts');

/* Insert the CSS - a workaround until we can disable metadata on specific activities */
function bplike_css() {
?>
<style type="text/css">
	.bp-like.activity_liked .activity-meta, .bp-like.activity_liked a.view { display: none; }
	.users-who-like { display:none; padding-top: 14px; }
</style>
<?php	
}
add_action('wp_head', 'bplike_css');