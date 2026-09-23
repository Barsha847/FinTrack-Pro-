/**
 * FinTrack Pro - Dashboard Core Engine (Phase 2)
 * Handles state management, dynamic statistics, LocalStorage persistence,
 * search filters, notification alerts, loading skeletons, modals, and accessibility traps.
 */

import { showToast } from './utils.js';

// Centralized Dashboard State
const DashboardState = {
  transactions: [],
  notifications: [],
  totals: {
    balance: 482930.00,
    income: 95200.00,
    expense: 32450.00
  },
  searchQuery: '',
  showEmptyState: false
};

// LocalStorage Keys
const TX_STORAGE_KEY = 'fintrack_transactions_v1';
const SEED_FLAG_KEY = 'fintrack_seeded_v1';

// Seed Data Definition
const DEFAULT_TRANSACTIONS = [
  { id: 1, title: 'Regular Salary Credited', amount: 85000.00, type: 'income', date: '2026-06-28', category: 'salary' },
  { id: 2, title: 'Whole Foods Outflow', amount: 4520.00, type: 'expense', date: '2026-06-27', category: 'food' },
  { id: 3, title: 'AWS Cloud Hosting Invoice', amount: 1850.00, type: 'expense', date: '2026-06-26', category: 'bills' }
];

const DEFAULT_NOTIFICATIONS = [
  { id: 1, type: 'overrun', title: 'Food Budget Alert', desc: 'You have utilized 72% of your monthly food budget limit.', time: '10m ago', unread: true },
  { id: 2, type: 'reminder', title: 'AWS Invoice Pending', desc: 'AWS subscription charge of ₹1,850 is due in 2 days.', time: '2h ago', unread: true },
  { id: 3, type: 'system', title: 'Backup Automated Success', desc: 'PostgreSQL daily automated schema backup was completed.', time: '1d ago', unread: false }
];

export function initDashboard() {
  const isDashboardPage = document.querySelector('.dashboard-page-layout');
  if (!isDashboardPage) return;

  console.warn("Initializing FinTrack Pro Dashboard (Phase 2)...");

  loadDashboardState();
  setupDropdowns();
  setupSearch();
  setupRefreshSimulator();
  setupAddTransactionModal();
  setupDeveloperToggles();
  setupChartTooltip();

  // Initial render
  recalculateTotals();
  renderDashboard();
}

// ==========================================
// STATE MANAGEMENT & LOCAL STORAGE
// ==========================================

function loadDashboardState() {
  const transactionData = localStorage.getItem(TX_STORAGE_KEY);
  const isSeeded = localStorage.getItem(SEED_FLAG_KEY);

  if (!isSeeded || !transactionData) {
    // Seed initial demo data
    DashboardState.transactions = [...DEFAULT_TRANSACTIONS];
    DashboardState.notifications = [...DEFAULT_NOTIFICATIONS];
    localStorage.setItem(TX_STORAGE_KEY, JSON.stringify(DashboardState.transactions));
    localStorage.setItem(SEED_FLAG_KEY, 'true');
    console.warn("Seeded default dashboard items to LocalStorage");
  } else {
    try {
      DashboardState.transactions = JSON.parse(transactionData);
      DashboardState.notifications = [...DEFAULT_NOTIFICATIONS]; // Seed notifications fresh on session
      console.warn("Restored dashboard transactions from LocalStorage:", DashboardState.transactions.length);
    } catch (e) {
      console.error("Failed to parse transactions, resetting state.", e);
      DashboardState.transactions = [...DEFAULT_TRANSACTIONS];
      localStorage.setItem(TX_STORAGE_KEY, JSON.stringify(DashboardState.transactions));
    }
  }
}

function saveTransactionsToStorage() {
  localStorage.setItem(TX_STORAGE_KEY, JSON.stringify(DashboardState.transactions));
}

function recalculateTotals() {
  let totalIncome = 0;
  let totalExpense = 0;

  DashboardState.transactions.forEach(tx => {
    if (tx.type === 'income') {
      totalIncome += tx.amount;
    } else if (tx.type === 'expense') {
      totalExpense += tx.amount;
    }
  });

  // Default baseline balance offset + dynamic math
  const baseline = 420180.00;
  DashboardState.totals.income = totalIncome;
  DashboardState.totals.expense = totalExpense;
  DashboardState.totals.balance = baseline + totalIncome - totalExpense;
}

// ==========================================
// RENDERING ENGINES
// ==========================================

function renderDashboard() {
  renderTotals();
  renderTransactionsList();
  renderNotificationsList();
  renderBudgetBars();
}

