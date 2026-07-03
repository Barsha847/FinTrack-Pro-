/**
 * FinTrack Pro - Budgets Controller (Phase 2)
 */

import { showToast } from './utils.js';

// LocalStorage Namespaces
const BUDGET_STORAGE_KEY = 'fintrack_budgets_v1';
const TX_STORAGE_KEY = 'fintrack_transactions_v1';

let budgetsList = [];
let expensesList = [];
let currentPage = 1;
const itemsPerPage = 8;

export function initBudgetsPage() {
  const isBudgetsPage = document.querySelector('.budgets-page-layout');
  if (!isBudgetsPage) return;

  console.warn("Initializing Budgets Module...");

  loadBudgetData();
  setupEventListeners();
  renderBudgetsPage();
}

function loadBudgetData() {
  // Load budgets list
  const data = localStorage.getItem(BUDGET_STORAGE_KEY);
  if (data) {
    try { budgetsList = JSON.parse(data); } catch (e) { budgetsList = []; }
  } else {
    budgetsList = [
      { id: 1, category: 'food', limit: 12000.00 },
      { id: 2, category: 'bills', limit: 8000.00 }
    ];
    localStorage.setItem(BUDGET_STORAGE_KEY, JSON.stringify(budgetsList));
  }

  // Load transactions for spent calculation
  const txData = localStorage.getItem(TX_STORAGE_KEY);
  if (txData) {
    try {
      const allTx = JSON.parse(txData);
      expensesList = allTx.filter(tx => tx.type === 'expense');
    } catch (e) {
      expensesList = [];
    }
  }
}

function saveBudgetData() {
  localStorage.setItem(BUDGET_STORAGE_KEY, JSON.stringify(budgetsList));
}

