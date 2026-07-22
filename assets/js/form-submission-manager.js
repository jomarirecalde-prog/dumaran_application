/**
 * Form Submission Manager - Client-side duplicate prevention
 * 
 * This script prevents duplicate form submissions by:
 * 1. Disabling submit buttons after click
 * 2. Showing loading states
 * 3. Preventing double-clicks
 * 4. Handling browser back button issues
 * 
 * Usage:
 *   Add class "prevent-double-submit" to any form
 *   OR call FormSubmissionManager.init() to apply to all forms
 */

class FormSubmissionManager {
    constructor() {
        this.submittingForms = new Set();
        this.init();
    }

    init() {
        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.attachHandlers());
        } else {
            this.attachHandlers();
        }

        // Handle browser back button
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                // Page was loaded from cache (back button)
                this.resetAllForms();
            }
        });
    }

    attachHandlers() {
        // Get all forms with prevent-double-submit class or all forms by default
        const forms = document.querySelectorAll('form.prevent-double-submit, form[method="POST"], form[method="post"]');
        
        forms.forEach(form => {
            // Skip if already initialized
            if (form.dataset.submissionManagerInit) {
                return;
            }
            
            form.dataset.submissionManagerInit = 'true';
            
            // Add submit handler
            form.addEventListener('submit', (e) => this.handleSubmit(e, form));
            
            // Add click handlers to submit buttons
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(button => {
                button.addEventListener('click', () => this.markButtonClicked(button));
            });
        });
    }

    handleSubmit(event, form) {
        const formId = this.getFormId(form);
        
        // Check if form is already being submitted
        if (this.submittingForms.has(formId)) {
            event.preventDefault();
            this.showDuplicateWarning(form);
            return false;
        }

        // Check for custom validation
        if (!this.validateForm(form)) {
            return false;
        }

        // Mark form as submitting
        this.submittingForms.add(formId);
        
        // Disable form elements
        this.disableForm(form);
        
        // Show loading state
        this.showLoadingState(form);
        
        // Store submission in sessionStorage to detect page refresh
        this.markFormSubmitted(formId);
        
        // Allow form to submit
        return true;
    }

    validateForm(form) {
        // Let browser's native validation run first
        if (!form.checkValidity()) {
            return false;
        }

        // Check for custom validation functions
        if (typeof form.customValidation === 'function') {
            return form.customValidation();
        }

        return true;
    }

    disableForm(form) {
        // Disable only submit controls; keep other inputs enabled so their values submit
        const submitElements = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitElements.forEach(element => {
            element.disabled = true;
            element.classList.add('disabled-by-submit');
        });

        // Make other interactive fields read-only to discourage changes without blocking submission payload
        const readonlyElements = form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), textarea');
        readonlyElements.forEach(element => {
            if (element.matches('input[type="checkbox"], input[type="radio"]')) {
                return;
            }

            if ('readOnly' in element && element.readOnly === false) {
                element.dataset.wasReadonly = 'false';
                element.readOnly = true;
            }
        });
    }

    enableForm(form) {
        // Re-enable all elements
        const submitElements = form.querySelectorAll('.disabled-by-submit');
        submitElements.forEach(element => {
            element.disabled = false;
            element.classList.remove('disabled-by-submit');
        });

        const readonlyElements = form.querySelectorAll('input[data-was-readonly], textarea[data-was-readonly]');
        readonlyElements.forEach(element => {
            if (element.dataset.wasReadonly === 'false' && 'readOnly' in element) {
                element.readOnly = false;
            }
            delete element.dataset.wasReadonly;
        });
    }

    showLoadingState(form) {
        // Find submit button that was clicked
        const submitButton = form.querySelector('button[type="submit"].clicked, input[type="submit"].clicked') 
                          || form.querySelector('button[type="submit"], input[type="submit"]');
        
        if (submitButton) {
            // Store original text
            submitButton.dataset.originalText = submitButton.innerHTML || submitButton.value;
            
            // Show loading state
            if (submitButton.tagName === 'BUTTON') {
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            } else {
                submitButton.value = 'Processing...';
            }
            
            submitButton.classList.add('btn-loading');
        }
    }

    markButtonClicked(button) {
        // Remove clicked class from all buttons in the form
        const form = button.closest('form');
        if (form) {
            const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            buttons.forEach(btn => btn.classList.remove('clicked'));
        }
        
        // Mark this button as clicked
        button.classList.add('clicked');
    }

    showDuplicateWarning(form) {
        // Check if toast system is available
        if (typeof window.Toast !== 'undefined') {
            Toast.show('This form is already being submitted. Please wait...', 'warning');
        } else {
            alert('This form is already being submitted. Please wait...');
        }
    }

    getFormId(form) {
        // Use form ID if available, otherwise generate one based on action and fields
        if (form.id) {
            return form.id;
        }
        
        const action = form.action || window.location.href;
        const formName = form.name || 'unnamed';
        return `${formName}_${action}`;
    }

    markFormSubmitted(formId) {
        try {
            const submitted = JSON.parse(sessionStorage.getItem('submitted_forms') || '[]');
            if (!submitted.includes(formId)) {
                submitted.push(formId);
                sessionStorage.setItem('submitted_forms', JSON.stringify(submitted));
            }
        } catch (e) {
            console.warn('Could not store form submission state:', e);
        }
    }

    wasFormSubmitted(formId) {
        try {
            const submitted = JSON.parse(sessionStorage.getItem('submitted_forms') || '[]');
            return submitted.includes(formId);
        } catch (e) {
            return false;
        }
    }

    resetForm(form) {
        const formId = this.getFormId(form);
        
        // Remove from submitting set
        this.submittingForms.delete(formId);
        
        // Re-enable form
        this.enableForm(form);
        
        // Reset submit button
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton && submitButton.dataset.originalText) {
            if (submitButton.tagName === 'BUTTON') {
                submitButton.innerHTML = submitButton.dataset.originalText;
            } else {
                submitButton.value = submitButton.dataset.originalText;
            }
            submitButton.classList.remove('btn-loading');
        }
    }

    resetAllForms() {
        // Clear submitting forms set
        this.submittingForms.clear();
        
        // Clear sessionStorage
        try {
            sessionStorage.removeItem('submitted_forms');
        } catch (e) {
            console.warn('Could not clear form submission state:', e);
        }
        
        // Reset all forms
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            this.enableForm(form);
            
            // Reset submit buttons
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(button => {
                if (button.dataset.originalText) {
                    if (button.tagName === 'BUTTON') {
                        button.innerHTML = button.dataset.originalText;
                    } else {
                        button.value = button.dataset.originalText;
                    }
                }
                button.classList.remove('btn-loading', 'clicked');
            });
        });
    }

    // Public method to manually reset a specific form
    static resetForm(formElement) {
        const instance = window.formSubmissionManager;
        if (instance && formElement) {
            instance.resetForm(formElement);
        }
    }

    // Public method to check if a form is currently submitting
    static isSubmitting(formElement) {
        const instance = window.formSubmissionManager;
        if (instance && formElement) {
            const formId = instance.getFormId(formElement);
            return instance.submittingForms.has(formId);
        }
        return false;
    }
}

// Initialize the form submission manager
window.formSubmissionManager = new FormSubmissionManager();

// Expose public methods
window.FormSubmissionManager = {
    resetForm: FormSubmissionManager.resetForm,
    isSubmitting: FormSubmissionManager.isSubmitting
};
