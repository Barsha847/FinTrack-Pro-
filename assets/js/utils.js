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
    
    if (response.status === 401 && !url.includes('/api/auth/me') && !url.includes('/api/auth/login')) {
      sessionStorage.clear();
      window.location.href = getRoutePath('login');
      return null;
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