function renderTotals() {
  const balanceEl = document.getElementById('totalBalance');
  const incomeEl = document.getElementById('totalIncome');
  const expenseEl = document.getElementById('totalExpense');

  if (balanceEl) balanceEl.textContent = '₹' + DashboardState.totals.balance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (incomeEl) incomeEl.textContent = '₹' + DashboardState.totals.income.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (expenseEl) expenseEl.textContent = '₹' + DashboardState.totals.expense.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderTransactionsList() {
  const container = document.getElementById('transactionsContainer');
  if (!container) return;

  // Clear previous contents
  container.innerHTML = '';

  // Apply filters
  const filtered = DashboardState.transactions.filter(tx => {
    return tx.title.toLowerCase().includes(DashboardState.searchQuery.toLowerCase()) ||
      tx.category.toLowerCase().includes(DashboardState.searchQuery.toLowerCase());
  });

  // Handle Empty State
  if (DashboardState.showEmptyState || filtered.length === 0) {
    container.appendChild(createEmptyStateMarkup());
    return;
  }

  // Populate transaction feed
  filtered.forEach(tx => {
    const item = document.createElement('div');
    item.className = 'transaction-feed-item';

    // Choose Category Icon
    let iconName = 'arrow-up-right';
    let iconBg = 'var(--color-danger-light)';
    let iconColor = 'var(--color-danger)';

    if (tx.type === 'income') {
      iconName = 'arrow-down-left';
      iconBg = 'var(--color-success-light)';
      iconColor = 'var(--color-success)';
    } else if (tx.category === 'bills') {
      iconName = 'cpu';
      iconBg = 'var(--color-primary-light)';
      iconColor = 'var(--color-primary)';
    } else if (tx.category === 'food') {
      iconName = 'shopping-bag';
      iconBg = 'rgba(236, 72, 153, 0.15)';
      iconColor = '#ec4899';
    }

    const amountFormatted = (tx.type === 'income' ? '+' : '-') + '₹' + tx.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    const amountClass = tx.type === 'income' ? 'text-success' : 'text-danger';

    item.innerHTML = `
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div class="transaction-icon" style="background-color: ${iconBg}; color: ${iconColor};">
          <i data-lucide="${iconName}"></i>
        </div>
        <div>
          <div class="transaction-title">${escapeHTML(tx.title)}</div>
          <div class="transaction-meta">${tx.date} • ${escapeHTML(tx.category)}</div>
        </div>
      </div>
      <div class="transaction-amount ${amountClass}">${amountFormatted}</div>
    `;

    container.appendChild(item);
  });

  // Rerender Lucide vector icons
  if (window.lucide) window.lucide.createIcons();
}

function renderNotificationsList() {
  // Handled globally in utils.js
}

function renderBudgetBars() {
  const savingsProgressEl = document.getElementById('savingsGoalProgress');
  const savingsPctText = document.getElementById('savingsGoalPct');

  // Example dynamic rendering values
  if (savingsProgressEl) {
    const balance = DashboardState.totals.balance;
    const target = 580000.00;
    const progress = Math.min((balance / target) * 100, 100);

    savingsProgressEl.style.width = `${progress}%`;
    if (savingsPctText) {
      savingsPctText.textContent = `${Math.round(progress)}% Complete`;
    }
  }
}

// ==========================================
// DROPDOWNS OVERLAY
// ==========================================

function setupDropdowns() {
  // Handled globally in utils.js
}

// ==========================================
// SEARCH FILTERS
// ==========================================

function setupSearch() {
  const searchInput = document.getElementById('dashboardSearchInput');
  if (!searchInput) return;

  searchInput.addEventListener('input', () => {
    DashboardState.searchQuery = searchInput.value;
    renderTransactionsList();
  });
}

// ==========================================
// LOADING SKELETONS REFRESH SIMULATION
// ==========================================

function setupRefreshSimulator() {
  const refreshBtn = document.getElementById('dashboardRefreshBtn');
  if (!refreshBtn) return;

  refreshBtn.addEventListener('click', () => {
    triggerSkeletonLoad();
  });
}

function triggerSkeletonLoad() {
  const balanceCard = document.getElementById('totalBalance');
  const incomeCard = document.getElementById('totalIncome');
  const expenseCard = document.getElementById('totalExpense');
  const txContainer = document.getElementById('transactionsContainer');

  if (!txContainer) return;

  // Add rotating animation class to refresh icon if present
  const refreshIcon = document.querySelector('#dashboardRefreshBtn i');
  if (refreshIcon) refreshIcon.style.transform = 'rotate(360deg)';

  // Swap content containers to skeleton layout
  if (balanceCard) balanceCard.innerHTML = '<div class="skeleton skeleton-value" style="width: 80%;"></div>';
  if (incomeCard) incomeCard.innerHTML = '<div class="skeleton skeleton-value" style="width: 70%;"></div>';
  if (expenseCard) expenseCard.innerHTML = '<div class="skeleton skeleton-value" style="width: 60%;"></div>';

  txContainer.innerHTML = `
    <div class="transaction-feed-item skeleton" style="height: 60px; margin-bottom: 0.75rem;"></div>
    <div class="transaction-feed-item skeleton" style="height: 60px; margin-bottom: 0.75rem;"></div>
    <div class="transaction-feed-item skeleton" style="height: 60px;"></div>
  `;

  // Restore values after 1.2s delay
  setTimeout(() => {
    if (refreshIcon) refreshIcon.style.transform = 'none';
    recalculateTotals();
    renderDashboard();
    showToast("Dashboard summary metrics synced successfully.", "success", "Metrics Synced");
  }, 1200);
}

// ==========================================
// ADD TRANSACTION MODAL & FOCUS TRAPS
// ==========================================

function setupAddTransactionModal() {
  const openBtn = document.getElementById('openAddTxModalBtn');
  const modal = document.getElementById('transactionModal');
  const closeBtn = document.getElementById('closeModalBtn');
  const cancelBtn = document.getElementById('cancelModalBtn');
  const form = document.getElementById('addTransactionForm');

  if (!modal || !openBtn) return;

  const openModal = () => {
    modal.classList.add('show');

    // Set initial focus
    const firstInput = document.getElementById('txTitle');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 100);
    }
    console.warn("Add Transaction modal opened.");
  };

  const closeModal = () => {
    modal.classList.remove('show');
    form.reset();
    console.warn("Add Transaction modal closed.");
  };

  // Open listeners
  openBtn.addEventListener('click', openModal);

  // Close listeners
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

  // Form Submit Action
  if (form) {
    // Custom validity flag
    form.dataset.valid = 'true';

    form.addEventListener('submit', (e) => {
      e.preventDefault();

      const title = document.getElementById('txTitle').value.trim();
      const amount = parseFloat(document.getElementById('txAmount').value);
      const type = document.getElementById('txType').value;
      const category = document.getElementById('txCategory').value;
      const date = document.getElementById('txDate').value;

      if (!title || isNaN(amount) || amount <= 0) {
        showToast("Please input valid transaction parameters.", "danger", "Validation Error");
        return;
      }

      // Add to state
      const newTx = {
        id: Date.now(),
        title,
        amount,
        type,
        date: date || new Date().toISOString().split('T')[0],
        category
      };

      DashboardState.transactions.unshift(newTx); // Add to beginning of array
      saveTransactionsToStorage();

      // Update UI
      recalculateTotals();
      renderDashboard();
      closeModal();

      showToast(`Transaction "${title}" logged successfully.`, "success", "Transaction Created");
    });
  }
}

