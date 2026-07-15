/**
 * FinTrack Pro - Notifications Center Controller (Phase 6)
 */

import { fetchApi, showToast } from './utils.js';

export function initNotificationsPage() {
  const isNotifPage = document.querySelector('.notifications-page-layout');
  if (!isNotifPage) return;

  console.warn("Initializing Notifications Center Timeline...");

  const container = document.getElementById('fullNotificationTimeline');
  if (!container) return;

  // Initial load
  loadNotifications(container);
}

async function loadNotifications(container) {
  container.innerHTML = '<div style="padding: 3rem; text-align: center; color: var(--text-secondary);">Loading notification logs...</div>';

  try {
    const response = await fetchApi('/api/notifications');
    if (!response) return;

    const result = await response.json();
    if (result && result.success) {
      const list = result.data.notifications || [];
      renderNotificationsTimeline(container, list);
    } else {
      container.innerHTML = `<div style="padding: 3rem; text-align: center; color: var(--color-danger);">${result?.message || 'Failed to fetch logs.'}</div>`;
    }
  } catch (err) {
    console.error("Failed to load notifications center:", err);
    container.innerHTML = '<div style="padding: 3rem; text-align: center; color: var(--color-danger);">Error connecting to database.</div>';
  }
}

function renderNotificationsTimeline(container, list) {
  container.innerHTML = '';

  if (list.length === 0) {
    container.innerHTML = `
      <div style="padding: 4rem 2rem; text-align: center;">
        <div class="empty-state-container">
          <svg class="empty-state-svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
          </svg>
          <div class="empty-state-title">No notifications</div>
          <div class="empty-state-desc">Your alert log is currently clear. Budgets and bills are running healthy.</div>
        </div>
      </div>
    `;
    return;
  }

  list.forEach(notif => {
    const card = document.createElement('div');
    card.className = `card-glass notification-timeline-card ${!notif.is_read ? 'unread-highlight' : ''}`;
    card.style.padding = '1.25rem';
    card.style.marginBottom = '1rem';
    card.style.display = 'flex';
    card.style.gap = '1rem';
    card.style.alignItems = 'center';
    card.style.transition = 'background-color 0.2s';
    card.style.cursor = !notif.is_read ? 'pointer' : 'default';

    // Highlight styling for unread items
    if (!notif.is_read) {
      card.style.borderLeft = '4px solid var(--color-primary)';
      card.style.backgroundColor = 'rgba(79, 70, 229, 0.05)';
    }

    let icon = 'info';
    let iconBg = 'var(--color-primary-light)';
    let iconColor = 'var(--color-primary)';
    if (notif.type.startsWith('budget')) {
      icon = 'alert-triangle';
      iconBg = 'var(--color-danger-light)';
      iconColor = 'var(--color-danger)';
    } else if (notif.type.startsWith('bill')) {
      icon = 'calendar';
      iconBg = 'var(--color-warning-light)';
      iconColor = 'var(--color-warning)';
    }

    card.innerHTML = `
      <div style="width: 40px; height: 40px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; background-color: ${iconBg}; color: ${iconColor}; flex-shrink: 0;">
        <i data-lucide="${icon}"></i>
      </div>
      <div style="flex: 1; min-width: 0;">
        <div class="d-flex justify-between align-center" style="margin-bottom: 0.15rem; flex-wrap: wrap; gap: 0.5rem;">
          <h5 style="font-weight: 700; margin: 0; font-size: var(--fs-xs); color: var(--text-primary);">${escapeHTML(notif.title)}</h5>
          <span style="font-size: 10px; color: var(--text-muted);">${notif.time_ago} • ${new Date(notif.created_at).toLocaleDateString()}</span>
        </div>
        <p style="margin: 0; font-size: 11px; color: var(--text-secondary); line-height: 1.4; margin-bottom: 0.5rem;">${escapeHTML(notif.message)}</p>
        <div class="d-flex align-center gap-2" style="font-size: 10px;">
          ${!notif.is_read ? `<button class="btn btn-secondary btn-sm mark-read-btn" data-id="${notif.id}" style="padding: 0.2rem 0.5rem; height: auto; font-size: 9px;">Mark Read</button>` : ''}
          <button class="btn btn-danger-outline btn-sm delete-notif-btn" data-id="${notif.id}" style="padding: 0.2rem 0.5rem; height: auto; font-size: 9px; border: 1px solid var(--color-danger); color: var(--color-danger); background: transparent; border-radius: 4px; cursor: pointer;">Dismiss</button>
        </div>
      </div>
    `;

    // Mark single notification read click handler
    if (!notif.is_read) {
      card.addEventListener('click', async (e) => {
        if (e.target.closest('.delete-notif-btn')) return; // Ignore if delete clicked
        await markAsRead(notif.id, container);
      });
    }

    // Dismiss click handler
    const deleteBtn = card.querySelector('.delete-notif-btn');
    if (deleteBtn) {
      deleteBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        await deleteNotification(notif.id, container);
      });
    }

    container.appendChild(card);
  });

  if (window.lucide) window.lucide.createIcons();
}

async function markAsRead(id, container) {
  try {
    const response = await fetchApi(`/api/notifications/${id}/read`, { method: 'PUT' });
    if (response && response.ok) {
      // Re-load list and refresh topbar badge via window event or direct DOM sync
      const badgeDot = document.getElementById('notificationBadgeDot');
      if (badgeDot) {
        // Decrease badge or fetch count again
        const countResponse = await fetchApi('/api/notifications/unread-count');
        if (countResponse && countResponse.ok) {
          const res = await countResponse.json();
          badgeDot.style.display = (res.data.unread_count || 0) > 0 ? 'block' : 'none';
        }
      }
      loadNotifications(container);
    }
  } catch (err) {
    console.error("Failed to mark read:", err);
  }
}

async function deleteNotification(id, container) {
  try {
    const response = await fetchApi(`/api/notifications/${id}`, { method: 'DELETE' });
    if (response && response.ok) {
      showToast("Notification alert dismissed.", "success", "Alert Dismissed");
      loadNotifications(container);
    } else {
      showToast("Failed to dismiss alert notification.", "danger", "Action Error");
    }
  } catch (err) {
    console.error("Failed to delete notification:", err);
    showToast("Server connection error during dismiss.", "danger", "Connection Error");
  }
}

function escapeHTML(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
