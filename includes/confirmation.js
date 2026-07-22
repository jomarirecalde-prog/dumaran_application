/**
 * Confirmation Dialog System for WPU Applicant System
 * Provides user-friendly confirmation dialogs for critical actions
 */

class ConfirmationDialog {
    constructor() {
        this.createDialogHTML();
        this.bindEvents();
    }

    createDialogHTML() {
        const dialogHTML = `
            <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0">
                            <h5 class="modal-title" id="confirmationTitle">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                Confirm Action
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p id="confirmationMessage">Are you sure you want to proceed?</p>
                            <div id="confirmationDetails" class="alert alert-warning" style="display: none;">
                                <small id="confirmationDetailsText"></small>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-danger" id="confirmButton">
                                <i class="fas fa-check"></i> Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        const existingModal = document.getElementById('confirmationModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', dialogHTML);
    }

    bindEvents() {
        // Handle confirmation button clicks
        document.addEventListener('click', (e) => {
            const confirmElement = e.target.closest('[data-confirm]');
            if (confirmElement) {
                e.preventDefault();
                this.showConfirmation(confirmElement);
            }
        });

        // Handle form submissions with confirmation
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.hasAttribute('data-confirm')) {
                e.preventDefault();
                this.showConfirmation(form);
            }
        });
    }

    showConfirmation(element) {
        const message = element.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
        const title = element.getAttribute('data-confirm-title') || 'Confirm Action';
        const details = element.getAttribute('data-confirm-details');
        const buttonText = element.getAttribute('data-confirm-button') || 'Confirm';
        const buttonClass = element.getAttribute('data-confirm-class') || 'btn-danger';

        // Update modal content
        document.getElementById('confirmationTitle').innerHTML = `
            <i class="fas fa-exclamation-triangle text-warning"></i> ${title}
        `;
        document.getElementById('confirmationMessage').textContent = message;
        
        const detailsElement = document.getElementById('confirmationDetails');
        const detailsTextElement = document.getElementById('confirmationDetailsText');
        
        if (details) {
            detailsTextElement.innerHTML = details;
            detailsElement.style.display = 'block';
        } else {
            detailsElement.style.display = 'none';
        }

        const confirmButton = document.getElementById('confirmButton');
        confirmButton.className = `btn ${buttonClass}`;
        confirmButton.innerHTML = `<i class="fas fa-check"></i> ${buttonText}`;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        modal.show();

        // Handle confirmation
        confirmButton.onclick = () => {
            modal.hide();
            this.executeAction(element);
        };
    }

    executeAction(element) {
        if (element.tagName.toLowerCase() === 'form') {
            // Submit form
            element.submit();
        } else if (element.tagName.toLowerCase() === 'a') {
            // Navigate to link
            window.location.href = element.href;
        } else if (element.hasAttribute('data-action')) {
            // Execute custom action
            const action = element.getAttribute('data-action');
            if (typeof window[action] === 'function') {
                window[action](element);
            }
        } else {
            // Click the element
            element.click();
        }
    }

    // Static method for programmatic confirmation
    static confirm(options = {}) {
        return new Promise((resolve) => {
            const {
                title = 'Confirm Action',
                message = 'Are you sure you want to proceed?',
                details = null,
                confirmText = 'Confirm',
                confirmClass = 'btn-danger'
            } = options;

            const dialogHTML = `
                <div class="modal fade" id="programmaticModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header border-0">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    ${title}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>${message}</p>
                                ${details ? `<div class="alert alert-warning"><small>${details}</small></div>` : ''}
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="button" class="btn ${confirmClass}" id="programmaticConfirm">
                                    <i class="fas fa-check"></i> ${confirmText}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal
            const existingModal = document.getElementById('programmaticModal');
            if (existingModal) {
                existingModal.remove();
            }

            document.body.insertAdjacentHTML('beforeend', dialogHTML);
            
            const modal = new bootstrap.Modal(document.getElementById('programmaticModal'));
            modal.show();

            document.getElementById('programmaticConfirm').onclick = () => {
                modal.hide();
                document.getElementById('programmaticModal').remove();
                resolve(true);
            };

            document.getElementById('programmaticModal').addEventListener('hidden.bs.modal', () => {
                document.getElementById('programmaticModal').remove();
                resolve(false);
            });
        });
    }
}

// Success/Error Toast Notifications
class ToastNotification {
    static show(message, type = 'success', duration = 5000) {
        const toastHTML = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" 
                 role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                            data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement, { delay: duration });
        toast.show();

        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    static success(message, duration = 5000) {
        this.show(message, 'success', duration);
    }

    static error(message, duration = 5000) {
        this.show(message, 'error', duration);
    }
}

// Loading Spinner
class LoadingSpinner {
    static show(message = 'Loading...') {
        const spinnerHTML = `
            <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0">
                        <div class="modal-body text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mb-0">${message}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing spinner
        const existingSpinner = document.getElementById('loadingModal');
        if (existingSpinner) {
            existingSpinner.remove();
        }

        document.body.insertAdjacentHTML('beforeend', spinnerHTML);
        
        const modal = new bootstrap.Modal(document.getElementById('loadingModal'), {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();
        
        return modal;
    }

    static hide() {
        const modal = document.getElementById('loadingModal');
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
    }
}

// Initialize confirmation dialog system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new ConfirmationDialog();

    // Add global confirmation for forms with delete actions
    document.querySelectorAll('form[action*="delete"], form input[name="action"][value="delete"]').forEach(form => {
        if (!form.hasAttribute('data-confirm')) {
            form.setAttribute('data-confirm', 'Are you sure you want to delete this item?');
            form.setAttribute('data-confirm-title', 'Confirm Delete');
            form.setAttribute('data-confirm-details', 'This action cannot be undone.');
            form.setAttribute('data-confirm-button', 'Delete');
            form.setAttribute('data-confirm-class', 'btn-danger');
        }
    });

    // Add confirmation for logout links
    document.querySelectorAll('a[href*="logout"]').forEach(link => {
        if (!link.hasAttribute('data-confirm')) {
            link.setAttribute('data-confirm', 'Are you sure you want to logout?');
            link.setAttribute('data-confirm-title', 'Confirm Logout');
            link.setAttribute('data-confirm-button', 'Logout');
            link.setAttribute('data-confirm-class', 'btn-warning');
        }
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ConfirmationDialog, ToastNotification, LoadingSpinner };
}