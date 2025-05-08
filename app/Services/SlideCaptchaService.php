<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SlideCaptchaService
{
    /**
     * @var SimpleCaptchaGeneratorService
     */
    protected $generator;
    
    /**
     * Constructor
     * 
     * @param SimpleCaptchaGeneratorService $generator
     */
    public function __construct(SimpleCaptchaGeneratorService $generator)
    {
        $this->generator = $generator;
    }
    
    /**
     * Generate a slide captcha
     *
     * @return array
     */
    public function generateSlideCaptcha()
    {
        try {
            // Generate new captcha images
            $captchaImages = $this->generator->generateCaptchaImages();
            
            // Generate a unique captcha ID
            $captchaId = Str::uuid()->toString();
            
            // Get the slider position as percentage
            $sliderPosition = $captchaImages['position_x'];
            $sliderYPosition = $captchaImages['position_y'];
            
            // Store the slider position in cache with 10 minutes expiration
            $captchaData = [
                'slider_position' => $sliderPosition,
                'slider_y_position' => $sliderYPosition,
                'timestamp' => now()->timestamp,
                'tolerance' => 5, // Acceptable error margin in percentage
            ];
            
            Cache::put('slide_captcha_' . $captchaId, json_encode($captchaData), 600);
            
            // Store filenames for cleanup later
            Cache::put('slide_captcha_filenames_' . $captchaId, json_encode([
                'background' => $captchaImages['background'],
                'slider' => $captchaImages['slider']
            ]), 600);
            
            // Return captcha data for the frontend
            // Instead of direct URLs, we use routes to serve the images
            return [
                'captcha_id' => $captchaId,
                'background_url' => route('captcha.image', ['type' => 'background', 'id' => $captchaId]),
                'slider_url' => route('captcha.image', ['type' => 'slider', 'id' => $captchaId]),
                'slider_y' => $sliderYPosition,
            ];
        } catch (\Exception $e) {
            // Log error
            Log::error('Error generating slide captcha: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a basic error response
            return [
                'error' => true,
                'message' => 'Failed to generate CAPTCHA. Please try again.'
            ];
        }
    }
    
    /**
     * Verify the slide captcha
     *
     * @param string $captchaId
     * @param float $sliderPosition
     * @return bool
     */
    public function verifySlideCaptcha($captchaId, $sliderPosition)
    {
        try {
            // Get the stored captcha data
            $key = 'slide_captcha_' . $captchaId;
            $storedData = Cache::get($key);
            
            if (!$storedData) {
                Log::warning('CAPTCHA verification failed: CAPTCHA not found or expired', [
                    'captcha_id' => $captchaId
                ]);
                return false; // Captcha expired or doesn't exist
            }
            
            // Decode stored data
            $captchaData = json_decode($storedData, true);
            
            // Check if solution was submitted within reasonable time (between 1 and 60 seconds)
            $elapsed = now()->timestamp - $captchaData['timestamp'];
            if ($elapsed < 1 || $elapsed > 60) {
                Log::warning('CAPTCHA verification failed: Submission time outside acceptable range', [
                    'captcha_id' => $captchaId,
                    'elapsed_time' => $elapsed
                ]);
                $this->cleanupCaptcha($captchaId);
                return false;
            }
            
            // After verification, remove the captcha from cache and files
            $this->cleanupCaptcha($captchaId);
            
            // Check if the submitted position is within acceptable range
            $correctPosition = $captchaData['slider_position'];
            $tolerance = $captchaData['tolerance'];
            $difference = abs($sliderPosition - $correctPosition);
            
            $isValid = $difference <= $tolerance;
            
            Log::info('CAPTCHA verification ' . ($isValid ? 'successful' : 'failed'), [
                'captcha_id' => $captchaId,
                'submitted_position' => $sliderPosition,
                'correct_position' => $correctPosition,
                'difference' => $difference,
                'tolerance' => $tolerance
            ]);
            
            return $isValid;
        } catch (\Exception $e) {
            Log::error('Error verifying slide captcha: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Clean up captcha files and cache
     * 
     * @param string $captchaId
     * @return void
     */
    protected function cleanupCaptcha($captchaId)
    {
        try {
            // Remove from cache
            Cache::forget('slide_captcha_' . $captchaId);
            
            // Try to get image filenames from the stored data
            $storedData = Cache::get('slide_captcha_filenames_' . $captchaId);
            if ($storedData) {
                $fileData = json_decode($storedData, true);
                
                // Delete files if they exist
                if (isset($fileData['background'])) {
                    Storage::delete($fileData['background']);
                }
                
                if (isset($fileData['slider'])) {
                    Storage::delete($fileData['slider']);
                }
                
                Cache::forget('slide_captcha_filenames_' . $captchaId);
            }
        } catch (\Exception $e) {
            Log::error('Error cleaning up captcha: ' . $e->getMessage());
        }
    }
}