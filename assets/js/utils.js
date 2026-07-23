/**
 * FinTrack Pro - Utility Functions & Component Helpers
 */

export const ROUTES = {
  landing: "index.html",
  dashboard: "dashboard.html",
  income: "income.html",
  expenses: "expenses.html",
  budgets: "budgets.html",
  savings: "savings.html",
  investments: "investments.html",
  loans: "loans.html",
  bills: "bills.html",
  reports: "reports.html",
  settings: "settings.html",
  profile: "profile.html",
  notifications: "notifications.html",
  login: "login.html",
  otp: "otp.html",
  verifyOtp: "verify-email.html",
  resetPassword: "reset-password.html"
};

/**
 * Resolves correct relative path for a route depending on current directory
 * @param {string} routeName
 */
export function getRoutePath(routeName) {
  const inPagesFolder = window.location.pathname.includes('/pages/');
  const filename = ROUTES[routeName];
  if (!filename) return '#';
  
  if (routeName === 'landing') {
    return inPagesFolder ? '../index.html' : 'index.html';
  }
  
  return inPagesFolder ? filename : `pages/${filename}`;
}

/**
 * Dynamically displays a premium custom toast notification
 * @param {string} message Toast descriptive text
 * @param {string} type 'success' | 'danger' | 'warning' | 'info'
 * @param {string} title Header tag for Toast
 * @param {number} duration Expiry time in ms (default 4000)
 */
export function showToast(message, type = 'info', title = 'Notification', duration = 4000) {
  let container = document.querySelector('.toast-container');
  
  // Create container if it doesn't exist
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  // Create toast card element
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  
  // Set corresponding Lucide icon
  let iconName = 'info';
  if (type === 'success') iconName = 'check-circle';
  if (type === 'danger') iconName = 'alert-triangle';
  if (type === 'warning') iconName = 'alert-circle';

  toast.innerHTML = `
    <i data-lucide="${iconName}"></i>
    <div class="toast-content">
      <div class="toast-title">${title}</div>
      <div class="toast-message">${message}</div>
    </div>
    <button class="toast-close"><i data-lucide="x"></i></button>
  `;

  container.appendChild(toast);
  
  // Render Lucide icons
  if (window.lucide) window.lucide.createIcons();

  // Slide trigger
  setTimeout(() => {
    toast.classList.add('show');
  }, 50);

  // Close event listener
  const closeBtn = toast.querySelector('.toast-close');
  closeBtn.addEventListener('click', () => dismissToast(toast));

  // Auto dismiss
  const autoDismiss = setTimeout(() => {
    dismissToast(toast);
  }, duration);

  function dismissToast(element) {
    clearTimeout(autoDismiss);
    element.classList.remove('show');
    element.addEventListener('transitionend', () => {
      element.remove();
    });
  }
}

/**
 * Attaches button click ripple effect listeners globally
 */
export function initRipples() {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn');
    if (!btn) return;

    // Create ripple circle
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    
    // Position ripple relative to button
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;

    ripple.style.width = ripple.style.height = `${size}px`;
    ripple.style.left = `${x}px`;
    ripple.style.top = `${y}px`;

    btn.appendChild(ripple);

    // Remove ripple after animation finishes
    ripple.addEventListener('animationend', () => {
      ripple.remove();
    });
  });
}

/**
 * Runs Lucide createIcons on the current DOM scope
 */
export function initIcons() {
  if (window.lucide) {
    window.lucide.createIcons();
  }
}

/**
 * Dynamic system-wide Currency Formatter
 * @param {number} amount Target value in INR (base)
 */
