=== ImageShack Offloader ===
Contributors: scribu
Donate link: http://scribu.net/wordpress
Tags: cdn, images, imageshack
Requires at least: 2.8
Tested up to: 2.9
Stable tag: trunk

Offload your images to <a href="http://imageshack.us">ImageShack</a> to save server resources.

== Description ==

Having images in posts is always a good ideea. However, having a lot of images in posts usually requires more server resources: more bandwith and more requests per page. If your site is even remotely popular, this usually means a more expensize hosting package.

<a href="http://imageshack.us">ImageShack</a> is a service that offers _free_ image hosting. So why not take advantage of it?

Instead of uploading each image manually and modifying each post, you can just install this plugin and be done with it.

If an image can't be loaded from ImageShack, the plugin will roll back to the image on your server.

**Advantages:**

* You don't have to modifiy your posts.
* You can use the default WP interface for uploading images.
* You have a backup of your images on your server.

== Installation ==

You can either use the built-in WordPress plugin install menu, or do it the old-fashioned way:

1. Unzip the .zip archive and put the imageshack-offloader folder into your plugins folder (/wp-content/plugins/).
1. Activate the plugin from the Plugins menu.
1. Go to Settings -> Imageshack Offloader and customize your settings.

= Usage =

The plugin will automatically filter the post content and replace image src attributes with ImageShack URLs.

If you want to get the URL for a specific image, you can use `get_imageshack_url($id, $size)`, where $id is the attachment id and $size is the desired size. Default is 'full'. If there is no URL yet, the function will return false.

== Frequently Asked Questions ==

= "Parse error: syntax error, unexpected T_CLASS..." Help! =

Make sure your new host is running PHP 5. Add this line to wp-config.php:

`var_dump(PHP_VERSION);`

== Changelog ==

= 1.0.4 =
* fix image replacement in post content

= 1.0.3 =
* added roll back mechanism for missing images

= 1.0.2 =
* better image replacing

= 1.0.1 =
* settings page bugfix

= 1.0 =
* use transloading instead of uploading
* [more info](http://scribu.net/wordpress/imageshack-offloader/io-1-0.html)

= 0.9 =
* initial release
* [more info](http://scribu.net/wordpress/imageshack-offloader/io-0-9.html)

