<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrPinBundleGenerator
{
    /**
     * Generate QR Code with PIN overlay/bundle
     * QR contains PIN code for easy scanning
     */
    public function generateBundle($setting)
    {
        try {
            \Log::info('Starting QR+PIN bundle generation for setting: ' . $setting->id);
            
            // Generate unique 6-digit PIN first
            $setting->qr_pin_code = $this->generateUniquePinCode($setting);
            $setting->qr_pin_enabled = true;
            
            // Generate token (for security/validation)
            $setting->qr_code_token = Str::random(24) . '_' . time();
            
            \Log::info('Generated PIN: ' . $setting->qr_pin_code);
            \Log::info('Generated Token: ' . $setting->qr_code_token);
            
            // OPTION 1: QR contains only PIN (simplest)
            // Staff scans → Gets PIN → Auto-filled
            $qrData = $this->createSecurePinQrData($setting);
            
            // OPTION 2: QR contains PIN + validation data (recommended)
            // $qrData = $this->createSecurePinQrData($setting);
            
            \Log::info('QR Data: ' . $qrData);
            \Log::info('QR Data length: ' . strlen($qrData));
            
            // Generate QR image
            $qrImageData = $this->generateQrImage($qrData);
            
            if (!$qrImageData) {
                throw new \Exception('Failed to generate base QR image');
            }
            
            // Create bundled image (QR + PIN text)
            $bundledImage = $this->createBundledImage($qrImageData, $setting);
            
            // Save bundled image
            $fileName = 'qr_bundle_' . ($setting->id ?? uniqid()) . '_' . time() . '.png';
            $path = 'qr_codes/' . $fileName;
            
            Storage::disk('public')->put($path, $bundledImage);
            
            if (!Storage::disk('public')->exists($path)) {
                throw new \Exception('Bundle image was not saved');
            }
            
            $setting->qr_code_path = $path;
            $setting->qr_code_generated_at = now();
            
            \Log::info('QR+PIN bundle generated successfully: ' . $path);
            
            return [
                'success' => true,
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'pin' => $setting->qr_pin_code,
                'qr_data' => $qrData
            ];
            
        } catch (\Exception $e) {
            \Log::error('Bundle generation failed: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * OPTION 1: Create QR data with PIN only (Simple)
     * QR scan result: Just the PIN number
     */
    private function createPinQrData($setting)
    {
        // Simplest: QR just contains the PIN
        return $setting->qr_pin_code;
    }
    
    /**
     * OPTION 2: Create QR data with PIN + validation (Secure)
     * QR scan result: JSON with PIN and metadata
     */
    private function createSecurePinQrData($setting)
    {
        // More secure: QR contains PIN + metadata
        return json_encode([
            'pin' => $setting->qr_pin_code,
            'location' => $setting->nama_gedung,
            'id' => $setting->id,
            'type' => 'attendance_pin',
            'generated' => now()->timestamp
        ]);
    }
    
    /**
     * OPTION 3: Create QR with encrypted PIN (Most Secure)
     */
    private function createEncryptedPinQrData($setting)
    {
        $data = [
            'pin' => $setting->qr_pin_code,
            'location' => $setting->nama_gedung,
            'id' => $setting->id,
            'timestamp' => now()->timestamp
        ];
        
        // Encrypt the data
        $encrypted = encrypt(json_encode($data));
        
        return base64_encode($encrypted);
    }
    
    /**
     * Generate unique 6-digit PIN
     */
    private function generateUniquePinCode($setting)
    {
        do {
            $pin = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            $exists = \App\Models\SimpegSettingKehadiran::where('qr_pin_code', $pin)
                ->where('id', '!=', $setting->id)
                ->exists();
                
        } while ($exists);
        
        return $pin;
    }
    
    /**
     * Generate QR code image data
     */
    private function generateQrImage($qrData)
    {
        // Try chillerlan/php-qrcode first
        if (class_exists('chillerlan\QRCode\QRCode')) {
            try {
                $options = new \chillerlan\QRCode\QROptions([
                    'version'      => -1, // Auto-detect
                    'outputType'   => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel'     => \chillerlan\QRCode\QRCode::ECC_M, // Medium for better balance
                    'scale'        => 10,
                    'imageBase64'  => false,
                ]);

                $qrcode = new \chillerlan\QRCode\QRCode($options);
                return $qrcode->render($qrData);
                
            } catch (\Exception $e) {
                \Log::error('chillerlan failed: ' . $e->getMessage());
            }
        }
        
        // Try SimpleSoftwareIO
        if (class_exists('SimpleSoftwareIO\QrCode\Facades\QrCode')) {
            try {
                return \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                    ->size(400)
                    ->margin(2)
                    ->errorCorrection('M')
                    ->generate($qrData);
                    
            } catch (\Exception $e) {
                \Log::error('SimpleSoftwareIO failed: ' . $e->getMessage());
            }
        }
        
        // Fallback to Google Charts API
        try {
            $size = '400x400';
            $qrUrl = 'https://chart.googleapis.com/chart?chs=' . $size . '&cht=qr&chl=' . urlencode($qrData) . '&choe=UTF-8';
            
            return @file_get_contents($qrUrl);
            
        } catch (\Exception $e) {
            \Log::error('Google Charts failed: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Create bundled image: QR Code + PIN text overlay
     */
    private function createBundledImage($qrImageData, $setting)
    {
        $qrImage = imagecreatefromstring($qrImageData);
        
        if (!$qrImage) {
            throw new \Exception('Failed to create image from QR data');
        }
        
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);
        
        // Calculate dimensions
        $headerHeight = 60;
        $textHeight = 200;
        $padding = 30;
        $finalWidth = $qrWidth + ($padding * 2);
        $finalHeight = $qrHeight + $headerHeight + $textHeight + ($padding * 3);
        
        // Create canvas
        $bundleImage = imagecreatetruecolor($finalWidth, $finalHeight);
        
        // Colors
        $white = imagecolorallocate($bundleImage, 255, 255, 255);
        $black = imagecolorallocate($bundleImage, 0, 0, 0);
        $gray = imagecolorallocate($bundleImage, 100, 100, 100);
        $blue = imagecolorallocate($bundleImage, 41, 128, 185);
        $lightBlue = imagecolorallocate($bundleImage, 230, 240, 255);
        $green = imagecolorallocate($bundleImage, 46, 204, 113);
        
        // Fill background
        imagefill($bundleImage, 0, 0, $white);
        
        // Add outer border
        imagerectangle($bundleImage, 0, 0, $finalWidth - 1, $finalHeight - 1, $gray);
        
        // === HEADER SECTION ===
        $headerY = $padding / 2;
        
        // Add header text
        $this->addCenteredText(
            $bundleImage, 
            'PRESENSI ' . strtoupper($setting->nama_gedung ?? 'LOKASI'), 
            $finalWidth, 
            $headerY + 10, 
            5, 
            $black
        );
        
        // Add separator line
        imageline($bundleImage, $padding, $headerHeight, $finalWidth - $padding, $headerHeight, $gray);
        
        // === QR CODE SECTION ===
        $qrX = ($finalWidth - $qrWidth) / 2;
        $qrY = $headerHeight + $padding;
        
        // Add QR border
        imagerectangle($bundleImage, $qrX - 2, $qrY - 2, $qrX + $qrWidth + 1, $qrY + $qrHeight + 1, $gray);
        
        // Copy QR code
        imagecopy($bundleImage, $qrImage, $qrX, $qrY, 0, 0, $qrWidth, $qrHeight);
        
        // === INSTRUCTION SECTION ===
        $textY = $qrY + $qrHeight + $padding;
        
        // Add instruction badge
        $badgeY = $textY;
        $badgeHeight = 30;
        imagefilledrectangle(
            $bundleImage, 
            $padding, 
            $badgeY, 
            $finalWidth - $padding, 
            $badgeY + $badgeHeight, 
            $green
        );
        
        $this->addCenteredText(
            $bundleImage, 
            'SCAN QR CODE DI ATAS', 
            $finalWidth, 
            $badgeY + 10, 
            4, 
            $white
        );
        
        // === PIN SECTION ===
        $pinY = $badgeY + $badgeHeight + 20;
        
        // Add "OR" divider
        $this->addCenteredText(
            $bundleImage, 
            'atau masukkan kode PIN:', 
            $finalWidth, 
            $pinY, 
            3, 
            $gray
        );
        
        // Add PIN digits
        $pinDigitsY = $pinY + 30;
        $this->addLargePinText($bundleImage, $setting->qr_pin_code, $finalWidth, $pinDigitsY, $blue, $lightBlue);
        
        // === FOOTER SECTION ===
        $footerY = $finalHeight - 50;
        
        
       
        
        // Convert to PNG
        ob_start();
        imagepng($bundleImage);
        $imageData = ob_get_clean();
        
        // Free memory
        imagedestroy($qrImage);
        imagedestroy($bundleImage);
        
        return $imageData;
    }
    
    private function addCenteredText($image, $text, $imageWidth, $y, $fontSize, $color)
    {
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $x = ($imageWidth - $textWidth) / 2;
        imagestring($image, $fontSize, $x, $y, $text, $color);
    }
    
    private function addLargePinText($image, $pin, $imageWidth, $y, $textColor, $boxColor)
    {
        $digits = str_split($pin);
        $digitWidth = 40;
        $spacing = 10;
        $totalWidth = (count($digits) * $digitWidth) + ((count($digits) - 1) * $spacing);
        $startX = ($imageWidth - $totalWidth) / 2;
        
        foreach ($digits as $index => $digit) {
            $x = $startX + ($index * ($digitWidth + $spacing));
            
            // Draw box
            imagefilledrectangle($image, $x, $y, $x + $digitWidth, $y + 50, $boxColor);
            imagerectangle($image, $x, $y, $x + $digitWidth, $y + 50, $textColor);
            
            // Draw digit
            $textWidth = imagefontwidth(5) * strlen($digit);
            $textX = $x + (($digitWidth - $textWidth) / 2);
            $textY = $y + 17;
            imagestring($image, 5, $textX, $textY, $digit, $textColor);
        }
    }
    
    public function regenerateBundle($setting)
    {
        if ($setting->qr_code_path && Storage::disk('public')->exists($setting->qr_code_path)) {
            Storage::disk('public')->delete($setting->qr_code_path);
            \Log::info('Deleted old bundle: ' . $setting->qr_code_path);
        }
        
        $result = $this->generateBundle($setting);
        
        if ($result['success']) {
            $setting->save();
        }
        
        return $result;
    }
    
    public function getBundleInfo($setting)
    {
        return [
            'qr_code' => [
                'enabled' => $setting->qr_code_enabled,
                'path' => $setting->qr_code_path,
                'url' => $setting->getQrCodeUrl(),
                'contains' => 'pin_code', // QR contains the PIN
                'generated_at' => $setting->qr_code_generated_at?->format('Y-m-d H:i:s')
            ],
            'pin_code' => [
                'enabled' => $setting->qr_pin_enabled,
                'code' => $setting->qr_pin_enabled ? $setting->qr_pin_code : null,
                'qr_encoded' => true, // PIN is encoded in QR
                'expires_at' => $setting->qr_pin_expires_at?->format('Y-m-d H:i:s'),
                'is_expired' => $setting->isPinExpired()
            ],
            'location' => [
                'nama_gedung' => $setting->nama_gedung,
                'latitude' => $setting->latitude,
                'longitude' => $setting->longitude,
                'radius' => $setting->radius
            ],
            'usage' => [
                'scan_qr' => 'Scan QR to get PIN automatically',
                'manual_pin' => 'Or type the 6-digit PIN manually',
                'both_work' => 'Both methods produce same result'
            ]
        ];
    }
    
    /**
     * Decode PIN from scanned QR data
     */
    public static function decodePinFromQr($scannedData)
    {
        // OPTION 1: Direct PIN (6 digits)
        if (is_numeric($scannedData) && strlen($scannedData) == 6) {
            return [
                'success' => true,
                'pin' => $scannedData,
                'method' => 'direct'
            ];
        }
        
        // OPTION 2: JSON with PIN
        if (is_string($scannedData) && (strpos($scannedData, '{') === 0 || strpos($scannedData, '[') === 0)) {
            try {
                $decoded = json_decode($scannedData, true);
                if (isset($decoded['pin'])) {
                    return [
                        'success' => true,
                        'pin' => $decoded['pin'],
                        'location' => $decoded['location'] ?? null,
                        'method' => 'json'
                    ];
                }
            } catch (\Exception $e) {
                // Not valid JSON
            }
        }
        
        // OPTION 3: Encrypted data
        try {
            $decrypted = decrypt(base64_decode($scannedData));
            $decoded = json_decode($decrypted, true);
            if (isset($decoded['pin'])) {
                return [
                    'success' => true,
                    'pin' => $decoded['pin'],
                    'location' => $decoded['location'] ?? null,
                    'method' => 'encrypted'
                ];
            }
        } catch (\Exception $e) {
            // Not encrypted data
        }
        
        return [
            'success' => false,
            'error' => 'Invalid QR code format'
        ];
    }
}