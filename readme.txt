=== Plugin Name ===
Contributors: hempsworth
Donate link: http://buddypress.org/community/groups/buddypress-like/donate/
Tags: buddypress, like, rate, thumbs
Requires at least: 2.9
Tested up to: 3.0.1
Stable tag: 0.0.8

Gives users of a BuddyPress site the ability to 'like' activities and blog posts.

== Description ==

<strong>Requires <a href="http://wordpress.org/extend/plugins/buddypress/">BuddyPress 1.2</a> or higher.</strong>

Allows users to 'Like' activities in BuddyPress, as well as blog posts.

== Installation ==

= Automatic Installation =

1. From inside your WordPress administration panel, visit 'Plugins -> Add New'
2. Search for `BuddyPress Like` and find this plugin in the results
3. Click 'Install'
4. Once installed, activate via the 'Plugins -> Installed' page

= Manual Installation =

1. Upload `buddypress-like` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

= Add the 'Like' Button to Blog Posts =
You need to edit your theme and include the following snippet within the loop to show the 'Like' button:
`<?php bp_like_button( get_the_ID(), 'blogpost' ) ?>`

If you are using the default BuddyPress theme, place that code inside the `<div class="author-box"></div>` found in `archive.php`, `index.php` and `single.php`.

== Changelog ==

= 0.0.8 =
* Adds support for liking blog posts (themes need to be edited to take advantage of this).
* Adds support for showing excerpts of the liked activity.
* Adds support for showing avatars of likers as well as their name.
* Adds the option whether to post an activity update when something is liked or not.
* Optimises the db, removing empty rows.

= 0.0.7 =
* Fixes a couple of major bugs
* Bug fixed: Posts, drafts etc would not be saved, giving error "You do not have permission to do that."
* Bug fixed: Could not save 'Likers Visibility' options from the BuddyPress Like settings screen.

= 0.0.6 =
* Fully localised.
* Adds options to customise the messages displayed to users.

= 0.0.5 =
* Fixes a bug when a user tries to view likes when they have no friends.
* Inserts the 'View likes' button if the user is the first to like an item.

= 0.0.4 =
* Adds options for the visibility of 'likers' via the admin panel.

= 0.0.3 =
* Fixed a bug affecting installs where WP isn’t in the root of the site.

= 0.0.1 =
* Initial release.

== Upgrade Notice ==

= 0.0.8 =
This is a major update which is recommended for all users; adding features which have been requested for a long time.
You can now 'Like' blog posts, show avatars of likers, and output a short excerpt of the activity that has been liked. There are also new options for administrators, as well as database optimisations.

= 0.0.7 =
Important upgrade! Fixes a couple of major bugs affecting saving posts, drafts etc (giving error "You do not have permission to do that.") and the saving of 'Likers Visibility' options

= 0.0.6 =
Now with translation support, as well as options to customise the messages displayed to users.

= 0.0.5 =
Important upgrade for 0.0.4 users! Fixes a bug when a user tries to view likes when they have no friends.

= 0.0.4 =
Upgrading allows you to choose what information about the 'likers' of an item is shown.

= 0.0.3 =
Upgrade if you're installation is not in the root of the domain.