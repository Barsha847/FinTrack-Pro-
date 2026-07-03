/**
 * FinTrack Pro - Notifications Center Controller (Phase 4)
 */

export function initNotificationsPage() {
  const isNotifPage = document.querySelector('.notifications-page-layout');
  if (!isNotifPage) return;

  console.warn("Initializing Notifications Center Timeline...");

  const container = document.getElementById('fullNotificationTimeline');
  if (!container) return;

  const list = [
    { id: 1, type: 'overrun', title: 'Food Budget Alert', desc: 'You have utilized 72% of your monthly food budget limit.', time: '10m ago', date: '2026-07-03' },
    { id: 2, type: 'reminder', title: 'AWS Invoice Pending', desc: 'AWS subscription charge of ₹1,850 is due in 2 days.', time: '2h ago', date: '2026-07-03' },
    { id: 3, type: 'system', title: 'Backup Automated Success', desc: 'PostgreSQL daily automated schema backup was completed.', time: '1d ago', date: '2026-07-02' }
  ];

  container.innerHTML = '';
  
  list.forEach(notif => {
    const card = document.createElement('div');
    card.className = 'card-glass';
    card.style.padding = '1.25rem';
    card.style.marginBottom = '1rem';
    card.style.display = 'flex';
    card.style.gap = '1rem';
    card.style.alignItems = 'center';

    let icon = 'info';
    let iconBg = 'var(--color-primary-light)';
    let iconColor = 'var(--color-primary)';
    if (notif.type === 'overrun') {
      icon = 'alert-triangle';
      iconBg = 'var(--color-danger-light)';
      iconColor = 'var(--color-danger)';
    } else if (notif.type === 'reminder') {
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
          <h5 style="font-weight: 700; margin: 0; font-size: var(--fs-xs); color: var(--text-primary);">${notif.title}</h5>
          <span style="font-size: 10px; color: var(--text-muted);">${notif.time} • ${notif.date}</span>
        </div>
        <p style="margin: 0; font-size: 11px; color: var(--text-secondary); line-height: 1.4;">${notif.desc}</p>
      </div>
    `;
    container.appendChild(card);
  });

  if (window.lucide) window.lucide.createIcons();
}