// ==========================================
// DEVELOPER EMPTY STATE TOGGLE
// ==========================================

function setupDeveloperToggles() {
  const devCard = document.createElement('div');
  devCard.className = 'card-glass';
  devCard.style.position = 'fixed';
  devCard.style.bottom = '1rem';
  devCard.style.left = '1rem';
  devCard.style.padding = '0.75rem';
  devCard.style.zIndex = 'var(--z-nav)';
  devCard.style.fontSize = '11px';
  devCard.style.display = 'flex';
  devCard.style.alignItems = 'center';
  devCard.style.gap = '0.5rem';
  devCard.style.boxShadow = 'var(--shadow-lg)';

  devCard.innerHTML = `
    <label class="form-check" style="margin: 0; font-size: 11px;">
      <input type="checkbox" id="devEmptyStateToggle" class="form-check-input">
      <span>Mock Empty State</span>
    </label>
    <button class="btn btn-secondary btn-sm" id="devResetStorageBtn" style="padding: 0.25rem 0.5rem; font-size: 10px; height: auto;">Reset Db</button>
  `;

  document.body.appendChild(devCard);

  const toggle = document.getElementById('devEmptyStateToggle');
  if (toggle) {
    toggle.addEventListener('change', () => {
      DashboardState.showEmptyState = toggle.checked;
      renderTransactionsList();
      showToast(
        toggle.checked ? "Swapped transactions container to empty view." : "Restored transactions feed rows.",
        "info",
        "Mock View Swapped"
      );
    });
  }

  const resetBtn = document.getElementById('devResetStorageBtn');
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      localStorage.removeItem(TX_STORAGE_KEY);
      localStorage.removeItem(SEED_FLAG_KEY);
      showToast("Cleared transactions state storage cache. Reloading...", "warning", "Cache Cleared");
      setTimeout(() => window.location.reload(), 1000);
    });
  }
}

