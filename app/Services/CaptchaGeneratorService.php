<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use App\Facades\Image;
use Illuminate\Support\Str;

class CaptchaGeneratorService
{
    /**
     * Generate a captcha image set with a puzzle piece
     *
     * @return array
     */
    public function generateCaptchaImages()
    {
        // Create directory if it doesn't exist
        if (!Storage::exists('public/captcha/backgrounds')) {
            Storage::makeDirectory('public/captcha/backgrounds');
        }
        
        if (!Storage::exists('public/captcha/sliders')) {
            Storage::makeDirectory('public/captcha/sliders');
        }
        
        // Generate a unique identifier for this captcha
        $uniqueId = Str::random(10);
        
        // Background image dimensions
        $width = 320;
        $height = 180;
        
        // Puzzle piece dimensions
        $pieceWidth = 40;
        $pieceHeight = 40;
        
        // Create a background image
        $background = Image::create($width, $height);
        
        // Fill with color
        $background->fill('rgb(50, 100, 150)');
        
        // Choose a random position for the puzzle piece (not too close to edges)
        $posX = rand($pieceWidth + 20, $width - $pieceWidth - 20);
        $posY = rand(20, $height - $pieceHeight - 20);
        
        // Draw piece outline on background
        $background->drawRectangle(
            $posX, 
            $posY, 
            $pieceWidth, 
            $pieceHeight, 
            function ($rectangle) {
                $rectangle->background('rgba(255, 255, 255, 0.8)');
                $rectangle->border(2, 'rgba(255, 255, 255, 0.9)');
            }
        );
        
        // Save background
        $background->save(Storage::path('public/captcha/backgrounds/bg_' . $uniqueId . '.jpg'));
        
        // Create slider piece
        $slider = Image::create($pieceWidth, $pieceHeight);
        
        // Fill with gradient color
        $slider->fill('rgba(255, 255, 255, 0.9)');
        
        // Add border
        $slider->drawRectangle(
            0, 
            0, 
            $pieceWidth - 1, 
            $pieceHeight - 1, 
            function ($rectangle) {
                $rectangle->border(2, 'rgba(0, 0, 0, 0.5)');
            }
        );
        
        // Save slider
        $slider->save(Storage::path('public/captcha/sliders/slider_' . $uniqueId . '.png'));
        
        // Return the file paths and positions
        return [
            'background' => 'captcha/backgrounds/bg_' . $uniqueId . '.jpg',
            'slider' => 'captcha/sliders/slider_' . $uniqueId . '.png',
            'position_x' => ($posX / $width) * 100, // as percentage
            'position_y' => ($posY / $height) * 100, // as percentage
        ];
    }
}