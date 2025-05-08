<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SlideCaptchaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class CaptchaController extends Controller
{
    protected $captchaService;
    
    public function __construct(SlideCaptchaService $captchaService)
    {
        $this->captchaService = $captchaService;
    }
    
    /**
     * Show slide captcha page
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showSlideCaptcha(Request $request)
    {
        try {
            $captchaId = $request->query('id');
            
            if (!$captchaId) {
                // If no captcha ID provided, generate a new one
                $captcha = $this->captchaService->generateSlideCaptcha();
                
                if (isset($captcha['error'])) {
                    return view('errors.captcha', ['message' => $captcha['message']]);
                }
                
                $captchaId = $captcha['captcha_id'];
                $backgroundUrl = $captcha['background_url'];
                $sliderUrl = $captcha['slider_url'];
                $sliderY = $captcha['slider_y'];
                
                // Log success for debugging
                Log::info('New CAPTCHA generated successfully', [
                    'captcha_id' => $captchaId
                ]);
            } else {
                // Get captcha data from cache
                $storedData = Cache::get('slide_captcha_' . $captchaId);
                
                if (!$storedData) {
                    // Captcha expired or doesn't exist
                    Log::warning('Captcha not found or expired', ['captcha_id' => $captchaId]);
                    return redirect()->route('captcha.slide-captcha');
                }
                
                $captchaData = json_decode($storedData, true);
                
                // Get filenames from cache
                $fileNames = Cache::get('slide_captcha_filenames_' . $captchaId);
                if (!$fileNames) {
                    Log::warning('Captcha filenames not found', ['captcha_id' => $captchaId]);
                    return redirect()->route('captcha.slide-captcha');
                }
                
                $fileData = json_decode($fileNames, true);
                
                // Convert storage paths to URLs using URL routing
                $backgroundUrl = route('captcha.image', ['type' => 'background', 'id' => $captchaId]);
                $sliderUrl = route('captcha.image', ['type' => 'slider', 'id' => $captchaId]);
                $sliderY = $captchaData['slider_y_position'] ?? 50;
            }
            
            return view('captcha.slide-captcha', [
                'captchaId' => $captchaId,
                'backgroundUrl' => $backgroundUrl,
                'sliderUrl' => $sliderUrl,
                'sliderY' => $sliderY,
            ]);
        } catch (\Exception $e) {
            // Log detailed error information
            Log::error('Error showing CAPTCHA', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a simpler error view
            return view('errors.captcha', [
                'message' => 'Terjadi kesalahan saat menampilkan CAPTCHA. Silakan coba lagi.'
            ]);
        }
    }
    
    /**
     * Serve captcha images from private storage
     *
     * @param Request $request
     * @param string $type
     * @param string $id
     * @return \Illuminate\Http\Response
     */
    public function serveImage(Request $request, $type, $id)
    {
        try {
            // Get filename from cache
            $fileNames = Cache::get('slide_captcha_filenames_' . $id);
            
            if (!$fileNames) {
                return response()->json(['error' => 'Image not found'], 404);
            }
            
            $fileData = json_decode($fileNames, true);
            
            if ($type === 'background') {
                $path = $fileData['background'];
            } elseif ($type === 'slider') {
                $path = $fileData['slider'];
            } else {
                return response()->json(['error' => 'Invalid image type'], 400);
            }
            
            // Check if file exists
            if (!Storage::exists($path)) {
                return response()->json(['error' => 'Image file not found'], 404);
            }
            
            // Get file content and mime type
            $file = Storage::get($path);
            $mimeType = Storage::mimeType($path);
            
            // Return file with proper headers
            return Response::make($file, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Exception $e) {
            Log::error('Error serving CAPTCHA image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}