// Markup Generator for Empty State (SVG based)
function createEmptyStateMarkup() {
  const el = document.createElement('div');
  el.className = 'empty-state-container reveal';

  el.innerHTML = `
    <svg class="empty-state-svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h.007v.008H3.75V4.5zm.007 2.25h.008v.008H3.75V6.75zm.007 2.25h.008v.008H3.75V9zm.007 2.25h.008v.008H3.75V11.25zm.007 2.25h.008v.008H3.75V13.5zm.007 2.25h.008v.008H3.75V15.75zM12 5.25h.007v.008H12V5.25zm.007 2.25h.008v.008H12V7.5zm.007 2.25h.008v.008H12V9.75zm.007 2.25h.008v.008H12v-.008zm.007 2.25h.008v.008H12V14.25zm.007 2.25h.008v.008H12V16.5zm-5.617-10.74h.007v.008H6.383v-.008zm.007 2.25h.008v.008H6.383V8.01zm.007 2.25h.008v.008H6.383V10.26zm.007 2.25h.008v.008H6.383v-.008zm.007 2.25h.008v.008H6.383v-.008zm20.25 2.25h.008v.008H21v-.008z" />
    </svg>
    <div class="empty-state-title">No transactions logged yet</div>
    <div class="empty-state-desc">Your personal finance ledger is currently clear. Add a transaction log to start monitoring your budgets.</div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('openAddTxModalBtn').click()">
      <i data-lucide="plus-circle" style="width: 14px; height: 14px; vertical-align: middle;"></i> Log First Inflow
    </button>
  `;

  return el;
}

// Utility helper to sanitize output strings
function escapeHTML(str) {
  return str.replace(/[&<>'"]/g,
    tag => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[tag] || tag)
  );
}

// ==========================================
// SHARP SVG CHART TOOLTIP INTERACTION
// ==========================================
const monthlyData = [
  { month: 'February', inflow: 60000, outflow: 40000, diff: 20000, growth: '+2.4%' },
  { month: 'March', inflow: 75000, outflow: 45000, diff: 30000, growth: '+3.1%' },
  { month: 'April', inflow: 80000, outflow: 50000, diff: 30000, growth: '+5.0%' },
  { month: 'May', inflow: 90000, outflow: 38000, diff: 52000, growth: '+6.8%' },
  { month: 'June', inflow: 95200, outflow: 32450, diff: 62750, growth: '+8.2%' },
  { month: 'July (Forecast)', inflow: 110000, outflow: 25000, diff: 85000, growth: '+12.4%' }
];

function setupChartTooltip() {
  const triggers = document.querySelectorAll('.chart-point-trigger');
  const tooltip = document.getElementById('chartTooltip');
  if (!tooltip || triggers.length === 0) return;

  triggers.forEach(trigger => {
    trigger.addEventListener('mouseenter', (e) => {
      const idx = parseInt(e.currentTarget.getAttribute('data-idx'));
      const data = monthlyData[idx];
      if (!data) return;

      tooltip.innerHTML = `
        <div class="chart-tooltip-title">${data.month}</div>
        <div class="chart-tooltip-row">
          <span class="chart-tooltip-label">Inflow:</span>
          <span class="chart-tooltip-val text-success">₹${data.inflow.toLocaleString('en-IN')}</span>
        </div>
        <div class="chart-tooltip-row">
          <span class="chart-tooltip-label">Outflow:</span>
          <span class="chart-tooltip-val text-danger">₹${data.outflow.toLocaleString('en-IN')}</span>
        </div>
        <div class="chart-tooltip-row">
          <span class="chart-tooltip-label">Difference:</span>
          <span class="chart-tooltip-val" style="color: var(--text-primary);">₹${data.diff.toLocaleString('en-IN')}</span>
        </div>
        <div class="chart-tooltip-row">
          <span class="chart-tooltip-label">Growth:</span>
          <span class="chart-tooltip-val text-success">${data.growth}</span>
        </div>
      `;

      tooltip.classList.add('show');
    });

    trigger.addEventListener('mousemove', (e) => {
      const rect = e.currentTarget.getBoundingClientRect();
      const offsetParent = tooltip.offsetParent || e.currentTarget.closest('.card-glass') || document.body;
      const parentRect = offsetParent.getBoundingClientRect();

      // Calculate top and left positions relative to parent container
      let top = rect.top - parentRect.top - tooltip.offsetHeight - 12;
      let left = rect.left - parentRect.left + rect.width / 2 - tooltip.offsetWidth / 2;

      // Boundaries protection
      if (left < 10) left = 10;
      if (left + tooltip.offsetWidth > parentRect.width - 10) {
        left = parentRect.width - tooltip.offsetWidth - 10;
      }
      if (top < 10) {
        top = rect.top - parentRect.top + rect.height + 12;
      }

      tooltip.style.top = `${top}px`;
      tooltip.style.left = `${left}px`;
    });

    trigger.addEventListener('mouseleave', () => {
      tooltip.classList.remove('show');
    });
  });
}
