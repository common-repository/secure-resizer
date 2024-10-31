=== Secure Image Resizer ===
Tags: image, resize
Requires at least: 3.0
Tested up to: 3.4
Stable tag: trunk

A very simple and secure image resizer. It adds dynamic resizing so you don't need to regenerate thumbnails when you install a new theme.

== Description ==

A lot of themes ship with Timthumb because it allows their users to start using the theme without rebuilding thumbnails.

Secure Image Resizer offers a far more secure alternative. It allows theme developers to rely on WordPress' own image resizing functionality using `add_image_size` and `get_the_post_thumbnail`.

When get_the_post_thumbnail is called, Secure Image Resizer checks if the requested image size exists. If the image doesn't exist it dynamically creates the resized version. It works in the background, with no front end functionality.

Theme developers can either bundle this plugin into their themes, or suggest that their users install it.

It also includes 2 functions `so_resize_custom` and `so_resize_custom_html` to do dynamic image resizing using Imagick. Great for theme developers.

Please Note: This is an early release of Secure Image Resizer. Please use it, try and break it and report any bugs to me support@siteorigin.com so I can quickly fix them and work towards version 1.0.

== Installation ==

This is a standard WordPress plugin. Just download the zip, then installing it by following [these instructions](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

There's nothing to configure after setup.

== Changelog ==

= 0.1 =
Initial release