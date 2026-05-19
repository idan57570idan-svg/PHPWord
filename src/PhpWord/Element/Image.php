<?php

/**
 * This file is part of PHPWord - A pure PHP library for reading and writing
 * word processing documents.
 *
 * PHPWord is free software distributed under the terms of the GNU Lesser
 * General Public License version 3 as published by the Free Software Foundation.
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code. For the full list of
 * contributors, visit https://github.com/PHPOffice/PHPWord/contributors.
 *
 * @see        https://github.com/PHPOffice/PHPWord
 *
 * @license    http://www.gnu.org/licenses/lgpl.txt LGPL version 3
 */

namespace PhpOffice\PhpWord\Element;

use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\Exception\InvalidImageException;
use PhpOffice\PhpWord\Exception\UnsupportedImageTypeException;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\ZipArchive;
use PhpOffice\PhpWord\Style\Image as ImageStyle;

/**
 * Image element.
 */
class Image extends AbstractElement
{
    /**
     * Image source type constants.
     */
    const SOURCE_LOCAL = 'local';
    const SOURCE_GD = 'gd';
    const SOURCE_ARCHIVE = 'archive';
    const SOURCE_STRING = 'string';

    private $source;
    private $sourceType;
    private $style;
    private $watermark;
    private $name;
    private $altText;
    private $imageType;
    private $imageCreateFunc;
    private $imageFunc;
    private $imageExtension;
    private $imageQuality;
    private $memoryImage;
    private $target;
    private $mediaIndex;
    protected $mediaRelation = true;

    public function __construct($source, $style = null, $watermark = false, $name = null, $altText = null)
    {
        $this->source = $source;
        $this->style = $this->setNewStyle(new ImageStyle(), $style, true);
        $this->setIsWatermark($watermark);
        $this->setName($name);
        $this->setAltText($altText);

        $this->checkImage();
    }

