<?php

namespace App\Services;

use Intervention\Image\Facades\Image;

class ImageCreator
{
    protected $image;

    public function __construct()
    {
        $this->image = Image::canvas(296, 128, '#fff');
    }

    public function addText($text, $x, $y, $size = 24, $color = '#000', $align = 'center')
    {
        $this->image->text($text, $x, $y, function ($font) use ($size, $color, $align) {
            $font->file(public_path('fonts/arial.ttf'));
            $font->size($size);
            $font->color($color);
            $font->align($align);
            $font->valign('top');
        });

        return $this;
    }

    public function addMultiLineText($text, $x, $y, $size = 24, $color = '#000', $align = 'center')
    {
        $lines = explode("\n", wordwrap($text, 16)); // break line after 16 charecters

        for ($i = 0; $i < count($lines); $i++) {
            $offset = $y + ($i * $size);

            $this->addText($lines[$i], $x, $offset, $size, $color, $align);
        }

        return $this;
    }

    public function addLine($x1, $y1, $x2, $y2, $color = '#f00')
    {
        $this->image->line($x1, $y1, $x2, $y2, function ($draw) use($color) {
            $draw->color($color);
        });
        
        return $this;
    }

    public function save($filename, $quality = 100)
    {
        $this->image->save($filename, $quality);
    }
}
