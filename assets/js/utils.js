/**
 * FinTrack Pro - Utility Functions & Component Helpers
 */

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
