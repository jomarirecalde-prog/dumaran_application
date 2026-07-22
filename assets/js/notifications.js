/**
 * Notification System JavaScript
 * Handles notification bell, dropdown, and API interactions
 */

class NotificationManager {
    constructor() {
        this.apiUrl = BASE_URL + 'api/notifications.php';
        this.sseUrl = BASE_URL + 'api/notifications_sse.php';
        this.eventSource = null;
        this.lastNotificationId = 0;
        this.reconnectDelay = 3000; // 3 seconds
        this.reconnectTimer = null;
        this.notificationSound = null;
        this.audioUnlocked = false;
        this.lastUnreadCount = -1; // Track previous count to detect new notifications
        this.init();
    }

    init() {
        // Initialize notification sound
        this.initNotificationSound();
        // Setup audio unlock on first user interaction
        this.setupAudioUnlock();
        // Load initial unread count immediately
        this.getUnreadCount();
        this.loadNotifications();
        this.setupEventListeners();
        this.startSSE();
    }

    initNotificationSound() {
        try {
            // Create audio element for notification sound
            this.notificationSound = new Audio(BASE_URL + 'sound/new-notification-09-352705.mp3');
            this.notificationSound.preload = 'auto';
            this.notificationSound.volume = 0.7; // Set volume to 70%
            
            // Add load event listener
            this.notificationSound.addEventListener('canplaythrough', () => {
                console.log('Notification sound loaded successfully');
            });
            
            this.notificationSound.addEventListener('error', (e) => {
                console.error('Error loading notification sound:', e);
            });
        } catch (error) {
            console.warn('Error initializing notification sound:', error);
            this.notificationSound = null;
        }
    }

    setupAudioUnlock() {
        // Browsers require user interaction before playing audio
        // This unlocks the audio on first click/touch/keypress
        const unlockAudio = () => {
            if (this.audioUnlocked) return;
            
            if (this.notificationSound) {
                // Play and immediately pause to unlock audio
                this.notificationSound.play().then(() => {
                    this.notificationSound.pause();
                    this.notificationSound.currentTime = 0;
                    this.audioUnlocked = true;
                    console.log('Audio unlocked - notification sounds enabled');
                }).catch(() => {
                    // Still try again on next interaction
                });
            }
        };

        // Listen for various user interactions
        ['click', 'touchstart', 'keydown'].forEach(event => {
            document.addEventListener(event, unlockAudio, { once: false, passive: true });
        });
    }

    playNotificationSound() {
        if (!this.notificationSound) {
            // Try to reinitialize if sound was not loaded
            this.initNotificationSound();
            if (!this.notificationSound) {
                return;
            }
        }

        try {
            // Clone the audio to allow overlapping sounds
            const sound = this.notificationSound.cloneNode();
            sound.volume = 0.7;
            sound.play().then(() => {
                console.log('Notification sound played');
            }).catch(error => {
                console.warn('Could not play notification sound:', error);
            });
        } catch (error) {
            console.warn('Error playing notification sound:', error);
        }
    }

    setupEventListeners() {
        // Toggle dropdown when clicking bell
        const bell = document.getElementById('notificationBell');
        if (bell) {
            bell.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleDropdown();
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.getElementById('notificationBell');
            if (dropdown && bell && !dropdown.contains(e.target) && !bell.contains(e.target)) {
                this.closeDropdown();
            }
        });

