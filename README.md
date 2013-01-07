Archive installer
=================

This is a simple installer that let's you create simple Composer packages that are actually downloading and extracting an archive from the web.

Downloading an archive from the web is actually already possible in Composer using the ["package" repository](http://getcomposer.org/doc/05-repositories.md#package-2), but this approach has a number of drawbacks. For instance, you cannot unpack the package in the root directory, or you cannot build dependencies easily upon that package.

Using the archive installer, you can let Composer install big packages that have no Composer package for you. For instance, you can build a Drupal installer just by writing a composer.json file.

A package implementing the archive installer should contain at least these statements in *composer.json*:


	{
		...
		"type": "archive-package",
		...
		"extra": {
			"url": "http://exemple.com/myarchive.zip"
			"target-dir": "destination/directory"
		}
	}
	
Please note that *target-dir* is relative to the root of your project (the directory containing the *composer.json* file).
If *target-dir* is ommitted, we default to the package's directory.

