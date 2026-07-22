/**
 * WPU Jobs - PWA Helper
 * Handles service worker registration, install prompts, and update notifications
 */

class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.swRegistration = null;
        this.isInstallable = false;
        this.isInstalled = false;
        this.basePath = window.PWA_BASE_PATH || '/Applicant_WPU/';
        this.baseUrl = window.PWA_BASE_URL || 'http://localhost/Applicant_WPU/';
        
        this.init();
    }
    
    init() {
        // Check if already installed
        this.checkIfInstalled();
        
        // Register service worker
        this.registerServiceWorker();
        
        // Listen for install prompt
        this.setupInstallPrompt();
        
        // Create install UI
        this.createInstallUI();
        
        // Setup online/offline handlers
        this.setupConnectionHandlers();
    }
    
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.log('[PWA] Service workers not supported');
            return;
        }
        
        try {
            this.swRegistration = await navigator.serviceWorker.register(this.basePath + 'sw.js', {
                scope: this.basePath
            });
            
            console.log('[PWA] Service worker registered:', this.swRegistration.scope);
            
            // Check for updates
            this.swRegistration.addEventListener('updatefound', () => {
                const newWorker = this.swRegistration.installing;
                
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdateNotification();
                    }
                });
            });
            
            // Handle controller change
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                console.log('[PWA] Controller changed, reloading...');
            });
            
        } catch (error) {
            console.error('[PWA] Service worker registration failed:', error);
        }
    }
    
    checkIfInstalled() {
        // Check if running in standalone mode (installed)
        if (window.matchMedia('(display-mode: standalone)').matches) {
            this.isInstalled = true;
            document.body.classList.add('pwa-standalone');
        }
        
        // iOS Safari check
        if (window.navigator.standalone === true) {
            this.isInstalled = true;
            document.body.classList.add('pwa-standalone');
        }
    }
    
    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent default browser prompt
            e.preventDefault();
            
            // Store the event for later use
            this.deferredPrompt = e;
            this.isInstallable = true;
            
            // Show install button
            this.showInstallButton();
            
            console.log('[PWA] Install prompt available');
        });
        
        // Listen for successful install
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App installed successfully');
            this.isInstalled = true;
            this.isInstallable = false;
            this.deferredPrompt = null;
            this.hideInstallButton();
            this.showInstallSuccess();
        });
    }
    
    createInstallUI() {
        // Create floating install button
        const installBtn = document.createElement('button');
        installBtn.id = 'pwa-install-btn';
        installBtn.className = 'pwa-install-button';
        installBtn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span>Install App</span>
        `;
        installBtn.addEventListener('click', () => this.promptInstall());
        
        // Create install banner for mobile
        const installBanner = document.createElement('div');
        installBanner.id = 'pwa-install-banner';
        installBanner.className = 'pwa-install-banner';
        installBanner.innerHTML = `
            <div class="pwa-banner-content">
                <img src="${this.basePath}assets/logo.png" alt="WPU" class="pwa-banner-icon">
                <div class="pwa-banner-text">
                    <strong>Install WPU Jobs</strong>
                    <span>Add to home screen for quick access</span>
                </div>
            </div>
            <div class="pwa-banner-actions">
                <button class="pwa-banner-close" aria-label="Close">×</button>
                <button class="pwa-banner-install">Install</button>
            </div>
        `;
        
        // Add to DOM
        document.body.appendChild(installBtn);
        document.body.appendChild(installBanner);
        
        // Banner event listeners
        installBanner.querySelector('.pwa-banner-close').addEventListener('click', () => {
            this.hideInstallBanner();
            // Don't show banner again for 7 days
            localStorage.setItem('pwa-banner-dismissed', Date.now());
        });
        
        installBanner.querySelector('.pwa-banner-install').addEventListener('click', () => {
            this.promptInstall();
        });
        
        // Add styles
        this.injectStyles();
    }
    
    injectStyles() {
        const styles = document.createElement('style');
        styles.textContent = `
            /* PWA Install Button - Hidden by default, shown only on mobile */
            .pwa-install-button {
                display: none !important;
                position: fixed;
                bottom: 100px;
                right: 16px;
                z-index: 9999;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
                color: white;
                border: none;
                padding: 14px;
                border-radius: 50%;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 20px rgba(30, 58, 95, 0.4);
                transition: all 0.3s ease;
                animation: pwa-bounce 2s infinite;
            }
            
            .pwa-install-button span {
                display: none;
            }
            
            .pwa-install-button:hover {
                transform: translateY(-2px) scale(1.02);
                box-shadow: 0 6px 30px rgba(30, 58, 95, 0.5);
            }
            
            @keyframes pwa-bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-5px); }
            }
            
            /* PWA Install Banner - Hidden by default, shown only on mobile */
            .pwa-install-banner {
                display: none !important;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 10000;
                background: white;
                padding: 16px 20px;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
                animation: pwa-slide-up 0.4s ease-out;
            }
            
            /* Show install UI only on phone-sized screens (max 576px) */
            @media (max-width: 576px) {
                .pwa-install-button.pwa-show {
                    display: flex !important;
                }
                
                .pwa-install-banner.pwa-show {
                    display: flex !important;
                }
            }
            
            @keyframes pwa-slide-up {
                from {
                    transform: translateY(100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .pwa-banner-content {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .pwa-banner-icon {
                width: 48px;
                height: 48px;
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            
            .pwa-banner-text {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            
            .pwa-banner-text strong {
                font-size: 15px;
                color: #1e3a5f;
            }
            
            .pwa-banner-text span {
                font-size: 13px;
                color: #64748b;
            }
            
            .pwa-banner-actions {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .pwa-banner-close {
                background: none;
                border: none;
                font-size: 24px;
                color: #94a3b8;
                cursor: pointer;
                padding: 4px;
                line-height: 1;
            }
            
            .pwa-banner-install {
                background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .pwa-banner-install:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
            }
            
            /* PWA Update Toast */
            .pwa-update-toast {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 10001;
                background: #1e3a5f;
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                gap: 16px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
                animation: pwa-fade-in 0.3s ease-out;
            }
            
            @keyframes pwa-fade-in {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
            
            .pwa-update-toast button {
                background: white;
                color: #1e3a5f;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .pwa-update-toast button:hover {
                background: #f1f5f9;
            }
            
            /* Offline indicator */
            .pwa-offline-indicator {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10002;
                background: #ef4444;
                color: white;
                text-align: center;
                padding: 8px;
                font-size: 14px;
                font-weight: 500;
                animation: pwa-slide-down 0.3s ease-out;
            }
            
            @keyframes pwa-slide-down {
                from {
                    transform: translateY(-100%);
                }
                to {
                    transform: translateY(0);
                }
            }
            
            /* Hide install UI when in standalone mode */
            .pwa-standalone .pwa-install-button,
            .pwa-standalone .pwa-install-banner {
                display: none !important;
            }
        `;
        document.head.appendChild(styles);
    }
    
    showInstallButton() {
        if (this.isInstalled) return;
        
        const btn = document.getElementById('pwa-install-btn');
        if (btn) {
            btn.classList.add('pwa-show');
        }
        
        // Show banner on mobile if not dismissed recently
        const dismissed = localStorage.getItem('pwa-banner-dismissed');
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        
        if (!dismissed || (Date.now() - parseInt(dismissed)) > sevenDays) {
            setTimeout(() => this.showInstallBanner(), 3000);
        }
    }
    
    hideInstallButton() {
        const btn = document.getElementById('pwa-install-btn');
        if (btn) {
            btn.classList.remove('pwa-show');
        }
    }
    
    showInstallBanner() {
        if (this.isInstalled) return;
        
        const banner = document.getElementById('pwa-install-banner');
        if (banner && this.isInstallable) {
            banner.classList.add('pwa-show');
        }
    }
    
    hideInstallBanner() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) {
            banner.classList.remove('pwa-show');
        }
    }
    
    async promptInstall() {
        if (!this.deferredPrompt) {
            // Show instructions for iOS
            if (this.isIOS()) {
                this.showIOSInstructions();
            }
            return;
        }
        
        try {
            // Show the install prompt
            this.deferredPrompt.prompt();
            
            // Wait for user response
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log('[PWA] Install prompt outcome:', outcome);
            
            if (outcome === 'accepted') {
                this.hideInstallButton();
                this.hideInstallBanner();
            }
            
            // Clear the prompt
            this.deferredPrompt = null;
            
        } catch (error) {
            console.error('[PWA] Install prompt error:', error);
        }
    }
    
    isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }
    
    showIOSInstructions() {
        const modal = document.createElement('div');
        modal.className = 'pwa-ios-modal';
        modal.innerHTML = `
            <div class="pwa-ios-content">
                <button class="pwa-ios-close">×</button>
                <img src="${this.basePath}assets/logo.png" alt="WPU" class="pwa-ios-icon">
                <h3>Install WPU Jobs</h3>
                <p>To install this app on your iPhone/iPad:</p>
                <ol>
                    <li>Tap the <strong>Share</strong> button <span style="font-size: 18px;">⎋</span></li>
                    <li>Scroll down and tap <strong>"Add to Home Screen"</strong></li>
                    <li>Tap <strong>"Add"</strong> in the top right</li>
                </ol>
            </div>
        `;
        
        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .pwa-ios-modal {
                position: fixed;
                inset: 0;
                z-index: 10003;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                animation: pwa-fade-in 0.3s ease-out;
            }
            
            .pwa-ios-content {
                background: white;
                border-radius: 16px;
                padding: 32px;
                max-width: 340px;
                text-align: center;
                position: relative;
            }
            
            .pwa-ios-close {
                position: absolute;
                top: 12px;
                right: 12px;
                background: none;
                border: none;
                font-size: 28px;
                color: #94a3b8;
                cursor: pointer;
            }
            
            .pwa-ios-icon {
                width: 64px;
                height: 64px;
                border-radius: 14px;
                margin-bottom: 16px;
            }
            
            .pwa-ios-content h3 {
                font-size: 20px;
                color: #1e3a5f;
                margin-bottom: 12px;
            }
            
            .pwa-ios-content p {
                color: #64748b;
                margin-bottom: 16px;
            }
            
            .pwa-ios-content ol {
                text-align: left;
                padding-left: 20px;
                color: #475569;
            }
            
            .pwa-ios-content li {
                margin-bottom: 12px;
                line-height: 1.5;
            }
        `;
        document.head.appendChild(style);
        document.body.appendChild(modal);
        
        // Close handlers
        modal.querySelector('.pwa-ios-close').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
    }
    
    showInstallSuccess() {
        if (typeof showToast === 'function') {
            showToast('App installed successfully! 🎉', 'success');
        }
    }
    
    showUpdateNotification() {
        const toast = document.createElement('div');
        toast.className = 'pwa-update-toast';
        toast.innerHTML = `
            <span>A new version is available!</span>
            <button onclick="window.location.reload()">Update Now</button>
        `;
        document.body.appendChild(toast);
        
        // Auto-hide after 10 seconds
        setTimeout(() => toast.remove(), 10000);
    }
    
    setupConnectionHandlers() {
        let offlineIndicator = null;
        
        const showOfflineIndicator = () => {
            if (offlineIndicator) return;
            
            offlineIndicator = document.createElement('div');
            offlineIndicator.className = 'pwa-offline-indicator';
            offlineIndicator.textContent = '⚠️ You are currently offline';
            document.body.appendChild(offlineIndicator);
        };
        
        const hideOfflineIndicator = () => {
            if (offlineIndicator) {
                offlineIndicator.remove();
                offlineIndicator = null;
            }
        };
        
        window.addEventListener('online', () => {
            hideOfflineIndicator();
            if (typeof showToast === 'function') {
                showToast('Back online!', 'success');
            }
        });
        
        window.addEventListener('offline', () => {
            showOfflineIndicator();
            if (typeof showToast === 'function') {
                showToast('You are offline. Some features may be limited.', 'warning');
            }
        });
        
        // Check initial state
        if (!navigator.onLine) {
            showOfflineIndicator();
        }
    }
}

// Initialize PWA when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
});

