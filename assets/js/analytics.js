/**
 * FinTrack Pro - Analytics Controller (Phase 4)
 */

import { formatCurrency } from './utils.js';

export function initAnalyticsPage() {
  const isAnalyticsPage = document.querySelector('.analytics-page-layout');
  if (!isAnalyticsPage) return;

  console.warn("Initializing Analytics Dashboard...");

  renderAnalyticsCharts();
  setupEventListeners();
}

function renderAnalyticsCharts() {
  // Category splits simulation
  const categories = [
    { name: 'salary', amount: 85000, color: 'var(--color-success)' },
    { name: 'food', amount: 4520, color: '#ec4899' },
    { name: 'bills', amount: 1850, color: 'var(--color-primary)' },
    { name: 'others', amount: 0, color: 'var(--color-warning)' }
  ];

  const totalExpenses = 4520 + 1850; // food + bills
  
  // Render expense breakdown text listing
  const container = document.getElementById('expenseBreakdownList');
  if (container) {
    container.innerHTML = '';
    categories.filter(c => c.name !== 'salary').forEach(cat => {
      const share = totalExpenses > 0 ? ((cat.amount / totalExpenses) * 100).toFixed(1) : 0;
      
      const el = document.createElement('div');
      el.className = 'd-flex align-center justify-between';
      el.style.fontSize = '12px';
      el.style.padding = '0.5rem 0';
      el.style.borderBottom = '1px solid var(--border-color)';

      el.innerHTML = `
        <div class="d-flex align-center gap-2">
          <span style="width: 10px; height: 10px; border-radius: 50%; background-color: ${cat.color};"></span>
          <span style="text-transform: capitalize; font-weight: 500;">${cat.name}</span>
        </div>
        <div class="d-flex align-center gap-3">
          <span style="font-weight: 700;">${formatCurrency(cat.amount)}</span>
          <span style="color: var(--text-muted); font-size: 10px;">${share}%</span>
        </div>
      `;
      container.appendChild(el);
    });
  }
}

function setupEventListeners() {
  // Tooltips interaction for Analytics page custom SVGs
  const sectors = document.querySelectorAll('.analytics-donut-sector');
  const tooltip = document.getElementById('analyticsTooltip');

  if (sectors.length > 0 && tooltip) {
    sectors.forEach(sector => {
      sector.addEventListener('mouseenter', (e) => {
        const name = e.currentTarget.getAttribute('data-name');
        const value = parseFloat(e.currentTarget.getAttribute('data-value'));
        
        tooltip.innerHTML = `
          <div style="font-weight: 800; font-size: 11px; text-transform: capitalize; color: var(--text-primary); margin-bottom: 0.15rem;">${name} Allocation</div>
          <div style="font-weight: 700; color: var(--color-primary);">${formatCurrency(value)}</div>
        `;
        tooltip.style.opacity = '1';
        tooltip.style.display = 'block';
      });

      sector.addEventListener('mousemove', (e) => {
        const rect = e.currentTarget.offsetParent.getBoundingClientRect();
        const top = e.clientY - rect.top - tooltip.offsetHeight - 12;
        const left = e.clientX - rect.left - tooltip.offsetWidth / 2;
        
        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
      });

      sector.addEventListener('mouseleave', () => {
        tooltip.style.opacity = '0';
        tooltip.style.display = 'none';
      });
    });
  }
}
