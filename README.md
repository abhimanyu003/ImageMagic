Image Magic class
=====================

Image Magic is based on PHP:Imagick library. The main use of this library to provide caching and easy image manipulation. 

Usage
=====

Using methods chaining, you can open, transform and save a file in a single line:

```php
<?php
require_once('ImageMagic.php');
  
use abhimanyusharma003\Image\Image;
    
Image::open('in.png')
    ->resizeImage(100, 100)
    ->save('out.jpg');
```

The methods available are:

* `cropImage($width, $height, $x = null, $y = null)`: crop the image to given dimension

* `cropThumbnailImage($width, $height)`: Creates a fixed size thumbnail by first scaling the image up or down and cropping a specified area from the center.

* `resizeImage($width, $height)`: resizes the image, will orce the image to
   be exactly $width by $height

* `enlargeSafeResize($width, $height)`: resizes the image keep aspect ratio, will never enlarge the image nor canvas.



Saving the image
----------------
You save the image with `save($imageName,$format,$qualit)` method.

```php
<?php
use abhimanyusharma003\Image\Image;

Image::open('in.png')->save('out.jpg','jpg',100);
```


Resize On the Fly
-----------------

Each operation above is not actually applied on the opened image, but added in an operations
array. This operation array, the name, type and modification time of file are hashed using
`sha1()` and the hash is used to look up for a cache file.

If cache file already present no operation will be executed

```php
Image::open('in.png')->resizeImage(200,200)->jpeg();
```

This will re-size you image on the fly

Using with composer
===================

This repository is available with composer under the name `abhimanyusharma003/image`, so simply add this to
your requires :

```
    "requires": {
        ...
        "abhimanyusharma003/image": "dev-master"
        ...
    }
```

And update your dependencies, you'll be able to use the composer autoloader to load the class

Development
===========

Most of the codes of this library are taken from [https://github.com/Gregwar/Image](https://github.com/Gregwar/Image "Gregwar/Image") class it's based on PHP GD library.

Do not hesitate to fork this repository and customize it!