        // Mark all as read button
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markAllAsRead();
            });
        }
    }

    async loadNotifications(showDropdown = false) {
        try {
            const response = await fetch(`${this.apiUrl}?action=index&limit=10`);
            const data = await response.json();
            
            if (data.success && data.data) {
                // Update last notification ID
                if (data.data.length > 0) {
                    const maxId = Math.max(...data.data.map(n => n.id));
                    this.lastNotificationId = Math.max(this.lastNotificationId, maxId);
                }
                
                this.updateBadge(data.data.filter(n => !n.is_read).length);
                this.renderNotifications(data.data);
                
                if (showDropdown) {
                    this.showDropdown();
                }
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    async getUnreadCount() {
        try {
            const response = await fetch(`${this.apiUrl}?action=unread-count`);
            const data = await response.json();
            
            if (data.success && data.data) {
                this.updateBadge(data.data.count);
            }
        } catch (error) {
            console.error('Error getting unread count:', error);
        }
    }

    updateBadge(count, playSoundOnIncrease = true) {
        const badge = document.getElementById('notificationBadge');
        
        // Check if unread count increased (new notification arrived)
        if (playSoundOnIncrease && this.lastUnreadCount >= 0 && count > this.lastUnreadCount) {
            // Play sound for each new notification
            this.playNotificationSound();
        }
        
        // Update last count (only after initial load)
        if (this.lastUnreadCount === -1) {
            // First load - just store the count without playing sound
            this.lastUnreadCount = count;
        } else {
            this.lastUnreadCount = count;
        }
        
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count.toString();
                badge.style.display = 'flex'; // Use flex to center content
                // Add pulse animation when count changes
                badge.classList.add('badge-update');
                setTimeout(() => {
                    badge.classList.remove('badge-update');
                }, 500);
            } else {
                badge.style.display = 'none';
            }
        }
    }

    renderNotifications(notifications) {
        const list = document.getElementById('notificationList');
        if (!list) return;

        if (notifications.length === 0) {
            list.innerHTML = '<div class="notification-empty">No notifications</div>';
            return;
        }

        list.innerHTML = notifications.map(notification => {
            const timeAgo = this.formatTimeAgo(notification.created_at);
            const unreadClass = notification.is_read ? '' : 'unread';
            const link = notification.related_type === 'application' && notification.related_id
                ? `href="${BASE_URL}admin/pages/applications.php?application_id=${notification.related_id}"`
                : 'href="#"';

            return `
                <a class="notification-item ${unreadClass}" ${link} onclick="notificationManager.markAsRead(${notification.id}, event)">
                    <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                    <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                    <div class="notification-time">${timeAgo}</div>
                </a>
            `;
        }).join('');
    }

    async markAsRead(notificationId, event) {
        if (event) {
            event.preventDefault();
        }

        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            formData.append('csrf_token', this.getCsrfToken());

            const response = await fetch(`${this.apiUrl}?action=mark-read`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                // Reload notifications
                this.loadNotifications();
                
                // Update badge
                this.getUnreadCount();
                
                // If it was a link click, navigate to the link
                if (event && event.currentTarget) {
                    const href = event.currentTarget.getAttribute('href');
                    if (href && href !== '#') {
                        window.location.href = href;
                    }
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const formData = new FormData();
            formData.append('csrf_token', this.getCsrfToken());

            const response = await fetch(`${this.apiUrl}?action=mark-all-read`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.loadNotifications();
                this.getUnreadCount();
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }

    toggleDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            if (dropdown.classList.contains('show')) {
                this.closeDropdown();
            } else {
                this.loadNotifications(true);
            }
        }
    }

    showDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.add('show');
        }
    }

    closeDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }

    startSSE() {
        // Close existing connection if any
        this.stopSSE();

        // Build SSE URL with last notification ID
        const url = `${this.sseUrl}?last_notification_id=${this.lastNotificationId}`;

        try {
            this.eventSource = new EventSource(url);

            // Handle connection established
            this.eventSource.addEventListener('connected', (e) => {
                const data = JSON.parse(e.data);
                if (data.success) {
                    console.log('Connected to real-time notifications');
                    if (data.unread_count !== undefined) {
                        this.updateBadge(data.unread_count);
                    } else {
                        // If unread_count not in initial message, fetch it
                        this.getUnreadCount();
                    }
                }
            });

            // Handle new notification
            this.eventSource.addEventListener('notification', (e) => {
                const data = JSON.parse(e.data);
                if (data.success && data.data) {
                    const notification = data.data;
                    
                    // Update last notification ID
                    this.lastNotificationId = Math.max(this.lastNotificationId, notification.id);
                    
                    // Update badge - sound will be played by updateBadge when count increases
                    this.getUnreadCount();
                    
                    // If dropdown is open, reload notifications
                    const dropdown = document.getElementById('notificationDropdown');
                    if (dropdown && dropdown.classList.contains('show')) {
                        this.loadNotifications(true);
                    } else {
                        // Show visual indicator for new notification
                        this.showNewNotificationIndicator();
                    }
                }
            });

            // Handle unread count update
            this.eventSource.addEventListener('unread_count', (e) => {
                const data = JSON.parse(e.data);
                if (data.success && data.count !== undefined) {
                    this.updateBadge(data.count);
                }
            });

            // Handle reconnect event
            this.eventSource.addEventListener('reconnect', (e) => {
                const data = JSON.parse(e.data);
                if (data.last_notification_id) {
                    this.lastNotificationId = data.last_notification_id;
                }
                // Automatically reconnect after a short delay
                this.scheduleReconnect();
            });

            // Handle errors
            this.eventSource.addEventListener('error', (e) => {
                console.error('SSE error:', e);
                this.scheduleReconnect();
            });

            // Handle generic error event
            this.eventSource.onerror = (e) => {
                console.error('SSE connection error:', e);
                // Close and reconnect
                this.scheduleReconnect();
            };

        } catch (error) {
            console.error('Error starting SSE:', error);
            // Fallback to polling if SSE fails
            this.fallbackToPolling();
        }
    }

    stopSSE() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }
    }

    scheduleReconnect() {
        // Stop current connection
        this.stopSSE();
        
        // Schedule reconnect
        this.reconnectTimer = setTimeout(() => {
            console.log('Reconnecting to notifications SSE...');
            this.startSSE();
        }, this.reconnectDelay);
    }

    fallbackToPolling() {
        console.log('Falling back to polling for notifications');
        // Fallback to polling every 30 seconds
        setInterval(() => {
            this.getUnreadCount();
        }, 30000);
    }

    showNewNotificationIndicator() {
        const bell = document.getElementById('notificationBell');
        if (bell) {
            // Add a pulse animation class
            bell.classList.add('notification-pulse');
            setTimeout(() => {
                bell.classList.remove('notification-pulse');
            }, 2000);
        }
    }

    formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) {
            return 'Just now';
        } else if (diffMins < 60) {
            return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        } else if (diffHours < 24) {
            return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        } else if (diffDays < 7) {
            return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        } else {
            return date.toLocaleDateString();
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getCsrfToken() {
        // Try to get CSRF token from meta tag or global variable
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }
        
        // Fallback: try to get from window object
        if (typeof window.csrfToken !== 'undefined') {
            return window.csrfToken;
        }
        
        return '';
    }
}

// Initialize notification manager when DOM is ready
let notificationManager;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        notificationManager = new NotificationManager();
    });
} else {
    notificationManager = new NotificationManager();
}

// Clean up SSE connection when page is unloading
window.addEventListener('beforeunload', () => {
    if (notificationManager) {
        notificationManager.stopSSE();
    }
});