export function formatCurrency(amount) {
  const currency = localStorage.getItem('fintrack_currency') || 'INR';
  let converted = amount;
  let symbol = '₹';

  if (currency === 'USD') {
    converted = amount * 0.012; // Example Exchange Rate: 1 INR = 0.012 USD
    symbol = '$';
  } else if (currency === 'EUR') {
    converted = amount * 0.011; // Example Exchange Rate: 1 INR = 0.011 EUR
    symbol = '€';
  }

  const locale = currency === 'INR' ? 'en-IN' : 'en-US';
  return symbol + converted.toLocaleString(locale, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

/**
 * Calculates initials from user's full name
 * @param {string} name 
 */
function getInitials(name) {
  if (!name) return 'JD';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Synchronizes user profile elements across the layout on page load
 */
export function syncUserProfile() {
  const name = localStorage.getItem('fintrack_user_name') || 'John Doe';
  const email = localStorage.getItem('fintrack_user_email') || 'john.doe@example.com';
  const initials = getInitials(name);

  // Sync sidebars
  const sidebarNames = document.querySelectorAll('.sidebar-profile-name');
  const sidebarEmails = document.querySelectorAll('.sidebar-profile-email');
  sidebarNames.forEach(el => el.textContent = name);
  sidebarEmails.forEach(el => el.textContent = email);

  const sidebarAvatars = document.querySelectorAll('.sidebar-footer .avatar-initials');
  sidebarAvatars.forEach(el => el.textContent = initials);

  // Sync headers
  const headerNameTrigger = document.querySelector('#profileTriggerBtn span');
  if (headerNameTrigger) {
    headerNameTrigger.textContent = name;
  }

  const dropdownName = document.querySelector('#profileDropdown .profile-menu-info > div:not(.avatar) > div:first-child');
  const dropdownEmail = document.querySelector('#profileDropdown .profile-menu-info > div:not(.avatar) > div:nth-child(2)');
  if (dropdownName) dropdownName.textContent = name;
  if (dropdownEmail) dropdownEmail.textContent = email;

  const headerAvatars = document.querySelectorAll('#profileTriggerBtn .avatar, #profileDropdown .avatar');
    headerAvatars.forEach(el => {
    el.textContent = initials;
  });
}

/**
 * Escapes HTML characters to prevent XSS.
 */
export function escapeHTML(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Global Topbar Notifications dropdown and badge sync
 */
export function initGlobalNotifications() {
  const notifBtn = document.getElementById('notificationTriggerBtn');
  if (!notifBtn) return;

  const notifMenu = document.getElementById('notificationDropdown');
  const badgeDot = document.getElementById('notificationBadgeDot');
  const profileBtn = document.getElementById('profileTriggerBtn');
  const profileMenu = document.getElementById('profileDropdown');

  if (notifBtn && notifMenu) {
    notifBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      notifMenu.classList.toggle('show');
      if (profileMenu) profileMenu.classList.remove('show');

      if (notifMenu.classList.contains('show')) {
        await loadTopbarNotifications();
        try {
          await fetchApi('/api/notifications/read-all', { method: 'PUT' });
          if (badgeDot) badgeDot.style.display = 'none';
        } catch (err) {
          console.error("Failed to mark notifications as read:", err);
        }
      }
    });
  }

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      profileMenu.classList.toggle('show');
      if (notifMenu) notifMenu.classList.remove('show');
    });
  }

  document.addEventListener('click', () => {
    if (notifMenu) notifMenu.classList.remove('show');
    if (profileMenu) profileMenu.classList.remove('show');
  });

  async function updateUnreadBadge() {
    try {
      const response = await fetchApi('/api/notifications/unread-count');
      if (response && response.ok) {
        const res = await response.json();
        const count = res.data.unread_count || 0;
        if (badgeDot) {
          badgeDot.style.display = count > 0 ? 'block' : 'none';
        }
      }
    } catch (err) {
      console.error("Failed to update unread badge:", err);
    }
  }

  async function loadTopbarNotifications() {
    const listContainer = document.getElementById('notificationsContainer');
    if (!listContainer) return;

    listContainer.innerHTML = '<div style="padding: 1rem; text-align: center; font-size: 11px; color: var(--text-muted);">Loading alerts...</div>';

    try {
      const response = await fetchApi('/api/notifications?limit=5');
      if (!response) return;

      const res = await response.json();
      const list = res.data.notifications || [];

      listContainer.innerHTML = '';
      if (list.length === 0) {
        listContainer.innerHTML = '<div style="padding: 1.5rem 1rem; text-align: center; font-size: 11px; color: var(--text-muted);">No new alerts</div>';
        return;
      }

      list.forEach(notif => {
        const item = document.createElement('div');
        item.className = `notification-item ${!notif.is_read ? 'unread' : ''}`;
        
        let iconName = 'info';
        let iconBg = 'var(--color-primary-light)';
        let iconColor = 'var(--color-primary)';

        if (notif.type.startsWith('budget')) {
          iconName = 'alert-triangle';
          iconBg = 'var(--color-danger-light)';
          iconColor = 'var(--color-danger)';
        } else if (notif.type.startsWith('bill')) {
          iconName = 'calendar';
          iconBg = 'var(--color-warning-light)';
          iconColor = 'var(--color-warning)';
        }

        item.innerHTML = `
          <div class="notification-item-icon" style="background-color: ${iconBg}; color: ${iconColor};">
            <i data-lucide="${iconName}"></i>
          </div>
          <div class="notification-item-content">
            <div class="notification-item-title">${escapeHTML(notif.title)}</div>
            <div class="notification-item-desc">${escapeHTML(notif.message)}</div>
            <div class="notification-item-time">${notif.time_ago || 'just now'}</div>
          </div>
        `;
        listContainer.appendChild(item);
      });

      if (window.lucide) window.lucide.createIcons();
    } catch (err) {
      console.error("Failed to load topbar notifications:", err);
      listContainer.innerHTML = '<div style="padding: 1rem; text-align: center; font-size: 11px; color: var(--color-danger);">Failed to load alerts</div>';
    }
  }

  // Initial sync
  updateUnreadBadge();
}

