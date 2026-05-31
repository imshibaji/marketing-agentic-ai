/**
 * Alert Box Plugin - Client script
 * Intercepts all window.alert calls and redirects them to a beautiful glassmorphic modal
 */
(function() {
    // Queue configuration
    const alertQueue = [];
    let isAlertActive = false;

    // Web Audio Synthesizer for premium auditory chimes
    let audioCtx = null;

    function getAudioContext() {
        console.log('[Alert Box Debug] getAudioContext called');
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                throw new Error('Web Audio API is not supported in this browser.');
            }
            audioCtx = new AudioContextClass();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            console.log('[Alert Box Debug] AudioContext is suspended, attempting to resume');
            audioCtx.resume().catch(err => {
                console.warn('[Alert Box Debug] Failed to resume AudioContext:', err);
            });
        }
        return audioCtx;
    }

    function playChime(type) {
        console.log('[Alert Box Debug] playChime called with type:', type);
        try {
            const ctx = getAudioContext();
            if (!ctx) {
                console.log('[Alert Box Debug] No AudioContext returned');
                return;
            }
            const now = ctx.currentTime;
            
            if (type === 'success') {
                // Success: Double pleasant E5 -> A5 sine chime
                const osc1 = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, now); // E5
                osc1.frequency.setValueAtTime(880.00, now + 0.1); // A5
                
                gain.gain.setValueAtTime(0, now);
                gain.gain.linearRampToValueAtTime(0.15, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);
                
                osc1.connect(gain);
                gain.connect(ctx.destination);
                
                osc1.start(now);
                osc1.stop(now + 0.5);
                console.log('[Alert Box Debug] Success chime started');
            } else if (type === 'error') {
                // Error: Low warning chime descending F3 -> C3 triangle/sawtooth
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(174.61, now); // F3
                osc.frequency.linearRampToValueAtTime(130.81, now + 0.25); // C3
                
                gain.gain.setValueAtTime(0, now);
                gain.gain.linearRampToValueAtTime(0.2, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                
                osc.start(now);
                osc.stop(now + 0.45);
                console.log('[Alert Box Debug] Error chime started');
            } else {
                // Info: Mild soft sine chime
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc.type = 'sine';
                osc.frequency.setValueAtTime(440.00, now); // A4
                
                gain.gain.setValueAtTime(0, now);
                gain.gain.linearRampToValueAtTime(0.15, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                
                osc.start(now);
                osc.stop(now + 0.4);
                console.log('[Alert Box Debug] Info chime started');
            }
        } catch (e) {
            console.warn('[Alert Box Debug] Audio synthesis blocked or unsupported:', e);
        }
    }

    // Modal SVG Icons
    const icons = {
        success: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:32px; height:32px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>`,
        error: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:32px; height:32px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>`,
        info: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:32px; height:32px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.085 1.086L12.75 14.25v2.25A.75.75 0 0112 17.25h-.75a.75.75 0 010-1.5h.75v-1.5h-.75a.75.75 0 01-.75-.75zM12 7.5a1 1 0 110-2 1 1 0 010 2z" />
        </svg>`
    };

    function getRealElement(id) {
        const el = document.getElementById(id);
        return (el && el instanceof HTMLElement) ? el : null;
    }

    function createAlertDOM() {
        console.log('[Alert Box Debug] createAlertDOM called');
        let overlay = getRealElement('custom-alert-overlay');
        if (!overlay) {
            console.log('[Alert Box Debug] Creating overlay element');
            overlay = document.createElement('div');
            overlay.id = 'custom-alert-overlay';
            overlay.className = 'custom-alert-overlay';
            
            // Inline style fallback for reliability
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100vw';
            overlay.style.height = '100vh';
            overlay.style.background = 'rgba(10, 10, 18, 0.75)';
            overlay.style.backdropFilter = 'blur(14px) saturate(180%)';
            overlay.style.webkitBackdropFilter = 'blur(14px) saturate(180%)';
            overlay.style.display = 'none';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '999999';
            overlay.style.transition = 'opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            overlay.style.opacity = '0';
            overlay.style.pointerEvents = 'none';

            overlay.innerHTML = `
                <div class="custom-alert-box" id="custom-alert-box" style="
                    background: rgba(22, 22, 34, 0.95);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 24px;
                    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
                    width: 90%;
                    max-width: 400px;
                    padding: 32px;
                    text-align: center;
                    transform: scale(0.9) translateY(30px);
                    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    box-sizing: border-box;
                ">
                    <div class="custom-alert-icon-wrapper" id="custom-alert-icon-wrapper" style="
                        width: 72px;
                        height: 72px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin-bottom: 20px;
                        position: relative;
                        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
                        box-sizing: border-box;
                    "></div>
                    <h3 class="custom-alert-title" id="custom-alert-title" style="
                        font-family: 'Outfit', 'Inter', -apple-system, sans-serif;
                        font-size: 1.4rem;
                        font-weight: 700;
                        color: #ffffff;
                        margin: 0 0 10px 0;
                        letter-spacing: -0.02em;
                    "></h3>
                    <div class="custom-alert-message" id="custom-alert-message" style="
                        font-family: 'Inter', -apple-system, sans-serif;
                        font-size: 0.95rem;
                        line-height: 1.6;
                        color: rgba(255, 255, 255, 0.7);
                        margin: 0 0 26px 0;
                        max-height: 180px;
                        overflow-y: auto;
                        width: 100%;
                        word-break: break-word;
                    "></div>
                    <button class="custom-alert-btn" id="custom-alert-btn" style="
                        border: none;
                        border-radius: 14px;
                        padding: 14px 28px;
                        font-family: 'Outfit', 'Inter', -apple-system, sans-serif;
                        font-weight: 600;
                        font-size: 0.95rem;
                        color: #ffffff;
                        cursor: pointer;
                        transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
                        outline: none;
                        width: 100%;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        letter-spacing: 0.02em;
                        box-sizing: border-box;
                    ">Dismiss</button>
                </div>
            `;
            document.body.appendChild(overlay);

            const dismissBtn = overlay.querySelector('#custom-alert-btn');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', function() {
                    console.log('[Alert Box Debug] Dismiss button clicked');
                    closeAlert();
                });
            }
            
            // Allow closing on hitting Escape or Enter
            window.addEventListener('keydown', function(e) {
                if (overlay.classList.contains('active') && (e.key === 'Escape' || e.key === 'Enter')) {
                    console.log('[Alert Box Debug] Escape/Enter keydown detected');
                    e.preventDefault();
                    closeAlert();
                }
            });
        }
        return overlay;
    }

    function showCustomAlert(message) {
        console.log('[Alert Box Debug] showCustomAlert called with message:', message);
        try {
            const overlay = createAlertDOM();

            const box = overlay.querySelector('#custom-alert-box');
            const iconWrapper = overlay.querySelector('#custom-alert-icon-wrapper');
            const titleEl = overlay.querySelector('#custom-alert-title');
            const messageEl = overlay.querySelector('#custom-alert-message');
            const btn = overlay.querySelector('#custom-alert-btn');

            // Analyze message to determine type
            const lower = (message || '').toLowerCase();
            let type = 'info';
            let title = 'Notification';
            let buttonText = 'Dismiss';

            // Custom color and theme styles for inline fallback
            let themeColor = '#3b82f6';
            let iconGradient = 'linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.35))';
            let btnGradient = 'linear-gradient(135deg, #3b82f6, #2563eb)';
            let iconBorder = 'rgba(59, 130, 246, 0.4)';

            if (lower.includes('success') || lower.includes('sent') || lower.includes('saved') || lower.includes('updated') || lower.includes('renewed') || lower.includes('reset')) {
                type = 'success';
                title = 'Success';
                buttonText = 'Awesome';
                themeColor = '#10b981';
                iconGradient = 'linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(5, 150, 105, 0.35))';
                btnGradient = 'linear-gradient(135deg, #10b981, #059669)';
                iconBorder = 'rgba(16, 185, 129, 0.4)';
            } else if (lower.includes('failed') || lower.includes('error') || lower.includes('invalid') || lower.includes('limit') || lower.includes('expire') || lower.includes('over') || lower.includes('forbidden') || lower.includes('unauthorized') || lower.includes('denied')) {
                type = 'error';
                title = 'Action Blocked';
                buttonText = 'Close';
                themeColor = '#f43f5e';
                iconGradient = 'linear-gradient(135deg, rgba(244, 63, 94, 0.25), rgba(225, 29, 72, 0.35))';
                btnGradient = 'linear-gradient(135deg, #f43f5e, #e11d48)';
                iconBorder = 'rgba(244, 63, 94, 0.4)';
            }

            console.log('[Alert Box Debug] Setting alert type to:', type);

            // Apply classes and content
            if (box) {
                box.className = `custom-alert-box ${type}`;
                box.style.borderColor = type === 'success' ? 'rgba(16, 185, 129, 0.25)' : (type === 'error' ? 'rgba(244, 63, 94, 0.25)' : 'rgba(59, 130, 246, 0.25)');
            }
            
            if (iconWrapper) {
                iconWrapper.innerHTML = icons[type];
                iconWrapper.style.background = iconGradient;
                iconWrapper.style.border = `1px solid ${iconBorder}`;
                iconWrapper.style.color = themeColor;
            }
            
            if (titleEl) titleEl.textContent = title;
            if (messageEl) messageEl.innerHTML = (message || '').replace(/\n/g, '<br>');
            
            if (btn) {
                btn.textContent = buttonText;
                btn.style.background = btnGradient;
                
                // Hover effect simulation in Javascript
                btn.onmouseover = () => { btn.style.transform = 'translateY(-2px)'; btn.style.filter = 'brightness(1.1)'; };
                btn.onmouseout = () => { btn.style.transform = 'none'; btn.style.filter = 'none'; };
            }

            // Play chime audio
            playChime(type);

            // Display modal with animation
            overlay.style.display = 'flex';
            overlay.classList.add('active');
            
            // Force reflow and transition
            overlay.offsetHeight;
            overlay.style.opacity = '1';
            overlay.style.pointerEvents = 'auto';

            if (box) {
                box.style.transform = 'scale(1) translateY(0)';
            }

            console.log('[Alert Box Debug] Overlay activated and transitioned');
            
            // Focus the button for direct keyboard accessibility
            if (btn) {
                btn.focus();
                console.log('[Alert Box Debug] Button focused');
            }
        } catch (err) {
            console.error('[Alert Box Debug] Error inside showCustomAlert:', err);
        }
    }

    function closeAlert() {
        console.log('[Alert Box Debug] closeAlert called');
        const overlay = getRealElement('custom-alert-overlay');
        const box = getRealElement('custom-alert-box');
        
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.opacity = '0';
            overlay.style.pointerEvents = 'none';
        }
        if (box) {
            box.style.transform = 'scale(0.9) translateY(30px)';
        }
        
        // Wait for CSS scale/fade transitions (300ms) before processing next alert in queue
        setTimeout(() => {
            if (overlay) {
                overlay.style.display = 'none';
            }
            isAlertActive = false;
            console.log('[Alert Box Debug] Active status reset. Processing next in queue');
            processQueue();
        }, 300);
    }

    function processQueue() {
        console.log('[Alert Box Debug] processQueue called. Active:', isAlertActive, 'Queue size:', alertQueue.length);
        if (isAlertActive || alertQueue.length === 0) return;
        isAlertActive = true;
        const msg = alertQueue.shift();
        showCustomAlert(msg);
    }

    // Override global window.alert
    console.log('[Alert Box Debug] Overriding window.alert');
    window.alert = function(message) {
        console.log('[Alert Box Debug] window.alert intercepted. Message:', message);
        // Enforce string conversion
        const msgStr = typeof message === 'string' ? message : String(message || '');
        alertQueue.push(msgStr);
        processQueue();
    };

    // Log activation to developer console
    console.log('%c[Alert Box Plugin]%c Activated and overriding window.alert', 'color: #818cf8; font-weight: bold;', 'color: inherit;');
})();
