/**
 * WPU Applicant System — Premium Toast Notification Manager
 * Drop-in replacement preserving showSuccess/showError/showInfo/showWarning/showToast APIs
 */

const TOAST_TYPES = {
    success: { icon: 'fas fa-check-circle', title: 'Success', duration: 3000 },
    error: { icon: 'fas fa-times-circle', title: 'Error', duration: 6000 },
    warning: { icon: 'fas fa-exclamation-triangle', title: 'Warning', duration: 4500 },
    info: { icon: 'fas fa-info-circle', title: 'Info', duration: 3500 },
    neutral: { icon: 'fas fa-bell', title: 'Notification', duration: 3500 },
    loading: { icon: 'fas fa-circle-notch', title: 'Loading', duration: 0 },
    archive: { icon: 'fas fa-archive', title: 'Archived', duration: 3000 },
    restore: { icon: 'fas fa-undo', title: 'Restored', duration: 3000 },
    email: { icon: 'fas fa-envelope', title: 'Email', duration: 3500 },
    download: { icon: 'fas fa-download', title: 'Download', duration: 3000 },
    upload: { icon: 'fas fa-upload', title: 'Upload', duration: 3000 },
    delete: { icon: 'fas fa-trash-alt', title: 'Deleted', duration: 3500 },
};

class ToastNotification {
    constructor() {
        this.maxVisible = 4;
        this.queue = [];
        this.visible = [];
        this.groupMap = new Map();
        this.nextId = 1;
        this.soundEnabled = localStorage.getItem('toast-sound-enabled') === 'true';
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.timeUpdateInterval = null;
        this.createContainer();
        this.createLiveRegion();
        this.bindGlobalEvents();
    }

    /* ── Setup ── */

    createContainer() {
        if (document.getElementById('toast-container-custom')) return;

        const container = document.createElement('div');
        container.id = 'toast-container-custom';
        container.className = 'toast-container-custom';
        container.setAttribute('aria-label', 'Notifications');

        if (!document.querySelector('.sidebar, .topbar, .admin-dashboard')) {
            container.classList.add('toast-container--login');
        }

        document.body.appendChild(container);
    }

    createLiveRegion() {
        if (document.getElementById('toast-sr-live')) return;

        const live = document.createElement('div');
        live.id = 'toast-sr-live';
        live.className = 'toast-sr-live';
        live.setAttribute('role', 'status');
        live.setAttribute('aria-live', 'polite');
        live.setAttribute('aria-atomic', 'true');
        document.body.appendChild(live);
    }

