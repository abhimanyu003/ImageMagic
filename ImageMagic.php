<?php
/**
 * Image Magic class
 * @author: Abhimanyu Sharma <abhimanyusharma003@gmail.com>
 *
 */
namespace abhimanyusharma003\Image;

class Image
{

    protected $cacheDir = 'cache/images';
    protected $actualCacheDir = null;
    protected $image = NULL;
    protected $format = NULL;
    protected $originalFile = NULL;
    protected $data = NULL;
    protected $width = NULL;
    protected $height = NULL;
    protected $operations = array();
    protected $prettyName = '';
    protected $hash = NULL;
    protected $file = NULL;


    public function __construct($path = NULL, $width = NULL, $height = NULL)
    {
        $this->file = $path;
        $this->width = $width;
        $this->height = $height;

        if (!(extension_loaded('imagick'))) {
            throw new \RuntimeException('You need to install Imagick PHP Extension OR use http://github.com/Gregwar/Image library');
        }

        $this->image = new \Imagick(__DIR__ . DIRECTORY_SEPARATOR . $path);
        $this->format = $this->image->getImageFormat();
    }

    public static function open($path)
    {
        return new Image($path);
    }

    public function jpeg($quality = 80)
    {
        return $this->cacheFile('jpg', $quality);
    }

    public function png()
    {
        return $this->cacheFile('png');
    }

    public function gif()
    {
        return $this->cacheFile('gif');
    }

    public function guess()
    {
        return $this->cacheFile(strtolower($this->format));
    }

    public function cacheFile($type = 'jpg', $quality = 80)
    {
        // Computes the hash
        $this->hash = $this->getHash($type, $quality);

        // Generates the cache file
        list($actualFile, $file) = $this->generateFileFromHash($this->hash . '.' . $type);

        // If the files does not exists, save it
        if (!file_exists($actualFile)) {
            $this->save($actualFile, $type, $quality);
        }

        return $this->getFilename($file);
    }

    public function getHash($type = 'guess', $quality = 80)
    {
        if (null === $this->hash) {
            $this->generateHash();
        }

        return $this->hash;
    }

    public function generateHash($type = 'guess', $quality = 80)
    {
        $inputInfos = 0;

        if ($this->file) {
            try {
                $inputInfos = filectime($this->file);
            } catch (\Exception $e) {
            }
        } else {
            $inputInfos = array($this->width, $this->height);
        }

        $datas = array(
            $this->file,
            $inputInfos,
            $type,
            $this->serializeOperations(),
            $quality
        );

        $this->hash = sha1(serialize($datas));
    }

    public function serializeOperations()
    {
        $datas = array();

        foreach ($this->operations as $operation) {
            $method = $operation[0];
            $args = $operation[1];

            foreach ($args as &$arg) {
                if ($arg instanceof Image) {
                    $arg = $arg->getHash();
                }
            }

            $datas[] = array($method, $args);
        }

        return serialize($datas);
    }

    public function generateFileFromHash($hash)
    {
        $directory = $this->cacheDir;

        if ($this->actualCacheDir === null) {
            $actualDirectory = $directory;
        } else {
            $actualDirectory = $this->actualCacheDir;
        }

        for ($i = 0; $i < 5; $i++) {
            $c = $hash[$i];
            $directory .= '/' . $c;
            $actualDirectory .= '/' . $c;
        }

        $endName = substr($hash, 5);

        if ($this->prettyName) {
            $endName = $this->prettyName . '-' . $endName;
        }

        $file = $directory . '/' . $endName;
        $actualFile = $actualDirectory . '/' . $endName;

        return array($actualFile, $file);
    }

    public function applyOperations()
    {
        // Renders the effects
        foreach ($this->operations as $operation) {
            call_user_func_array(array($this, $operation[0]), $operation[1]);
        }
    }

    protected function getFilename($filename)
    {
        return $filename;
    }


    public function __call($func, $args)
    {
        $reflection = new \ReflectionClass(get_class($this));
        $methodName = '_' . $func;

        if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);

            if ($method->getNumberOfRequiredParameters() > count($args)) {
                throw new \InvalidArgumentException('Not enough arguments given for ' . $func);
            }

            $this->addOperation($methodName, $args);

