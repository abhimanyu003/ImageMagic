<?php
/**
 * Image Magic class
 * @author: Abhimanyu Sharma <abhimanyusharma003@gmail.com>
 *
 */
namespace abhimanyusharma003\Image;

class Image
{

    /**
     * Image on which processing orccurs
     */
    protected $im;
    /**
     * Direcory to use for file caching
     */
    protected $cacheDir = 'cache/images';
    /**
     * The actual cache dir
     */
    protected $actualCacheDir = null;
    /**
     * Pretty name for the image
     */
    protected $prettyName = '';
    /**
     * Transformations hash
     */
    protected $hash = null;
    /**
     * File
     */
    protected $file = NULL;
    /**
     * Image data
     */
    protected $data = null;
    protected $width = null;
    protected $height = null;
    protected $operations = array();

    public function __construct($originalFile = null, $width = null, $height = null)
    {
        $this->file = $originalFile;
        $this->width = $width;
        $this->height = $height;


        if (!(extension_loaded('imagick'))) {
            throw new \RuntimeException('You need to install Imagick PHP Extension OR use http://github.com/Gregwar/Image library');
        }
        $this->im = new \Imagick(__DIR__ . DIRECTORY_SEPARATOR . $this->file);

    }

    public static function open($file = '')
    {
        return new Image($file);
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

    public function save($file, $type = 'jpg', $quality = 80)
    {
        if ($file) {
            $directory = dirname($file);

            if (!is_dir($directory)) {
                @mkdir($directory, 0777, true);
            }
        }

        $this->applyOperations();
        if ($type == 'jpg') {
            $this->im->setImageBackgroundColor('white');
            $this->im->flattenImages();
            $this->im = $this->im->flattenImages();
            $this->im->setCompressionQuality($quality);
        }

        $this->im->setImageFormat($type);
        $this->im->writeImage(__DIR__ . DIRECTORY_SEPARATOR . $file);

        return $file;
    }

    /**
     * Applies the operations
     */
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


    protected function _cropImage($width, $height, $x = null, $y = null)
    {
        $this->im->cropImage($width, $height, $x, $y);
        return $this;
    }

    protected function _enlargeSafeResize($width, $height)
    {
        $imageWidth = $this->im->getImageWidth();
        $imageHeight = $this->im->getImageHeight();

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
        $this->im->thumbnailImage($width, $height);
        return $this;
    }

    protected function _cropThumbnailImage($width, $height)
    {
        $geo = $this->im->getImageGeometry();

        if (($geo['width'] / $width) < ($geo['height'] / $height)) {
            $this->im->cropImage($geo['width'], floor($height * $geo['width'] / $width), 0, (($geo['height'] - ($height * $geo['width'] / $width)) / 2));
        } else {
            $this->im->cropImage(ceil($width * $geo['height'] / $height), $geo['height'], (($geo['width'] - ($width * $geo['height'] / $height)) / 2), 0);
        }

        $this->im->ThumbnailImage($width, $height, true);
        return $this;
    }

    protected function _resizeImage($width, $height, $filter = \imagick::DISPOSE_NONE, $blur = NULL)
    {
        $this->im->resizeImage($width, $height, $filter, $blur);
        return $this;
    }


}