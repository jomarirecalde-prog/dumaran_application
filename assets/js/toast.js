/**
 * WPU Applicant System - Toast Notification System
 * Modern alert and confirmation dialogs that appear at the top right
 */

class ToastNotification {
    constructor() {
        this.createContainer();
        this.toasts = [];
    }

    createContainer() {
        if (!document.getElementById('toast-container-custom')) {
            const container = document.createElement('div');
            container.id = 'toast-container-custom';
            container.className = 'toast-container-custom';
            document.body.appendChild(container);
        }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - success, error, warning, info
     * @param {number} duration - Duration in milliseconds (default: 3000)
     * @param {string} title - Optional title for the toast
     */
    show(message, type = 'info', duration = 3000, title = null) {
        const container = document.getElementById('toast-container-custom');
        if (!container) {
            this.createContainer();
        }

        // Determine icon and default title based on type
        let icon, defaultTitle;
        switch (type) {
            case 'success':
                icon = 'fas fa-check-circle';
                defaultTitle = 'Success';
                break;
            case 'error':
                icon = 'fas fa-exclamation-circle';
                defaultTitle = 'Error';
                break;
            case 'warning':
                icon = 'fas fa-exclamation-triangle';
                defaultTitle = 'Warning';
                break;
            case 'info':
            default:
                icon = 'fas fa-info-circle';
                defaultTitle = 'Info';
                break;
        }

        title = title || defaultTitle;

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-notification-icon">
                <i class="${icon}"></i>
            </div>
            <div class="toast-notification-content">
                <div class="toast-notification-title">${this.escapeHtml(title)}</div>
                <div class="toast-notification-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="toast-notification-close" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Add to container
        const toastContainer = document.getElementById('toast-container-custom');
        toastContainer.appendChild(toast);

        // Store reference
        const toastObj = {
            element: toast,
            timeout: null
        };
        this.toasts.push(toastObj);

        // Close on click
        toast.addEventListener('click', () => {
            this.removeToast(toastObj);
        });

        // Close button
        const closeBtn = toast.querySelector('.toast-notification-close');
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.removeToast(toastObj);
        });

        // Auto-remove after duration
        toastObj.timeout = setTimeout(() => {
            this.removeToast(toastObj);
        }, duration);