    public function getStyle()
    {
        return $this->style;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getSourceType()
    {
        return $this->sourceType;
    }

    public function setName($value): void
    {
        $this->name = $value;
    }

    /**
     * Get image alt text.
     */
    public function getAltText(): ?string
    {
        return $this->altText;
    }

    /**
     * Sets the image alt text.
     */
    public function setAltText(?string $value): void
    {
        $this->altText = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getMediaId()
    {
        return md5($this->source);
    }

    public function isWatermark()
    {
        return $this->watermark;
    }

    public function setIsWatermark($value): void
    {
        $this->watermark = $value;
    }

    public function getImageType()
    {
        return $this->imageType;
    }

    public function getImageCreateFunction()
    {
        return $this->imageCreateFunc;
    }

    public function getImageFunction(): ?callable
    {
        return $this->imageFunc;
    }

    public function getImageQuality(): ?int
    {
        return $this->imageQuality;
    }

    public function getImageExtension()
    {
        return $this->imageExtension;
    }

    public function isMemImage()
    {
        return $this->memoryImage;
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function setTarget($value): void
    {
        $this->target = $value;
    }

    public function getMediaIndex()
    {
        return $this->mediaIndex;
    }

    public function setMediaIndex($value): void
    {
        $this->mediaIndex = $value;
    }

    public function getImageString(): ?string
    {
        $source = $this->source;
        $actualSource = null;
        $imageBinary = null;
        $isTemp = false;

        if ($this->sourceType == self::SOURCE_ARCHIVE) {
            $source = substr($source, 6);
            [$zipFilename, $imageFilename] = explode('#', $source);

            $zip = new ZipArchive();
            if ($zip->open($zipFilename) !== false) {
                if ($zip->locateName($imageFilename) !== false) {
                    $isTemp = true;
                    $zip->extractTo(Settings::getTempDir(), $imageFilename);
                    $actualSource = Settings::getTempDir() . DIRECTORY_SEPARATOR . $imageFilename;
                }
            }
            $zip->close();
        } else {
            $actualSource = $source;
        }

        if ($this->sourceType == self::SOURCE_GD) {
            $imageResource = call_user_func($this->imageCreateFunc, $actualSource);
            if ($this->imageType === 'image/png') {
                imagesavealpha($imageResource, true);
            }
            ob_start();
            $callback = $this->imageFunc;
            $callback($imageResource);
            $imageBinary = ob_get_contents();
            ob_end_clean();
        } elseif ($this->sourceType == self::SOURCE_STRING) {
            $imageBinary = $this->source;
        } else {
            $fileHandle = fopen($actualSource, 'rb', false);
            $fileSize = filesize($actualSource);
            if ($fileHandle !== false && $fileSize > 0) {
                $imageBinary = fread($fileHandle, $fileSize);
                fclose($fileHandle);
            }
        }

        if ($isTemp === true) {
            @unlink($actualSource);
        }

        return $imageBinary;
    }

    public function getImageStringData($base64 = false)
    {
        $imageBinary = $this->getImageString();
        if ($imageBinary === null) {
            return null;
        }

        if ($base64) {
            return base64_encode($imageBinary);
        }

        return bin2hex($imageBinary);
    }

    private function checkImage(): void
    {
        $this->setSourceType();

        if ($this->sourceType == self::SOURCE_ARCHIVE) {
            $imageData = $this->getArchiveImageSize($this->source);
        } elseif ($this->sourceType == self::SOURCE_STRING) {
            $imageData = @getimagesizefromstring($this->source);
        } else {
            $imageData = @getimagesize($this->source);
        }
        if (!is_array($imageData)) {
            throw new InvalidImageException(sprintf('Invalid image: %s', $this->source));
        }
        [$actualWidth, $actualHeight, $imageType] = $imageData;

        $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG];
        if ($this->sourceType != self::SOURCE_GD && $this->sourceType != self::SOURCE_STRING) {
            $supportedTypes = array_merge($supportedTypes, [IMAGETYPE_BMP, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM]);
        }
        if (!in_array($imageType, $supportedTypes)) {
            throw new UnsupportedImageTypeException();
        }

        $this->imageType = image_type_to_mime_type($imageType);
        $this->setFunctions();
        $this->setProportionalSize($actualWidth, $actualHeight);
    }

    private function setSourceType(): void
    {
        if (stripos(strrev($this->source), strrev('.php')) === 0) {
            $this->memoryImage = true;
            $this->sourceType = self::SOURCE_GD;
        } elseif (strpos($this->source, 'zip://') !== false) {
            $this->memoryImage = false;
            $this->sourceType = self::SOURCE_ARCHIVE;
        } elseif (filter_var($this->source, FILTER_VALIDATE_URL) !== false) {
            $this->memoryImage = true;
            if (strpos($this->source, 'https') === 0) {
                $fileContent = file_get_contents($this->source);
                $this->source = $fileContent;
                $this->sourceType = self::SOURCE_STRING;
            } else {
                $this->sourceType = self::SOURCE_GD;
            }
        } elseif ((strpos($this->source, chr(0)) === false) && @file_exists($this->source)) {
            $this->memoryImage = false;
            $this->sourceType = self::SOURCE_LOCAL;
        } else {
            $this->memoryImage = true;
            $this->sourceType = self::SOURCE_STRING;
        }
    }

    private function getArchiveImageSize($source)
    {
        $imageData = null;
        $source = substr($source, 6);
        [$zipFilename, $imageFilename] = explode('#', $source);

        $tempFilename = tempnam(Settings::getTempDir(), 'PHPWordImage');
        if (false === $tempFilename) {
            throw new CreateTemporaryFileException();
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilename) !== false) {
            if ($zip->locateName($imageFilename) !== false) {
                $imageContent = $zip->getFromName($imageFilename);
                if ($imageContent !== false) {
                    file_put_contents($tempFilename, $imageContent);
                    $imageData = getimagesize($tempFilename);
                    unlink($tempFilename);
                }
            }
            $zip->close();
        }

        return $imageData;
    }

    private function setFunctions(): void
    {
        switch ($this->imageType) {
            case 'image/png':
                $this->imageCreateFunc = $this->sourceType == self::SOURCE_STRING ? 'imagecreatefromstring' : 'imagecreatefrompng';
                $this->imageFunc = function ($resource): void {
                    imagepng($resource, null, $this->imageQuality);
                };
                $this->imageExtension = 'png';
                $this->imageQuality = -1;

                break;
            case 'image/gif':
                $this->imageCreateFunc = $this->sourceType == self::SOURCE_STRING ? 'imagecreatefromstring' : 'imagecreatefromgif';
                $this->imageFunc = function ($resource): void {
                    imagegif($resource);
                };
                $this->imageExtension = 'gif';
                $this->imageQuality = null;

                break;
            case 'image/jpeg':
            case 'image/jpg':
                $this->imageCreateFunc = $this->sourceType == self::SOURCE_STRING ? 'imagecreatefromstring' : 'imagecreatefromjpeg';
                $this->imageFunc = function ($resource): void {
                    imagejpeg($resource, null, $this->imageQuality);
                };
                $this->imageExtension = 'jpg';
                $this->imageQuality = 100;

                break;
            case 'image/bmp':
            case 'image/x-ms-bmp':
                $this->imageType = 'image/bmp';
                $this->imageFunc = null;
                $this->imageExtension = 'bmp';
                $this->imageQuality = null;

                break;
            case 'image/tiff':
                $this->imageType = 'image/tiff';
                $this->imageFunc = null;
                $this->imageExtension = 'tif';
                $this->imageQuality = null;

                break;
        }
    }

    private function setProportionalSize($actualWidth, $actualHeight): void
    {
        $styleWidth = $this->style->getWidth();
        $styleHeight = $this->style->getHeight();
        if (!($styleWidth && $styleHeight)) {
            if ($styleWidth == null && $styleHeight == null) {
                $this->style->setWidth($actualWidth);
                $this->style->setHeight($actualHeight);
            } elseif ($styleWidth) {
                $this->style->setHeight($actualHeight * ($styleWidth / $actualWidth));
            } else {
                $this->style->setWidth($actualWidth * ($styleHeight / $actualHeight));
            }
        }
    }
}
