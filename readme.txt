=== ImageShack Offloader ===
Contributors: scribu
Donate link: http://scribu.net/paypal
Tags: cdn, images, imageshack
Requires at least: 2.8
Tested up to: 2.9
Stable tag: 1.0.4

Offload your images to <a href="http://imageshack.us">ImageShack</a> to save server resources.

== Description ==

Displaying a lot of images on your site is great. You'll notice, however, that they tend to eat up a lot of bandwidth. If your site is even remotely popular, this usually means you'll have to upgrade your hosting account.

<a href="http://imageshack.us">ImageShack</a> is a service that offers _free_ image hosting. So why not take advantage of it?

You could start uploading the images manually and modify each post, or you can just install this plugin and be done with it. It will take care of sending copies of all your current (and future) attachments to ImageShack. It will also dynamically replace the URLs when the images are to be displayed.

This way, if you decide you don't want to use ImageShack anymore, you can disable the plugin and everything goes back to how it was.

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

= 0.9 =
* initial release

