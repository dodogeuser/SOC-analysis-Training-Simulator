// ShadowWatch - Main JS

// ===== TOAST NOTIFICATIONS =====
function showToast(message, type = 'info', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span style="font-size:16px">${icons[type] || 'ℹ'}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(40px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ===== MODAL =====
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
    if (e.target.classList.contains('modal-close')) {
        e.target.closest('.modal-overlay').classList.remove('active');
    }
});

// ===== LIVE CLOCK =====
function updateClock() {
    const el = document.getElementById('live-clock');
    if (el) {
        const now = new Date();
        el.textContent = now.toISOString().replace('T', ' ').slice(0, 19) + ' UTC';
    }
}
setInterval(updateClock, 1000);
updateClock();

// ===== CSRF TOKEN =====
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// ===== API HELPERS =====
async function apiPost(url, data = {}) {
    data._csrf = getCsrfToken();
    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    });
    return resp.json();
}

async function apiGet(url) {
    const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return resp.json();
}

// ===== CONFIRM DIALOG =====
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}

// ===== ALERT AUTO-REFRESH =====
let alertRefreshInterval = null;

function startAlertRefresh(callback, interval = 30000) {
    if (alertRefreshInterval) clearInterval(alertRefreshInterval);
    alertRefreshInterval = setInterval(callback, interval);
}

// ===== SEVERITY COLORS =====
const SEVERITY_COLORS = {
    low: '#00ff88', medium: '#ffcc00', high: '#ff6600', critical: '#ff2244'
};

// ===== NUMBER FORMAT =====
function formatNumber(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return n.toString();
}

// ===== TIME AGO =====
function timeAgo(dateStr) {
    const time = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (time < 60) return Math.floor(time) + 's ago';
    if (time < 3600) return Math.floor(time / 60) + 'm ago';
    if (time < 86400) return Math.floor(time / 3600) + 'h ago';
    return Math.floor(time / 86400) + 'd ago';
}

// ===== COPY TO CLIPBOARD =====
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success', 2000);
    });
}

// ===== SCAN EFFECT =====
function triggerScanEffect(el) {
    el.classList.add('scanline');
    setTimeout(() => el.classList.remove('scanline'), 3000);
}

// ===== LIVE BADGE COUNT =====
async function updateAlertCount() {
    try {
        const data = await apiGet('/shadowwatch/api/fetch_alerts.php?count_only=1');
        const badge = document.getElementById('alert-count-badge');
        const topBadge = document.getElementById('topbar-alert-count');
        if (badge && data.open_count !== undefined) {
            badge.textContent = data.open_count;
            badge.style.display = data.open_count > 0 ? 'inline-block' : 'none';
        }
        if (topBadge && data.open_count !== undefined) {
            topBadge.textContent = data.open_count + ' OPEN';
        }
    } catch (e) {}
}

// Update badge every 30 seconds
if (document.querySelector('.nav-badge')) {
    setInterval(updateAlertCount, 30000);
}

// ===== TABLE SEARCH =====
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss alerts
    document.querySelectorAll('.alert-bar[data-auto-dismiss]').forEach(el => {
        const delay = parseInt(el.dataset.autoDismiss) || 5000;
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.3s';
            setTimeout(() => el.remove(), 300);
        }, delay);
    });

    // Active nav highlight
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-item').forEach(item => {
        if (item.getAttribute('href') && currentPath.endsWith(item.getAttribute('href').split('/').pop())) {
            item.classList.add('active');
        }
    });
});