/**
 * Sets up global logout click handlers with confirmation prompts
 */
export function setupLogout() {
  const logoutElements = document.querySelectorAll('.logout, #sidebarLogout, .sidebar-logout-btn');
  logoutElements.forEach(el => {
    // Clear old listeners if any by cloning, or just override
    const newEl = el.cloneNode(true);
    if (el.parentNode) {
      el.parentNode.replaceChild(newEl, el);
    }

    newEl.addEventListener('click', async (e) => {
      e.preventDefault();
      if (confirm("Are you sure you want to sign out from FinTrack Pro?")) {
        showToast("Signing you out of the secure session...", "info", "Logout Redirect");
        try {
          await fetchApi('/api/auth/logout', { method: 'POST' });
        } catch (err) {
          console.error("Logout error:", err);
        }
        sessionStorage.clear();
        localStorage.clear();
        setTimeout(() => {
          window.location.href = getRoutePath('login');
        }, 1000);
      }
    });
  });
}

/**
 * Fetch wrapper that automatically injects CSRF tokens and handles 401 redirects
 */
let isRefreshing = false;
let refreshSubscribers = [];

function subscribeTokenRefresh(cb) {
  refreshSubscribers.push(cb);
}

function onRefreshed() {
  refreshSubscribers.map(cb => cb());
  refreshSubscribers = [];
}

export async function fetchApi(url, options = {}) {
  options.headers = options.headers || {};
  
  if (!(options.body instanceof FormData) && !options.headers['Content-Type']) {
    options.headers['Content-Type'] = 'application/json';
  }

  const method = (options.method || 'GET').toUpperCase();
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    const token = sessionStorage.getItem('csrf_token');
    if (token) {
      options.headers['X-CSRF-TOKEN'] = token;
    }
  }

  options.credentials = 'include';

  // Support accessing backend from standard front-end Live Server port 5500
  let targetUrl = url;
  if (window.location.port !== '8000' && url.startsWith('/api/')) {
    targetUrl = 'http://localhost:8000' + url;
  }

  try {
    const response = await fetch(targetUrl, options);
    
    if (response.status === 401) {
      // Don't refresh on login or refresh itself
      if (url.includes('/api/auth/login') || url.includes('/api/auth/refresh')) {
        return response;
      }

      // If it is /api/auth/me, we only refresh if we are not on the login/signup page.
      const path = window.location.pathname;
      if (url.includes('/api/auth/me') && (path.includes('login.html') || path.includes('signup.html'))) {
        return response;
      }

      if (!isRefreshing) {
        isRefreshing = true;
        try {
          const refreshUrl = window.location.port !== '8000' ? 'http://localhost:8000/api/auth/refresh' : '/api/auth/refresh';
          const refreshResponse = await fetch(refreshUrl, {
            method: 'POST',
            credentials: 'include'
          });
          
          if (refreshResponse.ok) {
            const refreshResult = await refreshResponse.json();
            if (refreshResult.success) {
              // Update CSRF token if backend returned it
              if (refreshResult.data && refreshResult.data.csrf_token) {
                sessionStorage.setItem('csrf_token', refreshResult.data.csrf_token);
              }
              isRefreshing = false;
              onRefreshed();
            } else {
              throw new Error("Refresh token invalid");
            }
          } else {
            throw new Error("Refresh request failed");
          }
        } catch (refreshErr) {
          isRefreshing = false;
          // Refresh failed - force logout
          sessionStorage.clear();
          localStorage.clear();
          
          const path = window.location.pathname;
          const unprotectedKeywords = [
            'login.html',
            'signup.html',
            'otp.html',
            'verify-email.html',
            'forgot-password.html',
            'reset-password.html',
            'maintenance.html',
            '404.html',
            '500.html'
          ];
          const isLanding = !path.includes('/pages/') && (path.endsWith('/') || path.endsWith('index.html') || path.endsWith('FinTrack%20Pro/') || path.endsWith('FinTrack-Pro-/'));
          const isUnprotected = isLanding || unprotectedKeywords.some(keyword => path.includes(keyword));

          if (!isUnprotected) {
            const loginPath = getRoutePath('login');
            if (!window.location.pathname.endsWith('/login.html') && !window.location.pathname.endsWith('/login')) {
              window.location.href = loginPath;
            }
          }
          return null;
        }
      }

      // Queue the request until refresh finishes
      return new Promise((resolve) => {
        subscribeTokenRefresh(async () => {
          // Re-fetch with new CSRF token if method was state-changing
          if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            const newToken = sessionStorage.getItem('csrf_token');
            if (newToken) {
              options.headers['X-CSRF-TOKEN'] = newToken;
            }
          }
          resolve(await fetch(targetUrl, options));
        });
      });
    }
    
    return response;
  } catch (err) {
    console.error("Fetch API failure:", err);
    throw err;
  }
}

