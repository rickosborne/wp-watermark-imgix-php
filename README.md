# wp-watermark-imgix-php
The world's worst WordPress plugin for adding image watermarks via the imgix API.
You absolutely do not want to use this plugin.

## What's it do?

It hooks the WP attachment API to watch as files are uploaded and resized, then runs each through imgix's [watermark](https://docs.imgix.com/apis/url/watermark/mark) API, downloading them and updating the metadata before the CDN uploader plugin sees the files.

## History

I use WordPress for [my blog](https://rickosborne.org/blog/).
I like to take photos, and I like to post them to that blog with my phone.
But I also prefer to add a watermark (with the blog URL and license information) to the photos before I publish them.

Problem being: the very wonderful WordPress plugin I use to transparently push my photos to my CDN doesn't play well with the plugin I originally used to apply the watermark.
The watermark plugin was a little long in the tooth, and didn't look like it was going to get any more updates.
I poked around in the source for a while, but couldn't figure out how to get the two talking ... so here we are.
