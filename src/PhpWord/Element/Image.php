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
 * @see         https://github.com/PHPOffice/PHPWord
 *
 * @license     http://www.gnu.org/licenses/lgpl.txt LGPL version 3
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
}