function renderBudgetsPage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const countEl = document.getElementById('activeBudgetsVal');
  const limitEl = document.getElementById('totalLimitsVal');
  const spentEl = document.getElementById('overallSpentVal');
  const overrunEl = document.getElementById('overrunCountVal');

  let totalLimit = 0;
  let totalSpent = 0;
  let overrunCount = 0;

  // Calculate spent per category
  const spentByCat = {};
  expensesList.forEach(item => {
    spentByCat[item.category] = (spentByCat[item.category] || 0) + item.amount;
  });

  budgetsList.forEach(item => {
    totalLimit += item.limit;
    const spent = spentByCat[item.category] || 0;
    totalSpent += spent;

    if (spent > item.limit) {
      overrunCount++;
    }
  });

  if (countEl) countEl.textContent = `${budgetsList.length} Budgets`;
  if (limitEl) limitEl.textContent = '₹' + totalLimit.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (spentEl) spentEl.textContent = '₹' + totalSpent.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (overrunEl) {
    overrunEl.textContent = overrunCount > 0 ? `${overrunCount} Category${overrunCount > 1 ? 's' : ''}` : '0 Categories';
    if (overrunCount > 0) {
      overrunEl.className = 'stats-value text-danger';
    } else {
      overrunEl.className = 'stats-value';
    }
  }
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  // Calculate spent per category
  const spentByCat = {};
  expensesList.forEach(item => {
    spentByCat[item.category] = (spentByCat[item.category] || 0) + item.amount;
  });

  const totalItems = budgetsList.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;

  const startIdx = (currentPage - 1) * itemsPerPage;
  const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
  const paginated = budgetsList.slice(startIdx, endIdx);

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');
  const infoSpan = document.getElementById('paginationInfo');

  if (prevBtn) prevBtn.disabled = currentPage === 1;
  if (nextBtn) nextBtn.disabled = currentPage === totalPages || totalItems === 0;
  if (infoSpan) {
    infoSpan.textContent = totalItems > 0 
      ? `Showing ${startIdx + 1}-${endIdx} of ${totalItems} items` 
      : 'Showing 0-0 of 0 items';
  }

  if (totalItems === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="padding: 3rem; text-align: center;">
          <div class="empty-state-container">
            <svg class="empty-state-svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="empty-state-title">No category limits set</div>
            <div class="empty-state-desc">Configure monthly category budget constraints to safeguard your wallets.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  paginated.forEach(item => {
    const spent = spentByCat[item.category] || 0;
    const ratio = Math.min((spent / item.limit) * 100, 100);
    const ratioRounded = Math.round((spent / item.limit) * 100);
    
    // Choose status badges
    let barColor = 'linear-gradient(135deg, var(--color-primary-start), var(--color-primary-end))';
    let textClass = 'text-primary';
    if (spent > item.limit) {
      barColor = 'var(--color-danger)';
      textClass = 'text-danger';
    } else if (spent > item.limit * 0.7) {
      barColor = 'var(--color-warning)';
      textClass = 'text-warning';
    }

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary); text-transform: capitalize;">${item.category}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">₹${item.limit.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;" class="${textClass}">₹${spent.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex align-center gap-2">
          <div style="flex: 1; height: 8px; background-color: var(--bg-secondary); border-radius: var(--radius-full); overflow: hidden;">
            <div style="width: ${ratio}%; height: 100%; background: ${barColor}; border-radius: var(--radius-full); transition: width 0.4s ease;"></div>
          </div>
          <span style="font-size: 10px; font-weight: 700;" class="${textClass}">${ratioRounded}%</span>
        </div>
      </td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
          <button class="btn btn-secondary btn-sm edit-budget-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto;">
            <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i>
          </button>
          <button class="btn btn-secondary btn-sm delete-budget-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; color: var(--color-danger);">
            <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
          </button>
        </div>
      </td>
    `;

    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('.edit-budget-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-budget-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      deleteBudget(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const openModalBtn = document.getElementById('openAddBudgetModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('budgetModal');
  const form = document.getElementById('budgetForm');
  const refreshBtn = document.getElementById('pageRefreshBtn');

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadBudgetData();
      renderBudgetsPage();
      showToast("Budget thresholds recalculated against real expenses.", "success", "Synced Limits");
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        renderTable();
      }
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      currentPage++;
      renderTable();
    });
  }

  if (openModalBtn && modal) {
    openModalBtn.addEventListener('click', () => {
      document.getElementById('modalTitle').textContent = 'Configure Category Limit';
      form.reset();
      document.getElementById('budgetId').value = '';
      modal.classList.add('show');
    });
  }

  const closeModal = () => { if (modal) modal.classList.remove('show'); };
  if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
  if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const id = document.getElementById('budgetId').value;
      const category = document.getElementById('budgetCategory').value;
      const limit = parseFloat(document.getElementById('budgetLimit').value);

      if (isNaN(limit) || limit <= 0) {
        showToast("Please enter a valid limit.", "danger", "Validation Error");
        return;
      }

      if (id) {
        const index = budgetsList.findIndex(x => x.id === parseInt(id));
        if (index !== -1) {
          budgetsList[index] = { ...budgetsList[index], category, limit };
          showToast(`Budget limit updated for "${category}".`, "success", "Limit Updated");
        }
      } else {
        // Prevent duplicate category limits
        if (budgetsList.some(b => b.category === category)) {
          showToast(`A budget limit for "${category}" already exists.`, "danger", "Limit Configuration Error");
          return;
        }

        const newBudget = {
          id: Date.now(),
          category,
          limit
        };
        budgetsList.push(newBudget);
        showToast(`Budget limit configured for "${category}".`, "success", "Limit Configured");
      }

      saveBudgetData();
      renderBudgetsPage();
      closeModal();
    });
  }
}

function openEditModal(id) {
  const item = budgetsList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Modify Category Limit';
  document.getElementById('budgetId').value = item.id;
  document.getElementById('budgetCategory').value = item.category;
  document.getElementById('budgetLimit').value = item.limit;

  const modal = document.getElementById('budgetModal');
  if (modal) {
    modal.classList.add('show');
  }
}

function deleteBudget(id) {
  if (confirm("Are you sure you want to remove this budget limit configuration?")) {
    const item = budgetsList.find(x => x.id === id);
    const catName = item ? item.category : '';
    budgetsList = budgetsList.filter(x => x.id !== id);
    saveBudgetData();
    renderBudgetsPage();
    showToast(`Budget limit configuration for "${catName}" removed.`, "warning", "Limit Removed");
  }
}
