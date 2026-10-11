/**
 * Dumaran Hiring System — Login Portal UX
 * Frontend-only enhancements; does not alter authentication logic.
 */
(function () {
    'use strict';

    const STORAGE_KEY_EMAIL = 'dumaran_login_remember_email';
    const STORAGE_KEY_REMEMBER = 'dumaran_login_remember_me';

    const FRIENDLY_MESSAGES = {
        'invalid email or password': 'Incorrect email or password.',
        'all fields are required': 'Please enter your email and password.',
        'invalid request': 'Your session has expired. Please try again.',
        'please use google sign-in for this account': 'Please use Google Sign-in for this account.',
        'an error occurred. please try again.': 'Something went wrong. Please try again shortly.',
        'session expired': 'Session expired. Please sign in again.',
        'account locked': 'Account locked. Please contact the administrator.'
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const form = document.getElementById('loginForm');
        if (!form) return;

        const emailInput = form.querySelector('[name="email"]');
        const passwordInput = form.querySelector('[name="password"]');
        const submitBtn = document.getElementById('loginSubmitBtn');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const rememberCheckbox = document.getElementById('rememberMe');
        const capsLockWarning = document.getElementById('capsLockWarning');
        const offlineBanner = document.getElementById('offlineBanner');
        const loadingOverlay = document.getElementById('loginLoadingOverlay');

        restoreRememberedEmail(emailInput, rememberCheckbox);
        showFlashMessages();
        setupOfflineDetection(offlineBanner);
        setupSessionNotice();

        if (emailInput) {
            emailInput.focus();
            emailInput.addEventListener('input', () => validateEmail(emailInput));
            emailInput.addEventListener('blur', () => validateEmail(emailInput));
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', () => validatePassword(passwordInput));
            passwordInput.addEventListener('blur', () => validatePassword(passwordInput));
            passwordInput.addEventListener('keydown', (e) => checkCapsLock(e, capsLockWarning));
            passwordInput.addEventListener('keyup', (e) => checkCapsLock(e, capsLockWarning));
        }

        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', () => togglePassword(passwordInput, togglePasswordBtn));
        }

        if (rememberCheckbox && emailInput) {
            rememberCheckbox.addEventListener('change', () => {
                if (rememberCheckbox.checked) {
                    localStorage.setItem(STORAGE_KEY_REMEMBER, '1');
                    if (emailInput.value.trim()) {
                        localStorage.setItem(STORAGE_KEY_EMAIL, emailInput.value.trim());
                    }
                } else {
                    localStorage.removeItem(STORAGE_KEY_REMEMBER);
                    localStorage.removeItem(STORAGE_KEY_EMAIL);
                }
            });

            emailInput.addEventListener('input', () => {
                if (rememberCheckbox.checked) {
                    localStorage.setItem(STORAGE_KEY_EMAIL, emailInput.value.trim());
                }
            });
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', createRipple);
        }

        form.addEventListener('submit', (e) => handleSubmit(e, form, emailInput, passwordInput, submitBtn, loadingOverlay, rememberCheckbox));
    }

    function restoreRememberedEmail(emailInput, rememberCheckbox) {
        if (!emailInput || !rememberCheckbox) return;

        const remembered = localStorage.getItem(STORAGE_KEY_REMEMBER) === '1';
        const savedEmail = localStorage.getItem(STORAGE_KEY_EMAIL);

        if (remembered && savedEmail) {
            rememberCheckbox.checked = true;
            emailInput.value = savedEmail;
        }
    }

    function friendlyMessage(raw) {
        const key = (raw || '').trim().toLowerCase();
        return FRIENDLY_MESSAGES[key] || raw;
    }

    function showFlashMessages() {
        const flashEl = document.getElementById('loginFlashData');
        if (!flashEl) return;

        let data;
        try {
            data = JSON.parse(flashEl.textContent);
        } catch (err) {
            return;
        }

        if (!Array.isArray(data)) return;

        data.forEach((item, index) => {
            setTimeout(() => {
                const message = friendlyMessage(item.message);
                if (typeof window.showToast === 'function') {
                    window.showToast(message, item.type, item.type === 'error' ? 5000 : 4000, item.title);
                }
            }, index * 200);
        });

        const alertsEl = document.querySelector('.login-alerts');
        if (alertsEl) {
            alertsEl.setAttribute('aria-hidden', 'true');
            alertsEl.classList.add('sr-only');
        }

        flashEl.remove();
    }

    function setupOfflineDetection(banner) {
        if (!banner) return;

        const update = () => {
            banner.classList.toggle('is-visible', !navigator.onLine);
            banner.setAttribute('aria-hidden', navigator.onLine ? 'true' : 'false');
        };

        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
    }

    function setupSessionNotice() {
        const params = new URLSearchParams(window.location.search);
        const error = (params.get('error') || '').toLowerCase();
        const message = (params.get('message') || '').toLowerCase();

        if (error.includes('session') || error.includes('expired') || message.includes('session') || message.includes('expired')) {
            setTimeout(() => {
                if (typeof window.showWarning === 'function') {
                    window.showWarning('Your session has expired. Please sign in again.', 6000, 'Session Expired');
                }
            }, 800);
        }
    }

    function validateEmail(input) {
        const errorEl = document.getElementById('emailError');
        const value = input.value.trim();

        if (!value) {
            setFieldState(input, errorEl, false, 'Please enter your email.');
            return false;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            setFieldState(input, errorEl, false, 'Please enter a valid email address.');
            return false;
        }

        setFieldState(input, errorEl, true, '');
        return true;
    }

    function validatePassword(input) {
        const errorEl = document.getElementById('passwordError');
        const value = input.value;

        if (!value) {
            setFieldState(input, errorEl, false, 'Please enter your password.');
            return false;
        }

        setFieldState(input, errorEl, true, '');
        return true;
    }

    function setFieldState(input, errorEl, isValid, message) {
        input.classList.toggle('is-valid', isValid && input.value.length > 0);
        input.classList.toggle('is-invalid', !isValid && input.value.length > 0);

        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.toggle('is-visible', !isValid && message);
        }

        input.setAttribute('aria-invalid', isValid ? 'false' : 'true');
    }

    function checkCapsLock(event, warningEl) {
        if (!warningEl) return;

        const capsOn = event.getModifierState && event.getModifierState('CapsLock');
        warningEl.classList.toggle('is-visible', !!capsOn);
        warningEl.setAttribute('aria-hidden', capsOn ? 'false' : 'true');
    }

    function togglePassword(input, btn) {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        btn.style.transform = 'translateY(-50%) scale(0.92)';
        requestAnimationFrame(() => {
            btn.style.transform = 'translateY(-50%) scale(1)';
        });
    }

    function createRipple(e) {
        const btn = e.currentTarget;
        const rect = btn.getBoundingClientRect();
        const ripple = document.createElement('span');
        ripple.className = 'btn-signin__ripple';
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        btn.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove());
    }

    function handleSubmit(e, form, emailInput, passwordInput, submitBtn, loadingOverlay, rememberCheckbox) {
        if (!navigator.onLine) {
            e.preventDefault();
            if (typeof window.showError === 'function') {
                window.showError('No Internet Connection. Please check your network and try again.', 5000, 'Offline');
            }
            return;
        }

        const emailValid = emailInput ? validateEmail(emailInput) : true;
        const passwordValid = passwordInput ? validatePassword(passwordInput) : true;

        if (!emailValid || !passwordValid) {
            e.preventDefault();
            if (typeof window.showError === 'function') {
                window.showError('Please correct the highlighted fields before signing in.', 4000, 'Validation Error');
            }
            (emailValid ? passwordInput : emailInput)?.focus();
            return;
        }

        if (form.dataset.submitting === 'true') {
            e.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';
        form.classList.add('is-loading');

        if (rememberCheckbox && emailInput) {
            if (rememberCheckbox.checked) {
                localStorage.setItem(STORAGE_KEY_REMEMBER, '1');
                localStorage.setItem(STORAGE_KEY_EMAIL, emailInput.value.trim());
            } else {
                localStorage.removeItem(STORAGE_KEY_REMEMBER);
                localStorage.removeItem(STORAGE_KEY_EMAIL);
            }
        }

        setButtonState(submitBtn, 'loading');
        if (loadingOverlay) loadingOverlay.classList.add('is-visible');

        form.querySelectorAll('button:not([type="submit"]), a, #rememberMe').forEach((el) => {
            if (el.tagName === 'A') {
                el.style.pointerEvents = 'none';
                el.setAttribute('aria-disabled', 'true');
            } else if (el.type !== 'hidden') {
                el.disabled = true;
            }
        });
    }

    function setButtonState(btn, state) {
        if (!btn) return;

        const label = btn.querySelector('.btn-signin__label');
        btn.classList.remove('is-loading', 'is-success');
        btn.disabled = false;

        switch (state) {
            case 'loading':
                btn.classList.add('is-loading');
                btn.disabled = true;
                if (label) label.textContent = 'Signing In...';
                break;
            case 'success':
                btn.classList.add('is-success');
                if (label) label.textContent = 'Welcome Back!';
                break;
            default:
                if (label) label.textContent = 'Sign In';
        }
    }
})();