/**
 * Manages modal focus traps, Escape key closure, and backdrop clicks globally
 */
export function initGlobalModalManager() {
  const focusableSelectors = 'input, select, textarea, button, [tabindex="0"]';
  
  const handleKeyDown = (e) => {
    const openModal = document.querySelector('.modal-backdrop.show');
    if (!openModal) return;

    if (e.key === 'Escape') {
      closeModal(openModal);
      return;
    }

    if (e.key === 'Tab') {
      const focusables = Array.from(openModal.querySelectorAll(focusableSelectors))
        .filter(el => el.tabIndex >= 0 && !el.disabled && el.offsetParent !== null);
      
      if (focusables.length === 0) return;

      const firstFocusable = focusables[0];
      const lastFocusable = focusables[focusables.length - 1];

      if (e.shiftKey) { // Shift + Tab
        if (document.activeElement === firstFocusable) {
          lastFocusable.focus();
          e.preventDefault();
        }
      } else { // Tab
        if (document.activeElement === lastFocusable) {
          firstFocusable.focus();
          e.preventDefault();
        }
      }
    }
  };

  const closeModal = (modal) => {
    modal.classList.remove('show');
    const form = modal.querySelector('form');
    if (form) form.reset();
  };

  document.addEventListener('keydown', handleKeyDown);

  // Outside click close
  document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop') && e.target.classList.contains('show')) {
      closeModal(e.target);
    }
  });
}

/**
 * Validates active session status on protected pages and redirects on failure.
 */
export async function checkPageAuth() {
  const path = window.location.pathname;
  
  const unprotectedKeywords = [
    'login.html',
    'signup.html',
    'otp.html',
    'verify-email.html',
    'forgot-password.html',
    'reset-password.html',
    'maintenance.html',
    '404.html',
    '500.html'
  ];
  
  const isLanding = !path.includes('/pages/') && (path.endsWith('/') || path.endsWith('index.html') || path.endsWith('FinTrack%20Pro/') || path.endsWith('FinTrack-Pro-/'));
  const isUnprotected = isLanding || unprotectedKeywords.some(keyword => path.includes(keyword));
  
  if (isUnprotected) {
    return;
  }
  
  try {
    // Before every page loads: Call: GET /api/auth/me
    const response = await fetchApi('/api/auth/me');
    if (!response) return; // fetchApi redirects to login on 401
    
    const result = await response.json();
    if (!result || !result.success || !result.data || !result.data.user) {
      sessionStorage.clear();
      localStorage.clear();
      window.location.href = getRoutePath('login');
      return;
    }

    const user = result.data.user;
    localStorage.setItem('fintrack_user_name', user.full_name);
    localStorage.setItem('fintrack_user_email', user.email);
    localStorage.setItem('fintrack_role', user.role);
    syncUserProfile();

    // Client-side Role Guard for admin page protection
    const currentPage = path.split('/').pop() || '';
    if (currentPage === 'admin.html' && user.role !== 'admin') {
      showToast("Access denied. Admin authorization required.", "danger", "Access Forbidden");
      setTimeout(() => {
        window.location.href = getRoutePath('dashboard');
      }, 1500);
      return;
    }
  } catch (err) {
    console.error("Auth check failure:", err);
    sessionStorage.clear();
    localStorage.clear();
    window.location.href = getRoutePath('login');
  }
}