            return $this;
        }

        throw new \BadFunctionCallException('Invalid method: ' . $func);
    }

    protected function addOperation($method, $args)
    {
        $this->operations[] = array($method, $args);
    }

    public function save($file, $type = 'jpg', $quality = 80)
    {
        if ($file) {
            $directory = dirname($file);

            if (!is_dir($directory)) {
                @mkdir($directory, 0777, true);
            }
        }

        $this->applyOperations();
        $this->image->setImageCompressionQuality($quality);

        if ($type == 'JPG' || $type == 'jpg') {
            $this->image->setImageBackgroundColor('white');
            $this->image->flattenImages();
            $this->image = $this->image->flattenImages();
            $this->image->setImageCompression(\Imagick::COMPRESSION_JPEG);
        }


        if ($type == 'GIF' || $type == 'gif') {
            $this->image->setImageFormat($type);
            $file = preg_replace('/[^\.]*$/', '', $file);
            return $this->image->writeImages(__DIR__ . DIRECTORY_SEPARATOR . $file . 'gif', true) === true;
        }

        $this->image->setImageFormat($type);
        $file = preg_replace('/[^\.]*$/', '', $file);
        $this->image->writeImage(__DIR__ . DIRECTORY_SEPARATOR . $file . strtolower($type));

        return $file;
    }

    protected function _enlargeSafeResize($width, $height)
    {
        $imageWidth = $this->image->getImageWidth();
        $imageHeight = $this->image->getImageHeight();

        if ($imageWidth >= $imageHeight) {
            if ($imageWidth <= $width && $imageHeight <= $height)
                return $this->_thumbnailImage($imageWidth, $imageHeight);
            $wRatio = $width / $imageWidth;
            $hRatio = $height / $imageHeight;
        } else {
            if ($imageHeight <= $width && $imageWidth <= $height)
                return $this->_thumbnailImage($imageWidth, $imageHeight); // no resizing required
            $wRatio = $height / $imageWidth;
            $hRatio = $width / $imageHeight;
        }
        $resizeRatio = Min($wRatio, $hRatio);

        $newHeight = $imageHeight * $resizeRatio;
        $newWidth = $imageWidth * $resizeRatio;

        return $this->_thumbnailImage($newWidth, $newHeight);
    }

    protected function _thumbnailImage($width, $height)
    {
        if ($this->format == 'GIF' || $this->format == 'gif') {
            foreach ($this->image as $frame) {
                $this->image->thumbnailImage($width, $height);
                $frame->setImagePage($width, $height, 0, 0);
            }
        } else {
            $this->image->thumbnailImage($width, $height);
        }
        return $this;
    }

    protected function _cropImage($width, $height, $x = null, $y = null)
    {
        if ($this->format == 'GIF' || $this->format == 'gif') {
            foreach ($this->image as $frame) {
                $this->image->cropImage($width, $height, $x, $y);
                $frame->setImagePage($width, $height, 0, 0);
            }
        } else {
            $this->image->cropImage($width, $height, $x, $y);
        }
        return $this;
    }

    protected function _cropThumbnailImage($width, $height)
    {
        $geo = $this->image->getImageGeometry();

        if (($geo['width'] / $width) < ($geo['height'] / $height)) {

            if ($this->format == 'GIF' || $this->format == 'gif') {
                foreach ($this->image as $frame) {
                    $this->image->cropImage($geo['width'], floor($height * $geo['width'] / $width), 0, (($geo['height'] - ($height * $geo['width'] / $width)) / 2));
                    $frame->setImagePage($width, $height, 0, 0);
                }
            } else {
                $this->image->cropImage($geo['width'], floor($height * $geo['width'] / $width), 0, (($geo['height'] - ($height * $geo['width'] / $width)) / 2));
            }
        } else {
            if ($this->format == 'GIF' || $this->format == 'gif') {
                foreach ($this->image as $frame) {
                    $this->image->cropImage(ceil($width * $geo['height'] / $height), $geo['height'], (($geo['width'] - ($width * $geo['height'] / $height)) / 2), 0);
                    $frame->setImagePage($width, $height, 0, 0);
                }
            } else {
                $this->image->cropImage(ceil($width * $geo['height'] / $height), $geo['height'], (($geo['width'] - ($width * $geo['height'] / $height)) / 2), 0);
            }
        }
        if ($this->format == 'GIF' || $this->format == 'gif') {
            foreach ($this->image as $frame) {
                $this->image->ThumbnailImage($width, $height, true);
                $frame->setImagePage($width, $height, 0, 0);
            }
        } else {
            $this->image->ThumbnailImage($width, $height, true);
        }
        return $this;
    }


    protected function _resizeImage($width, $height, $filter = \imagick::DISPOSE_NONE, $blur = NULL)
    {
        if ($this->format == 'GIF' || $this->format == 'gif') {
            foreach ($this->image as $frame) {
                $this->image->resizeImage($width, $height, $filter, $blur);
                $frame->setImagePage($width, $height, 0, 0);
            }
        } else {
            $this->image->resizeImage($width, $height, $filter, $blur);
        }
        return $this;
    }

}