<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slide CAPTCHA Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            user-select: none;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .captcha-container {
            width: 320px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .captcha-header {
            padding: 10px 15px;
            background: #f7f7f7;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .captcha-header h3 {
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        
        .captcha-header .captcha-actions {
            display: flex;
            gap: 10px;
        }
        
        .captcha-header .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #777;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .captcha-header .action-btn:hover {
            background: #eee;
        }
        
        .captcha-image-container {
            position: relative;
            height: 180px;
            overflow: hidden;
        }
        
        .captcha-background {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .slider-piece {
            position: absolute;
            z-index: 3;
            cursor: pointer;
            /* Initial position at left side, with y-position set by controller */
            left: 0;
            /* Top will be set by JS */
            touch-action: none;
        }
        
        .slider-container {
            position: relative;
            margin: 15px;
            height: 40px;
            background: #e9e9e9;
            border-radius: 20px;
        }
        
        .slider-track {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            border-radius: 20px;
            background: #ddd;
            width: 0;
            transition: background 0.3s;
        }
        
        .slider-handle {
            position: absolute;
            top: 0;
            left: 0;
            width: 40px;
            height: 40px;
            background: #fff;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #aaa;
            font-size: 20px;
            transition: background 0.3s;
            z-index: 10;
            touch-action: none;
        }
        
        .slider-handle:hover {
            background: #f9f9f9;
        }
        
        .slider-handle.dragging {
            background: #f0f0f0;
        }
        
        .slider-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 13px;
        }
        
        .captcha-footer {
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
        }
        
        .success-msg {
            color: #4caf50;
            font-size: 13px;
            display: none;
        }
        
        .fail-msg {
            color: #f44336;
            font-size: 13px;
            display: none;
        }
        
        .verify-btn {
            background: #4caf50;
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            opacity: 0.7;
            pointer-events: none;
        }
        
        .verify-btn.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        .verify-btn.active:hover {
            background: #43a047;
        }
        
        .verified .slider-track {
            background: #4caf50;
        }
        
        .failed .slider-track {
            background: #f44336;
        }
    </style>
</head>
<body>
    <div class="captcha-container" id="captcha-container">
        <div class="captcha-header">
            <h3>Geser slider untuk menyelesaikan puzzle</h3>
            <div class="captcha-actions">
                <button class="action-btn refresh-btn" title="Refresh">⟳</button>
                <button class="action-btn info-btn" title="Info">ⓘ</button>
            </div>
        </div>
        
        <div class="captcha-image-container">
            <img src="{{ $backgroundUrl }}" alt="Captcha Background" class="captcha-background" id="captcha-background">
            <img src="{{ $sliderUrl }}" alt="Slider Piece" class="slider-piece" id="slider-piece">
        </div>
        
        <div class="slider-container">
            <div class="slider-track" id="slider-track"></div>
            <div class="slider-handle" id="slider-handle">⇥</div>
            <div class="slider-text" id="slider-text">Geser ke kanan untuk memverifikasi</div>
        </div>
        
        <div class="captcha-footer">
            <div>
                <span class="success-msg" id="success-msg">Verifikasi berhasil</span>
                <span class="fail-msg" id="fail-msg">Verifikasi gagal, coba lagi</span>
            </div>
            <button class="verify-btn" id="verify-btn">Verifikasi</button>
        </div>
    </div>
    
    <input type="hidden" id="captcha-id" value="{{ $captchaId }}">
    <input type="hidden" id="slider-y" value="{{ $sliderY }}">
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const container = document.getElementById('captcha-container');
            const background = document.getElementById('captcha-background');
            const sliderPiece = document.getElementById('slider-piece');
            const sliderHandle = document.getElementById('slider-handle');
            const sliderTrack = document.getElementById('slider-track');
            const sliderText = document.getElementById('slider-text');
            const verifyBtn = document.getElementById('verify-btn');
            const successMsg = document.getElementById('success-msg');
            const failMsg = document.getElementById('fail-msg');
            const refreshBtn = document.querySelector('.refresh-btn');
            const captchaId = document.getElementById('captcha-id').value;
            const sliderYPercent = parseFloat(document.getElementById('slider-y').value);
            
            // Variables
            let isDragging = false;
            let startX = 0;
            let sliderLeft = 0;
            let containerWidth = container.offsetWidth;
            let pieceWidth = sliderPiece.offsetWidth;
            let handleWidth = sliderHandle.offsetWidth;
            let maxSliderLeft = containerWidth - handleWidth - 30; // 30px margin
            let isVerified = false;
            let currentPosition = 0;
            
            // Set up initial positions
            const sliderYPosition = (sliderYPercent / 100) * background.offsetHeight;
            
            // Position the slider piece (initially at left, same Y position)
            sliderPiece.style.left = '0px';
            sliderPiece.style.top = `${sliderYPosition - (sliderPiece.offsetHeight / 2)}px`;
            
            // Functions
            function updateSliderPosition(clientX) {
                // Calculate new position
                const dx = clientX - startX;
                let newLeft = sliderLeft + dx;
                
                // Constrain within bounds
                newLeft = Math.max(0, Math.min(newLeft, maxSliderLeft));
                
                // Update slider handle position
                sliderHandle.style.left = `${newLeft}px`;
                
                // Update track width
                sliderTrack.style.width = `${newLeft + handleWidth / 2}px`;
                
                // Calculate position as percentage (0-100)
                currentPosition = (newLeft / maxSliderLeft) * 100;
                
                // Check if slider is moved sufficiently to enable verify button
                if (currentPosition > 10) {
                    verifyBtn.classList.add('active');
                } else {
                    verifyBtn.classList.remove('active');
                }
                
                // Update slider piece position proportionally to match the background
                const backgroundWidth = background.offsetWidth;
                const pieceLeft = (currentPosition / 100) * (backgroundWidth - pieceWidth);
                sliderPiece.style.left = `${pieceLeft}px`;
            }
            
            function startDrag(e) {
                if (isVerified) return;
                
                // Get starting position
                startX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
                sliderLeft = sliderHandle.offsetLeft;
                
                // Set dragging state
                isDragging = true;
                sliderHandle.classList.add('dragging');
                
                // Capture events
                document.addEventListener('mousemove', onDrag);
                document.addEventListener('touchmove', onDrag, { passive: false });
                document.addEventListener('mouseup', stopDrag);
                document.addEventListener('touchend', stopDrag);
            }
            
            function onDrag(e) {
                if (!isDragging) return;
                
                // Prevent default to avoid scrolling on mobile
                if (e.type === 'touchmove') {
                    e.preventDefault();
                }
                
                const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                updateSliderPosition(clientX);
            }
            
            function stopDrag() {
                if (!isDragging) return;
                
                // Reset dragging state
                isDragging = false;
                sliderHandle.classList.remove('dragging');
                
                // Remove events
                document.removeEventListener('mousemove', onDrag);
                document.removeEventListener('touchmove', onDrag);
                document.removeEventListener('mouseup', stopDrag);
                document.removeEventListener('touchend', stopDrag);
            }
            
            function verifyCaptcha() {
                if (!verifyBtn.classList.contains('active')) return;
                
                // Save the solution data
                const solutionData = {
                    captchaId: captchaId,
                    sliderPosition: currentPosition
                };
                
                // Send to parent window or save locally
                if (window.opener) {
                    window.opener.postMessage(solutionData, '*');
                    // Close this window after a short delay
                    setTimeout(() => window.close(), 1500);
                } else if (window.parent !== window) {
                    window.parent.postMessage(solutionData, '*');
                } else {
                    // Copy to clipboard as fallback
                    navigator.clipboard.writeText(JSON.stringify(solutionData))
                        .then(() => {
                            alert('Solusi CAPTCHA telah disalin ke clipboard. Gunakan untuk verifikasi.');
                        })
                        .catch(err => {
                            console.error('Gagal menyalin ke clipboard:', err);
                            alert('Solusi CAPTCHA: ' + JSON.stringify(solutionData));
                        });
                }
                
                // Visual feedback
                isVerified = true;
                container.classList.add('verified');
                successMsg.style.display = 'block';
                failMsg.style.display = 'none';
                sliderText.style.display = 'none';
                verifyBtn.innerText = 'Terverifikasi';
            }
            
            function refreshCaptcha() {
                // In a real implementation, we'd fetch a new CAPTCHA from the server
                window.location.reload();
            }
            
            // Add event listeners
            sliderHandle.addEventListener('mousedown', startDrag);
            sliderHandle.addEventListener('touchstart', startDrag, { passive: true });
            
            verifyBtn.addEventListener('click', verifyCaptcha);
            
            refreshBtn.addEventListener('click', refreshCaptcha);
            
            // Handle window resize
            window.addEventListener('resize', function() {
                containerWidth = container.offsetWidth;
                maxSliderLeft = containerWidth - handleWidth - 30;
                
                // Recalculate positions if verification not complete
                if (!isVerified) {
                    sliderHandle.style.left = '0px';
                    sliderTrack.style.width = '0px';
                    sliderPiece.style.left = '0px';
                    currentPosition = 0;
                    verifyBtn.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>