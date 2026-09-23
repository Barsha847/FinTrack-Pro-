/**
 * FinTrack Pro - Budgets Controller (Phase 6)
 */

import { showToast, fetchApi, escapeHTML } from './utils.js';

let budgetsList = [];
let categoriesList = [];
let currentPage = 1;
const itemsPerPage = 8;

export function initBudgetsPage() {
  const isBudgetsPage = document.querySelector('.budgets-page-layout');
  if (!isBudgetsPage) return;

  console.warn("Initializing Budgets Module...");

  loadBudgetData();
  setupEventListeners();
}

async function loadBudgetData() {
  try {
    // 1. Fetch categories
    const catResponse = await fetchApi('/api/categories?type=expense');
    if (catResponse && catResponse.ok) {
      const catRes = await catResponse.json();
      categoriesList = catRes.data.categories || [];
      populateCategorySelects();
    }

    // 2. Fetch budgets
    const response = await fetchApi('/api/budgets');
    if (!response) return;

    const result = await response.json();
    if (result && result.success) {
      budgetsList = (result.data.budgets || []).map(b => ({
        id: b.id,
        category_id: b.category_id,
        category: b.category_name,
        limit: parseFloat(b.amount),
        spent: parseFloat(b.spent || 0),
        progress_percent: parseFloat(b.progress_percent || 0)
      }));
      renderBudgetsPage();
    } else {
      budgetsList = [];
      showToast(result?.message || "Failed to load budget limits.", "danger", "API Error");
      renderBudgetsPage();
    }
  } catch (err) {
    console.error("Failed to load budgets:", err);
    showToast("Error connecting to server to load budget targets.", "danger", "API Connection Error");
  }
}

function populateCategorySelects() {
  const select = document.getElementById('budgetCategory');
  if (!select) return;

  select.innerHTML = '';
  categoriesList.forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat.id;
    opt.textContent = cat.name;
    select.appendChild(opt);
  });
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

  budgetsList.forEach(item => {
    totalLimit += item.limit;
    totalSpent += item.spent;
    if (item.spent > item.limit) {
      overrunCount++;
    }
  });

  if (countEl) countEl.textContent = `${budgetsList.length} Budget${budgetsList.length !== 1 ? 's' : ''}`;
  if (limitEl) limitEl.textContent = '₹' + totalLimit.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (spentEl) spentEl.textContent = '₹' + totalSpent.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (overrunEl) {
    overrunEl.textContent = overrunCount > 0 ? `${overrunCount} Category${overrunCount > 1 ? 's' : ''}` : '0 Categories';
    overrunEl.className = overrunCount > 0 ? 'stats-value text-danger' : 'stats-value';
  }
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

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
    const ratio = Math.min((item.spent / item.limit) * 100, 100);
    const ratioRounded = Math.round((item.spent / item.limit) * 100);

    // Choose status colors
    let barColor = 'linear-gradient(135deg, var(--color-primary-start), var(--color-primary-end))';
    let textClass = 'text-primary';
    if (item.spent > item.limit) {
      barColor = 'var(--color-danger)';
      textClass = 'text-danger';
    } else if (item.spent > item.limit * 0.9) {
      barColor = 'var(--color-danger-light)';
      textClass = 'text-danger';
    } else if (item.spent > item.limit * 0.75) {
      barColor = 'var(--color-warning)';
      textClass = 'text-warning';
    } else if (item.spent > item.limit * 0.5) {
      barColor = 'var(--color-warning-light)';
      textClass = 'text-warning';
    }

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary); text-transform: capitalize;">${escapeHTML(item.category)}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">₹${item.limit.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;" class="${textClass}">₹${item.spent.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
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
      const id = btn.getAttribute('data-id');
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-budget-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
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
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const id = document.getElementById('budgetId').value;
      const categoryId = document.getElementById('budgetCategory').value;
      const limit = parseFloat(document.getElementById('budgetLimit').value);

      if (!categoryId) {
        showToast("Please configure target categories first.", "danger", "Configuration Error");
        return;
      }

      if (isNaN(limit) || limit <= 0) {
        showToast("Please enter a valid limit amount.", "danger", "Validation Error");
        return;
      }

      // Configure current month/year
      const currentMonth = new Date().getMonth() + 1;
      const currentYear = new Date().getFullYear();

      const payload = {
        category_id: categoryId,
        amount: limit,
        budget_month: currentMonth,
        budget_year: currentYear
      };

      try {
        let response;
        if (id) {
          response = await fetchApi(`/api/budgets/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
          });
        } else {
          response = await fetchApi('/api/budgets', {
            method: 'POST',
            body: JSON.stringify(payload)
          });
        }

        if (response && response.ok) {
          const result = await response.json();
          if (result.success) {
            showToast(id ? "Budget limit updated successfully." : "Budget limit configured successfully.", "success", "Success");
            loadBudgetData();
            closeModal();
          } else {
            showToast(result.message || "Operation failed.", "danger", "API Error");
          }
        }
      } catch (err) {
        console.error("Budget submit failed:", err);
        showToast("Server connection error.", "danger", "Connection Error");
      }
    });
  }
}

function openEditModal(id) {
  const item = budgetsList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Modify Category Limit';
  document.getElementById('budgetId').value = item.id;
  document.getElementById('budgetCategory').value = item.category_id;
  document.getElementById('budgetLimit').value = item.limit;

  const modal = document.getElementById('budgetModal');
  if (modal) {
    modal.classList.add('show');
  }
}

async function deleteBudget(id) {
  if (confirm("Are you sure you want to remove this budget limit configuration?")) {
    try {
      const response = await fetchApi(`/api/budgets/${id}`, { method: 'DELETE' });
      if (response && response.ok) {
        showToast("Budget limit configuration removed.", "warning", "Limit Removed");
        loadBudgetData();
      } else {
        showToast("Failed to delete budget limit.", "danger", "API Error");
      }
    } catch (err) {
      console.error("Budget deletion failed:", err);
      showToast("Server connection error.", "danger", "Connection Error");
    }
  }
}