        return toastObj;
    }

    removeToast(toastObj) {
        if (toastObj.timeout) {
            clearTimeout(toastObj.timeout);
        }

        toastObj.element.classList.add('toast-closing');
        
        setTimeout(() => {
            if (toastObj.element.parentNode) {
                toastObj.element.parentNode.removeChild(toastObj.element);
            }
            const index = this.toasts.indexOf(toastObj);
            if (index > -1) {
                this.toasts.splice(index, 1);
            }
        }, 300);
    }

    success(message, duration = 3000, title = null) {
        return this.show(message, 'success', duration, title);
    }

    error(message, duration = 4000, title = null) {
        return this.show(message, 'error', duration, title);
    }

    warning(message, duration = 3500, title = null) {
        return this.show(message, 'warning', duration, title);
    }

    info(message, duration = 3000, title = null) {
        return this.show(message, 'info', duration, title);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

class ConfirmDialog {
    /**
     * Show a confirmation dialog
     * @param {Object} options - Configuration options
     * @param {string} options.message - The confirmation message
     * @param {string} options.title - Dialog title (default: 'Confirm')
     * @param {string} options.type - warning, danger, info (default: 'warning')
     * @param {string} options.confirmText - Confirm button text (default: 'Confirm')
     * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
     * @param {Function} options.onConfirm - Callback when confirmed
     * @param {Function} options.onCancel - Callback when cancelled
     * @returns {Promise} Promise that resolves with true/false
     */
    static show(options = {}) {
        return new Promise((resolve) => {
            const {
                message = 'Are you sure?',
                title = 'Confirm',
                type = 'warning',
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                onConfirm = null,
                onCancel = null
            } = options;

            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'confirm-dialog-overlay';

            // Determine icon
            let iconClass;
            switch (type) {
                case 'danger':
                    iconClass = 'fas fa-exclamation-triangle';
                    break;
                case 'info':
                    iconClass = 'fas fa-info-circle';
                    break;
                case 'warning':
                default:
                    iconClass = 'fas fa-exclamation-triangle';
                    break;
            }

            // Create dialog
            // Format message: escape HTML then convert newlines to <br>
            const formattedMessage = this.escapeHtml(message).replace(/\n/g, '<br>');
            
            overlay.innerHTML = `
                <div class="confirm-dialog">
                    <div class="confirm-dialog-header">
                        <div class="confirm-dialog-icon ${type}">
                            <i class="${iconClass}"></i>
                        </div>
                        <h3 class="confirm-dialog-title">${this.escapeHtml(title)}</h3>
                    </div>
                    <div class="confirm-dialog-body">
                        <p class="confirm-dialog-message">${formattedMessage}</p>
                    </div>
                    <div class="confirm-dialog-footer">
                        <button class="btn btn-secondary confirm-cancel" type="button">
                            <i class="fas fa-times"></i> ${this.escapeHtml(cancelText)}
                        </button>
                        <button class="btn ${type === 'danger' ? 'btn-danger' : 'btn-primary'} confirm-ok" type="button">
                            <i class="fas fa-check"></i> ${this.escapeHtml(confirmText)}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            const dialog = overlay.querySelector('.confirm-dialog');
            const confirmBtn = overlay.querySelector('.confirm-ok');
            const cancelBtn = overlay.querySelector('.confirm-cancel');

            // Prevent click propagation on dialog
            dialog.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Close on overlay click
            overlay.addEventListener('click', () => {
                this.close(overlay, false, resolve, onCancel);
            });

            // Confirm button
            confirmBtn.addEventListener('click', () => {
                this.close(overlay, true, resolve, onConfirm);
            });

            // Cancel button
            cancelBtn.addEventListener('click', () => {
                this.close(overlay, false, resolve, onCancel);
            });

            // ESC key to cancel
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    this.close(overlay, false, resolve, onCancel);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);

            // Focus confirm button
            setTimeout(() => {
                confirmBtn.focus();
            }, 100);
        });
    }

    static close(overlay, result, resolve, callback) {
        overlay.style.animation = 'fadeOut 0.2s ease';
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            if (callback) {
                callback();
            }
            resolve(result);
        }, 200);
    }

    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Create global instance
const toast = new ToastNotification();

// Global functions for easy access
window.showToast = (message, type = 'info', duration = 3000, title = null) => {
    return toast.show(message, type, duration, title);
};

window.showSuccess = (message, duration = 3000, title = null) => {
    return toast.success(message, duration, title);
};

window.showError = (message, duration = 4000, title = null) => {
    return toast.error(message, duration, title);
};

window.showWarning = (message, duration = 3500, title = null) => {
    return toast.warning(message, duration, title);
};

window.showInfo = (message, duration = 3000, title = null) => {
    return toast.info(message, duration, title);
};

window.showConfirm = (options) => {
    if (typeof options === 'string') {
        // Simple usage: showConfirm('Are you sure?')
        options = { message: options };
    }
    return ConfirmDialog.show(options);
};

// Override native alert() with toast notification
window.alert = function(message) {
    showError(message);
};

// Override native confirm() with custom dialog
const originalConfirm = window.confirm;
window.confirm = function(message) {
    // For synchronous compatibility, we need to handle this carefully
    // This is a fallback - prefer using showConfirm() for async operations
    console.warn('Native confirm() called. Consider using showConfirm() for better UX');
    return originalConfirm(message);
};

// Initialize event handlers when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Handle logout confirmation
    document.addEventListener('click', function(e) {
        const logoutLink = e.target.closest('.confirm-logout');
        if (logoutLink) {
            e.preventDefault();
            showConfirm({
                message: 'Are you sure you want to logout?',
                title: 'Confirm Logout',
                type: 'warning',
                confirmText: 'Logout',
            }).then(confirmed => {
                if (confirmed) {
                    window.location.href = logoutLink.href;
                }
            });
        }
    });

    // Handle delete experience confirmation
    document.addEventListener('click', function(e) {
        const deleteLink = e.target.closest('.confirm-delete-experience');
        if (deleteLink) {
            e.preventDefault();
            showConfirm({
                message: 'Are you sure you want to delete this experience? This action cannot be undone.',
                title: 'Confirm Delete',
                type: 'danger',
                confirmText: 'Delete',
            }).then(confirmed => {
                if (confirmed) {
                    window.location.href = deleteLink.href;
                }
            });
        }
    });

    // Handle application form submission
    const applicationForm = document.getElementById('applicationForm');
    if (applicationForm) {
        applicationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            showConfirm({
                message: 'Are you sure you want to submit this application? Please review all information before submitting.',
                title: 'Confirm Submission',
                type: 'info',
                confirmText: 'Submit Application',
            }).then(confirmed => {
                if (confirmed) {
                    applicationForm.submit();
                }
            });
        });
    }

    // Handle any generic confirm links/buttons
    document.addEventListener('click', function(e) {
        const confirmElement = e.target.closest('[data-confirm]');
        if (confirmElement && !confirmElement.classList.contains('confirm-logout') && 
            !confirmElement.classList.contains('confirm-delete-experience')) {
            e.preventDefault();
            const message = confirmElement.getAttribute('data-confirm');
            const title = confirmElement.getAttribute('data-confirm-title') || 'Confirm Action';
            const type = confirmElement.getAttribute('data-confirm-type') || 'warning';
            
            showConfirm({
                message: message,
                title: title,
                type: type,
            }).then(confirmed => {
                if (confirmed) {
                    if (confirmElement.tagName === 'A') {
                        window.location.href = confirmElement.href;
                    } else if (confirmElement.tagName === 'BUTTON') {
                        confirmElement.form?.submit();
                    }
                }
            });
        }
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ToastNotification, ConfirmDialog, toast };
}
