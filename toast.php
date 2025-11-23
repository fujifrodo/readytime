<!-- Toast Notification System -->
<style>
    /* ========== Modern Toast Notification System ========== */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
        max-width: 450px;
    }
    
    .toast-clear-all {
        pointer-events: auto;
        background: rgba(0, 0, 0, 0.85);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        align-self: flex-end;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    
    .toast-clear-all:hover {
        background: rgba(0, 0, 0, 1);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.4);
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(450px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(450px);
            opacity: 0;
        }
    }
    
    @keyframes progress {
        from { width: 100%; }
        to { width: 0%; }
    }
    
    @keyframes bounceIn {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    
    .toast {
        pointer-events: auto;
        min-width: 350px;
        max-width: 450px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        overflow: hidden;
        animation: slideInRight 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        transition: all 0.3s;
        border-left: 5px solid;
    }
    
    .toast.removing {
        animation: slideOutRight 0.3s ease-in;
    }
    
    .toast:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.2);
    }
    
    .toast-success { border-left-color: #10b981; }
    .toast-error { border-left-color: #ef4444; }
    .toast-warning { border-left-color: #f59e0b; }
    .toast-info { border-left-color: #3b82f6; }
    
    .toast-content {
        display: flex;
        align-items: flex-start;
        padding: 16px;
        gap: 12px;
    }
    
    .toast-icon {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        animation: bounceIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .toast-success .toast-icon {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }
    
    .toast-error .toast-icon {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }
    
    .toast-warning .toast-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    
    .toast-info .toast-icon {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }
    
    .toast-body {
        flex: 1;
        padding-right: 8px;
    }
    
    .toast-title {
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 4px;
        color: #1f2937;
    }
    
    .toast-message {
        font-size: 14px;
        color: #6b7280;
        line-height: 1.5;
        word-wrap: break-word;
    }
    
    .toast-close {
        flex-shrink: 0;
        background: transparent;
        border: none;
        color: #9ca3af;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .toast-close:hover {
        background: #f3f4f6;
        color: #1f2937;
    }
    
    .toast-progress {
        height: 4px;
        background: rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    
    .toast-progress-bar {
        height: 100%;
        animation: progress linear;
        transform-origin: left;
    }
    
    .toast-success .toast-progress-bar {
        background: linear-gradient(90deg, #10b981, #059669);
    }
    
    .toast-error .toast-progress-bar {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }
    
    .toast-warning .toast-progress-bar {
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }
    
    .toast-info .toast-progress-bar {
        background: linear-gradient(90deg, #3b82f6, #2563eb);
    }
    
    @media (max-width: 640px) {
        .toast-container {
            left: 10px;
            right: 10px;
            top: 10px;
        }
        
        .toast {
            min-width: 100%;
            max-width: 100%;
        }
    }
</style>

<div id="toast-container" class="toast-container"></div>

<script>
class ToastNotification {
    constructor() {
        this.container = document.getElementById('toast-container');
        this.toasts = new Map();
        this.toastIdCounter = 0;
        this.clearAllButton = null;
        this.maxToasts = 5;
    }

    show(message, type = 'info', duration = 5000, title = '') {
        // Prevent duplicate toasts
        const toastKey = `${type}-${message}`;
        if (this.toasts.has(toastKey)) {
            return;
        }

        // Remove oldest toast if limit reached
        if (this.toasts.size >= this.maxToasts) {
            const firstKey = this.toasts.keys().next().value;
            this.remove(firstKey);
        }

        const toastId = `toast-${this.toastIdCounter++}`;
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        const titles = {
            success: title || 'สำเร็จ',
            error: title || 'ข้อผิดพลาด',
            warning: title || 'คำเตือน',
            info: title || 'แจ้งเตือน'
        };

        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">${icons[type]}</div>
                <div class="toast-body">
                    <div class="toast-title">${titles[type]}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="toastNotification.remove('${toastKey}')">&times;</button>
            </div>
            <div class="toast-progress">
                <div class="toast-progress-bar" style="animation-duration: ${duration}ms"></div>
            </div>
        `;

        this.container.appendChild(toast);
        this.toasts.set(toastKey, { element: toast, id: toastId });

        // Update clear all button
        this.updateClearAllButton();

        // Auto remove
        if (duration > 0) {
            setTimeout(() => this.remove(toastKey), duration);
        }
    }

    remove(toastKey) {
        const toastData = this.toasts.get(toastKey);
        if (!toastData) return;

        const toast = toastData.element;
        toast.classList.add('removing');
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            this.toasts.delete(toastKey);
            this.updateClearAllButton();
        }, 300);
    }

    clearAll() {
        this.toasts.forEach((_, key) => this.remove(key));
    }

    updateClearAllButton() {
        if (this.toasts.size > 1) {
            if (!this.clearAllButton) {
                this.clearAllButton = document.createElement('button');
                this.clearAllButton.className = 'toast-clear-all';
                this.clearAllButton.textContent = 'ล้างทั้งหมด';
                this.clearAllButton.onclick = () => this.clearAll();
                this.container.insertBefore(this.clearAllButton, this.container.firstChild);
            }
        } else {
            if (this.clearAllButton) {
                this.clearAllButton.remove();
                this.clearAllButton = null;
            }
        }
    }

    success(message, title = '', duration = 5000) {
        this.show(message, 'success', duration, title);
    }

    error(message, title = '', duration = 7000) {
        this.show(message, 'error', duration, title);
    }

    warning(message, title = '', duration = 6000) {
        this.show(message, 'warning', duration, title);
    }

    info(message, title = '', duration = 5000) {
        this.show(message, 'info', duration, title);
    }
}

// Create global instance
const toastNotification = new ToastNotification();

// Helper functions for backward compatibility
function showToast(message, type = 'info', duration = 5000) {
    toastNotification.show(message, type, duration);
}

function showSuccess(message, duration = 5000) {
    toastNotification.success(message, '', duration);
}

function showError(message, duration = 7000) {
    toastNotification.error(message, '', duration);
}

function showWarning(message, duration = 6000) {
    toastNotification.warning(message, '', duration);
}

function showInfo(message, duration = 5000) {
    toastNotification.info(message, '', duration);
}
</script>