    bindGlobalEvents() {
        window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (e) => {
            this.reducedMotion = e.matches;
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.updateAllTimestamps();
        });
    }

    getContainer() {
        let container = document.getElementById('toast-container-custom');
        if (!container) {
            this.createContainer();
            container = document.getElementById('toast-container-custom');
        }
        return container;
    }

    /* ── Public API ── */

    /**
     * Show a toast — backward compatible signature:
     * show(message, type, duration, title)
     * Extended: show({ message, type, duration, title, actions, details, groupKey, icon, loading })
     */
    show(messageOrOptions, type = 'info', duration = null, title = null) {
        const options = this.normalizeOptions(messageOrOptions, type, duration, title);

        if (options.groupKey !== false) {
            const groupKey = options.groupKey || this.buildGroupKey(options);
            const existing = this.groupMap.get(groupKey);
            if (existing && existing.element.isConnected) {
                return this.incrementGroup(existing, options);
            }
        }

        if (this.visible.length >= this.maxVisible) {
            return this.enqueue(options);
        }

        return this.renderToast(options);
    }

    success(message, duration = 3000, title = null) {
        return this.show(message, 'success', duration, title);
    }

    error(message, duration = 6000, title = null) {
        return this.show(message, 'error', duration, title);
    }

    warning(message, duration = 4500, title = null) {
        return this.show(message, 'warning', duration, title);
    }

    info(message, duration = 3500, title = null) {
        return this.show(message, 'info', duration, title);
    }

    loading(message, title = 'Please wait...', options = {}) {
        const opts = typeof message === 'object'
            ? message
            : { message, title, type: 'loading', duration: 0, ...options };

        opts.type = 'loading';
        opts.duration = 0;
        opts.groupKey = false;
        return this.show(opts);
    }

    /**
     * Transition an existing toast (e.g. loading → success/error)
     */
    update(toastRef, updates = {}) {
        const toastObj = this.resolveToastRef(toastRef);
        if (!toastObj || !toastObj.element.isConnected) return null;

        const nextType = updates.type || toastObj.type;
        const config = TOAST_TYPES[nextType] || TOAST_TYPES.info;

        if (updates.message !== undefined) toastObj.message = updates.message;
        if (updates.title !== undefined) toastObj.title = updates.title;
        if (updates.details !== undefined) toastObj.details = updates.details;
        if (updates.actions !== undefined) toastObj.actions = updates.actions;

        toastObj.type = nextType;
        toastObj.loading = nextType === 'loading';

        const el = toastObj.element;
        el.className = el.className
            .replace(/toast-(success|error|warning|info|neutral|loading|archive|restore|email|download|upload|delete)/g, '')
            .trim();
        el.classList.add(`toast-${nextType}`);

        if (nextType === 'loading') {
            el.classList.add('toast-loading', 'toast-no-autodismiss');
        } else {
            el.classList.remove('toast-loading', 'toast-no-autodismiss');
        }

        const iconEl = el.querySelector('.toast-notification-icon i');
        if (iconEl) {
            iconEl.className = updates.icon || config.icon;
        }

        const titleEl = el.querySelector('.toast-notification-title');
        if (titleEl) {
            titleEl.textContent = toastObj.title || config.title;
        }

        const messageEl = el.querySelector('.toast-notification-message');
        if (messageEl) {
            messageEl.textContent = toastObj.message;
        }

        this.setDetails(el, toastObj.details);
        this.renderActions(toastObj);

        if (nextType === 'success') {
            el.classList.add('toast-success-pop');
            setTimeout(() => el.classList.remove('toast-success-pop'), 600);
            this.playSound('success');
        } else if (nextType === 'error') {
            this.playSound('error');
        }

        if (nextType !== 'loading') {
            const newDuration = updates.duration ?? config.duration;
            this.restartAutoDismiss(toastObj, newDuration);
        } else {
            this.clearAutoDismiss(toastObj);
        }

        this.announce(`${toastObj.title}: ${toastObj.message}`);
        return toastObj;
    }

    dismiss(toastRef) {
        const toastObj = this.resolveToastRef(toastRef);
        if (toastObj) this.removeToast(toastObj);
        return toastObj;
    }

    setSoundEnabled(enabled) {
        this.soundEnabled = !!enabled;
        localStorage.setItem('toast-sound-enabled', enabled ? 'true' : 'false');
    }

    /* ── Options normalization ── */

    normalizeOptions(messageOrOptions, type, duration, title) {
        if (messageOrOptions && typeof messageOrOptions === 'object' && !Array.isArray(messageOrOptions)) {
            const o = messageOrOptions;
            const t = o.type || 'info';
            const cfg = TOAST_TYPES[t] || TOAST_TYPES.info;
            return {
                message: o.message || '',
                type: t,
                duration: o.duration ?? cfg.duration,
                title: o.title ?? cfg.title,
                actions: o.actions || [],
                details: o.details || null,
                groupKey: o.groupKey,
                icon: o.icon || cfg.icon,
                id: o.id || this.nextId++,
                createdAt: Date.now(),
            };
        }

        const cfg = TOAST_TYPES[type] || TOAST_TYPES.info;
        return {
            message: String(messageOrOptions ?? ''),
            type,
            duration: duration ?? cfg.duration,
            title: title ?? cfg.title,
            actions: [],
            details: null,
            groupKey: undefined,
            icon: cfg.icon,
            id: this.nextId++,
            createdAt: Date.now(),
        };
    }

    buildGroupKey(options) {
        return `${options.type}::${options.title}::${options.message}`;
    }

    /* ── Queue ── */

    enqueue(options) {
        return new Promise((resolve) => {
            this.queue.push({ options, resolve });
        });
    }

    processQueue() {
        if (this.queue.length === 0 || this.visible.length >= this.maxVisible) return;

        const next = this.queue.shift();
        if (!next) return;

        const toastObj = this.renderToast(next.options);
        if (next.resolve) next.resolve(toastObj);
    }

    /* ── Render ── */

    renderToast(options) {
        const config = TOAST_TYPES[options.type] || TOAST_TYPES.info;
        const isLoading = options.type === 'loading';
        const autoDismiss = isLoading ? 0 : (options.duration > 0 ? options.duration : config.duration);

        const toastObj = {
            id: options.id,
            type: options.type,
            message: options.message,
            title: options.title || config.title,
            details: options.details,
            actions: options.actions || [],
            duration: autoDismiss,
            createdAt: options.createdAt,
            groupCount: 1,
            groupKey: options.groupKey !== false ? (options.groupKey || this.buildGroupKey(options)) : null,
            element: null,
            timeout: null,
            remaining: autoDismiss,
            paused: false,
            pauseStarted: null,
            rafId: null,
            progressStart: null,
            expanded: false,
        };

        const el = this.createToastElement(toastObj, config, options.icon);
        toastObj.element = el;

        const container = this.getContainer();
        container.insertBefore(el, container.firstChild);

        this.visible.unshift(toastObj);

        if (toastObj.groupKey) {
            this.groupMap.set(toastObj.groupKey, toastObj);
        }

        requestAnimationFrame(() => {
            el.classList.remove('toast-enter');
        });

        this.bindToastEvents(toastObj);

        if (autoDismiss > 0) {
            this.startAutoDismiss(toastObj);
        } else {
            el.classList.add('toast-no-autodismiss');
        }

        if (options.type === 'success') {
            this.playSound('success');
        } else if (options.type === 'error') {
            this.playSound('error');
        }

        this.announce(`${toastObj.title}: ${toastObj.message}`);
        this.startTimestampUpdater();

        return toastObj;
    }

    createToastElement(toastObj, config, icon) {
        const el = document.createElement('article');
        el.className = `toast-notification toast-${toastObj.type} toast-enter`;
        el.setAttribute('role', 'alert');
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-atomic', 'true');
        el.dataset.toastId = String(toastObj.id);

        if (toastObj.details) {
            el.classList.add('toast-has-details');
        }

        if (toastObj.type === 'loading') {
            el.classList.add('toast-loading');
        }

        el.innerHTML = `
            <div class="toast-accent" aria-hidden="true"></div>
            <div class="toast-notification-icon" aria-hidden="true">
                <i class="${this.escapeAttr(icon || config.icon)}"></i>
            </div>
            <div class="toast-notification-body">
                <div class="toast-notification-content">
                    <div class="toast-notification-title">${this.escapeHtml(toastObj.title)}</div>
                    <div class="toast-notification-message">${this.escapeHtml(toastObj.message)}</div>
                    <div class="toast-notification-details" hidden></div>
                </div>
                <div class="toast-notification-footer">
                    <time class="toast-notification-time">${this.formatRelativeTime(toastObj.createdAt)}</time>
                    <div class="toast-notification-actions"></div>
                </div>
            </div>
            <button type="button" class="toast-notification-close" aria-label="Dismiss notification">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <div class="toast-notification-progress" aria-hidden="true">
                <div class="toast-notification-progress-bar"></div>
            </div>
        `;

        this.setDetails(el, toastObj.details);
        this.renderActions(toastObj);

        return el;
    }

    setDetails(el, details) {
        const detailsEl = el.querySelector('.toast-notification-details');
        if (!detailsEl) return;

        if (details) {
            detailsEl.innerHTML = this.escapeHtml(details);
            detailsEl.hidden = true;
            el.classList.add('toast-has-details');
        } else {
            detailsEl.hidden = true;
            detailsEl.textContent = '';
            el.classList.remove('toast-has-details');
        }
    }

    renderActions(toastObj) {
        const actionsEl = toastObj.element?.querySelector('.toast-notification-actions');
        if (!actionsEl) return;

        actionsEl.innerHTML = '';

        (toastObj.actions || []).forEach((action) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `toast-action-btn${action.primary ? ' toast-action-btn--primary' : ''}`;
            btn.textContent = action.label || 'Action';
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (typeof action.onClick === 'function') {
                    action.onClick(toastObj);
                }
                if (action.dismiss !== false) {
                    this.removeToast(toastObj);
                }
            });
            actionsEl.appendChild(btn);
        });
    }

    /* ── Grouping ── */

    incrementGroup(toastObj, options) {
        toastObj.groupCount = (toastObj.groupCount || 1) + 1;
        toastObj.createdAt = Date.now();

        const titleEl = toastObj.element.querySelector('.toast-notification-title');
        if (titleEl) {
            const baseTitle = options.title || toastObj.title;
            titleEl.innerHTML = `${this.escapeHtml(baseTitle)}<span class="toast-group-count">${toastObj.groupCount}</span>`;
        }

        const timeEl = toastObj.element.querySelector('.toast-notification-time');
        if (timeEl) {
            timeEl.textContent = this.formatRelativeTime(toastObj.createdAt);
        }

        if (toastObj.duration > 0) {
            this.restartAutoDismiss(toastObj, toastObj.duration);
        }

        toastObj.element.classList.remove('toast-exit');
        toastObj.element.classList.add('toast-success-pop');
        setTimeout(() => toastObj.element.classList.remove('toast-success-pop'), 600);

        this.announce(`${options.title || toastObj.title}: ${options.message || toastObj.message}`);
        return toastObj;
    }

    /* ── Auto dismiss & progress ── */

    startAutoDismiss(toastObj) {
        this.clearAutoDismiss(toastObj);

        const duration = toastObj.remaining ?? toastObj.duration;
        toastObj.remaining = duration;
        toastObj.progressStart = performance.now();

        const bar = toastObj.element.querySelector('.toast-notification-progress-bar');
        if (bar) {
            bar.style.animation = 'none';
            bar.offsetHeight; // reflow
            bar.style.animation = `toastProgress ${duration}ms linear forwards`;
        }

        toastObj.timeout = setTimeout(() => {
            this.removeToast(toastObj);
        }, duration);
    }

    restartAutoDismiss(toastObj, duration) {
        toastObj.duration = duration;
        toastObj.remaining = duration;
        toastObj.paused = false;
        toastObj.element.classList.remove('toast-no-autodismiss', 'toast-paused');
        this.startAutoDismiss(toastObj);
    }

    clearAutoDismiss(toastObj) {
        if (toastObj.timeout) {
            clearTimeout(toastObj.timeout);
            toastObj.timeout = null;
        }
        if (toastObj.rafId) {
            cancelAnimationFrame(toastObj.rafId);
            toastObj.rafId = null;
        }
    }

    pauseToast(toastObj) {
        if (toastObj.paused || toastObj.duration <= 0) return;

        toastObj.paused = true;
        toastObj.pauseStarted = performance.now();

        if (toastObj.timeout) {
            clearTimeout(toastObj.timeout);
            toastObj.timeout = null;
        }

        const bar = toastObj.element.querySelector('.toast-notification-progress-bar');
        if (bar) {
            const computed = getComputedStyle(bar);
            const matrix = computed.transform;
            let scaleX = 1;
            if (matrix && matrix !== 'none') {
                const values = matrix.match(/matrix\(([^)]+)\)/);
                if (values) scaleX = parseFloat(values[1].split(',')[0]) || 1;
            }
            toastObj.remaining = Math.max(0, scaleX * toastObj.duration);
            bar.style.animation = 'none';
            bar.style.transform = `scaleX(${scaleX})`;
        }

        toastObj.element.classList.add('toast-paused');
    }

    resumeToast(toastObj) {
        if (!toastObj.paused) return;

        toastObj.paused = false;
        toastObj.element.classList.remove('toast-paused');

        const bar = toastObj.element.querySelector('.toast-notification-progress-bar');
        if (bar) {
            bar.style.animation = `toastProgress ${toastObj.remaining}ms linear forwards`;
            bar.style.transform = '';
        }

        toastObj.timeout = setTimeout(() => {
            this.removeToast(toastObj);
        }, toastObj.remaining);
    }

    /* ── Events ── */

    bindToastEvents(toastObj) {
        const el = toastObj.element;
        const closeBtn = el.querySelector('.toast-notification-close');

        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.removeToast(toastObj);
        });

        el.addEventListener('mouseenter', () => this.pauseToast(toastObj));
        el.addEventListener('mouseleave', () => this.resumeToast(toastObj));
        el.addEventListener('focusin', () => this.pauseToast(toastObj));
        el.addEventListener('focusout', () => this.resumeToast(toastObj));

        el.addEventListener('click', (e) => {
            if (e.target.closest('.toast-notification-close, .toast-action-btn')) return;
            if (!toastObj.details) return;

            toastObj.expanded = !toastObj.expanded;
            const detailsEl = el.querySelector('.toast-notification-details');
            if (detailsEl) {
                detailsEl.hidden = !toastObj.expanded;
            }
            el.setAttribute('aria-expanded', toastObj.expanded ? 'true' : 'false');
        });

        this.bindSwipeDismiss(toastObj);
    }

    bindSwipeDismiss(toastObj) {
        const el = toastObj.element;
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let currentY = 0;
        let swiping = false;

        el.addEventListener('touchstart', (e) => {
            if (e.touches.length !== 1) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            swiping = true;
            el.classList.add('toast-swiping');
        }, { passive: true });

        el.addEventListener('touchmove', (e) => {
            if (!swiping) return;
            currentX = e.touches[0].clientX - startX;
            currentY = e.touches[0].clientY - startY;

            if (Math.abs(currentY) > Math.abs(currentX) && currentY < 0) {
                el.style.transform = `translateY(${currentY}px) scale(${Math.max(0.92, 1 + currentY / 400)})`;
                el.style.opacity = String(Math.max(0.3, 1 + currentY / 200));
            } else if (currentX > 0) {
                el.style.transform = `translateX(${currentX}px)`;
                el.style.opacity = String(Math.max(0.3, 1 - currentX / 280));
            }
        }, { passive: true });

        const endSwipe = () => {
            if (!swiping) return;
            swiping = false;
            el.classList.remove('toast-swiping');

            if (currentY < -50 || currentX > 80) {
                this.removeToast(toastObj);
            } else {
                el.style.transform = '';
                el.style.opacity = '';
            }

            currentX = 0;
            currentY = 0;
        };

        el.addEventListener('touchend', endSwipe, { passive: true });
        el.addEventListener('touchcancel', endSwipe, { passive: true });
    }

    /* ── Remove ── */

    removeToast(toastObj) {
        if (!toastObj || !toastObj.element || toastObj._removing) return;

        toastObj._removing = true;
        this.clearAutoDismiss(toastObj);

        if (toastObj.groupKey) {
            const mapped = this.groupMap.get(toastObj.groupKey);
            if (mapped === toastObj) {
                this.groupMap.delete(toastObj.groupKey);
            }
        }

        const el = toastObj.element;
        el.classList.remove('toast-enter', 'toast-success-pop');
        el.classList.add('toast-exit');
        el.style.transform = '';
        el.style.opacity = '';

        const exitMs = this.reducedMotion ? 0 : 200;

        setTimeout(() => {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }

            const idx = this.visible.indexOf(toastObj);
            if (idx > -1) {
                this.visible.splice(idx, 1);
            }

            this.processQueue();

            if (this.visible.length === 0) {
                this.stopTimestampUpdater();
            }
        }, exitMs);
    }

    resolveToastRef(ref) {
        if (!ref) return null;
        if (ref.element) return ref;
        return this.visible.find((t) => t.id === ref) || null;
    }

    /* ── Timestamps ── */

    formatRelativeTime(timestamp) {
        const diffSec = Math.floor((Date.now() - timestamp) / 1000);

        if (diffSec < 5) return 'Just now';
        if (diffSec < 60) return `${diffSec} second${diffSec === 1 ? '' : 's'} ago`;

        const diffMin = Math.floor(diffSec / 60);
        if (diffMin < 60) return `${diffMin} minute${diffMin === 1 ? '' : 's'} ago`;

        const diffHr = Math.floor(diffMin / 60);
        if (diffHr < 24) return `${diffHr} hour${diffHr === 1 ? '' : 's'} ago`;

        return new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    updateAllTimestamps() {
        this.visible.forEach((toastObj) => {
            const timeEl = toastObj.element?.querySelector('.toast-notification-time');
            if (timeEl) {
                timeEl.textContent = this.formatRelativeTime(toastObj.createdAt);
            }
        });
    }

    startTimestampUpdater() {
        if (this.timeUpdateInterval) return;
        this.timeUpdateInterval = setInterval(() => this.updateAllTimestamps(), 15000);
    }

    stopTimestampUpdater() {
        if (this.timeUpdateInterval) {
            clearInterval(this.timeUpdateInterval);
            this.timeUpdateInterval = null;
        }
    }

    /* ── Sound ── */

    playSound(kind) {
        if (!this.soundEnabled || this.reducedMotion) return;

        try {
            const ctx = this._audioCtx || (this._audioCtx = new (window.AudioContext || window.webkitAudioContext)());
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.connect(gain);
            gain.connect(ctx.destination);

            if (kind === 'success') {
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.04, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.15);
            } else if (kind === 'error') {
                osc.frequency.setValueAtTime(320, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(240, ctx.currentTime + 0.12);
                gain.gain.setValueAtTime(0.05, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.18);
            }
        } catch (_) {
            /* audio optional */
        }
    }

    /* ── Accessibility ── */

    announce(text) {
        const live = document.getElementById('toast-sr-live');
        if (live) {
            live.textContent = '';
            requestAnimationFrame(() => {
                live.textContent = text;
            });
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    escapeAttr(text) {
        return String(text ?? '').replace(/"/g, '&quot;');
    }
}


class ConfirmDialog {
    static show(options = {}) {
        return new Promise((resolve) => {
            const {
                message = 'Are you sure?',
                title = 'Confirm',
                type = 'warning',
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                onConfirm = null,
                onCancel = null,
            } = options;

            const overlay = document.createElement('div');
            overlay.className = 'confirm-dialog-overlay';

            let iconClass;
            switch (type) {
                case 'danger':
                    iconClass = 'fas fa-times-circle';
                    break;
                case 'info':
                    iconClass = 'fas fa-info-circle';
                    break;
                case 'warning':
                default:
                    iconClass = 'fas fa-exclamation-triangle';
                    break;
            }

            const formattedMessage = this.escapeHtml(message).replace(/\n/g, '<br>');

            overlay.innerHTML = `
                <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title">
                    <div class="confirm-dialog-header">
                        <div class="confirm-dialog-icon ${type}">
                            <i class="${iconClass}"></i>
                        </div>
                        <h3 class="confirm-dialog-title" id="confirm-dialog-title">${this.escapeHtml(title)}</h3>
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

            dialog.addEventListener('click', (e) => e.stopPropagation());

            overlay.addEventListener('click', () => {
                this.close(overlay, false, resolve, onCancel);
            });

            confirmBtn.addEventListener('click', () => {
                this.close(overlay, true, resolve, onConfirm);
            });

            cancelBtn.addEventListener('click', () => {
                this.close(overlay, false, resolve, onCancel);
            });

            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    this.close(overlay, false, resolve, onCancel);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);

            setTimeout(() => confirmBtn.focus(), 100);
        });
    }

    static close(overlay, result, resolve, callback) {
        overlay.style.animation = 'toastFadeIn 0.2s ease reverse';
        setTimeout(() => {
            overlay.remove();
            if (callback) callback();
            resolve(result);
        }, 200);
    }

    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}


const toast = new ToastNotification();

window.showToast = (message, type = 'info', duration = 3000, title = null) => {
    return toast.show(message, type, duration, title);
};

window.showSuccess = (message, duration = 3000, title = null) => {
    return toast.success(message, duration, title);
};

window.showError = (message, duration = 6000, title = null) => {
    return toast.error(message, duration, title);
};

window.showWarning = (message, duration = 4500, title = null) => {
    return toast.warning(message, duration, title);
};

window.showInfo = (message, duration = 3500, title = null) => {
    return toast.info(message, duration, title);
};

window.showLoading = (message, title, options) => {
    return toast.loading(message, title, options);
};

window.updateToast = (toastRef, updates) => {
    return toast.update(toastRef, updates);
};

window.dismissToast = (toastRef) => {
    return toast.dismiss(toastRef);
};

window.setToastSound = (enabled) => {
    toast.setSoundEnabled(enabled);
};

window.showConfirm = (options) => {
    if (typeof options === 'string') {
        options = { message: options };
    }
    return ConfirmDialog.show(options);
};

window.alert = function (message) {
    showError(message);
};

const originalConfirm = window.confirm;
window.confirm = function (message) {
    console.warn('Native confirm() called. Consider using showConfirm() for better UX');
    return originalConfirm(message);
};

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (e) {
        const logoutLink = e.target.closest('.confirm-logout');
        if (logoutLink) {
            e.preventDefault();
            showConfirm({
                message: 'Are you sure you want to logout?',
                title: 'Confirm Logout',
                type: 'warning',
                confirmText: 'Logout',
            }).then((confirmed) => {
                if (confirmed) window.location.href = logoutLink.href;
            });
        }
    });

    document.addEventListener('click', function (e) {
        const deleteLink = e.target.closest('.confirm-delete-experience');
        if (deleteLink) {
            e.preventDefault();
            showConfirm({
                message: 'Are you sure you want to delete this experience? This action cannot be undone.',
                title: 'Confirm Delete',
                type: 'danger',
                confirmText: 'Delete',
            }).then((confirmed) => {
                if (confirmed) window.location.href = deleteLink.href;
            });
        }
    });

    const applicationForm = document.getElementById('applicationForm');
    if (applicationForm) {
        applicationForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showConfirm({
                message: 'Are you sure you want to submit this application? Please review all information before submitting.',
                title: 'Confirm Submission',
                type: 'info',
                confirmText: 'Submit Application',
            }).then((confirmed) => {
                if (confirmed) applicationForm.submit();
            });
        });
    }

    document.addEventListener('click', function (e) {
        const confirmElement = e.target.closest('[data-confirm]');
        if (
            confirmElement &&
            !confirmElement.classList.contains('confirm-logout') &&
            !confirmElement.classList.contains('confirm-delete-experience')
        ) {
            e.preventDefault();
            const message = confirmElement.getAttribute('data-confirm');
            const confirmTitle = confirmElement.getAttribute('data-confirm-title') || 'Confirm Action';
            const confirmType = confirmElement.getAttribute('data-confirm-type') || 'warning';

            showConfirm({
                message,
                title: confirmTitle,
                type: confirmType,
            }).then((confirmed) => {
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

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ToastNotification, ConfirmDialog, toast };
}
