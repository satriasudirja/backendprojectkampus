<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SimpleCaptchaGeneratorService
{
    /**
     * Generate a captcha image set with a puzzle piece
     *
     * @return array
     */
    public function generateCaptchaImages()
    {
        // Make sure GD extension is loaded
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('GD extension is required for CAPTCHA generation.');
        }
        
        // Penting: Gunakan direktori private untuk penyimpanan
        if (!Storage::exists('private/captcha/backgrounds')) {
            Storage::makeDirectory('private/captcha/backgrounds');
        }
        
        if (!Storage::exists('private/captcha/sliders')) {
            Storage::makeDirectory('private/captcha/sliders');
        }
        
        // Generate a unique identifier for this captcha
        $uniqueId = Str::random(10);
        
        // Background image dimensions
        $width = 320;
        $height = 180;
        
        // Puzzle piece dimensions
        $pieceWidth = 40;
        $pieceHeight = 40;
        
        // Create images using native GD
        $backgroundPath = $this->createBackgroundImage($width, $height, $uniqueId);
        
        // Choose a random position for the puzzle piece (not too close to edges)
        $posX = rand($pieceWidth + 20, $width - $pieceWidth - 20);
        $posY = rand(20, $height - $pieceHeight - 20);
        
        // Create a puzzle piece
        $sliderPath = $this->createSliderImage($backgroundPath, $posX, $posY, $pieceWidth, $pieceHeight, $uniqueId);
        
        // Create a cutout in the background
        $this->createCutout($backgroundPath, $posX, $posY, $pieceWidth, $pieceHeight, $uniqueId);
        
        // Return the file paths and positions
        return [
            'background' => 'private/captcha/backgrounds/bg_' . $uniqueId . '.jpg',
            'slider' => 'private/captcha/sliders/slider_' . $uniqueId . '.png',
            'position_x' => ($posX / $width) * 100, // as percentage
            'position_y' => ($posY / $height) * 100, // as percentage
        ];
    }
    
    /**
     * Create a background image with a gradient
     *
     * @param int $width
     * @param int $height
     * @param string $uniqueId
     * @return string Path to the created image
     */
    protected function createBackgroundImage($width, $height, $uniqueId)
    {
        // Create base image
        $image = imagecreatetruecolor($width, $height);
        
        // Allocate colors for gradient
        $startColor = imagecolorallocate($image, 50, 100, 150);
        $endColor = imagecolorallocate($image, 200, 200, 250);
        
        // Create gradient
        for ($i = 0; $i < $height; $i++) {
            $ratio = $i / $height;
            $r = 50 + (int)($ratio * 150);
            $g = 100 + (int)($ratio * 100);
            $b = 150 + (int)($ratio * 50);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $i, $width, $i, $color);
        }
        
        // Add some random shapes for texture
        for ($i = 0; $i < 20; $i++) {
            $color = imagecolorallocatealpha($image, rand(0, 255), rand(0, 255), rand(0, 255), 110);
            imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $color);
        }
        
        // Save image
        $path = Storage::path('private/captcha/backgrounds/bg_' . $uniqueId . '.jpg');
        imagejpeg($image, $path, 90);
        imagedestroy($image);
        
        return $path;
    }
    
    /**
     * Create a slider image from part of the background
     *
     * @param string $backgroundPath
     * @param int $posX
     * @param int $posY
     * @param int $width
     * @param int $height
     * @param string $uniqueId
     * @return string Path to the created image
     */
    protected function createSliderImage($backgroundPath, $posX, $posY, $width, $height, $uniqueId)
    {
        // Load background image
        $background = imagecreatefromjpeg($backgroundPath);
        
        // Create transparent image for the slider
        $slider = imagecreatetruecolor($width, $height);
        imagealphablending($slider, false);
        imagesavealpha($slider, true);
        $transparent = imagecolorallocatealpha($slider, 0, 0, 0, 127);
        imagefilledrectangle($slider, 0, 0, $width, $height, $transparent);
        
        // Copy portion of background to slider
        imagecopy($slider, $background, 0, 0, $posX, $posY, $width, $height);
        
        // Add a white border to the slider
        $white = imagecolorallocatealpha($slider, 255, 255, 255, 20);
        imagerectangle($slider, 0, 0, $width - 1, $height - 1, $white);
        
        // Save slider
        $path = Storage::path('private/captcha/sliders/slider_' . $uniqueId . '.png');
        imagepng($slider, $path);
        
        // Clean up
        imagedestroy($slider);
        imagedestroy($background);
        
        return $path;
    }
    
    /**
     * Create a cutout in the background image
     *
     * @param string $backgroundPath
     * @param int $posX
     * @param int $posY
     * @param int $width
     * @param int $height
     * @param string $uniqueId
     * @return void
     */
    protected function createCutout($backgroundPath, $posX, $posY, $width, $height, $uniqueId)
    {
        // Load background image
        $background = imagecreatefromjpeg($backgroundPath);
        
        // Create a semi-transparent white rectangle for the cutout
        $color = imagecolorallocatealpha($background, 255, 255, 255, 80);
        imagefilledrectangle($background, $posX, $posY, $posX + $width - 1, $posY + $height - 1, $color);
        
        // Add border to cutout
        $border = imagecolorallocatealpha($background, 255, 255, 255, 40);
        imagerectangle($background, $posX, $posY, $posX + $width - 1, $posY + $height - 1, $border);
        
        // Save updated background
        imagejpeg($background, $backgroundPath, 90);
        imagedestroy($background);
    }
}