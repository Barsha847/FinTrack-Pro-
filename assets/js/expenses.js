/**
 * FinTrack Pro - Expenses Controller (Phase 6)
 */

import { showToast, fetchApi, escapeHTML } from './utils.js';

let expensesList = [];
let categoriesList = [];
let currentPage = 1;
const itemsPerPage = 8;
let searchQuery = '';
let categoryFilterVal = 'all';

export function initExpensesPage() {
  const isExpensesPage = document.querySelector('.expenses-page-layout');
  if (!isExpensesPage) return;

  console.warn("Initializing Expenses Module...");

  loadExpensesData();
  setupEventListeners();
}

async function loadExpensesData() {
  try {
    // 1. Fetch categories for selects
    const catResponse = await fetchApi('/api/categories?type=expense');
    if (catResponse && catResponse.ok) {
      const catRes = await catResponse.json();
      categoriesList = catRes.data.categories || [];
      populateCategoryDropdowns();
    }

    // 2. Fetch expenses
    let url = `/api/expenses?category=${encodeURIComponent(categoryFilterVal)}`;
    if (searchQuery.trim() !== '') {
      url += `&search=${encodeURIComponent(searchQuery.trim())}`;
    }

    const response = await fetchApi(url);
    if (!response) return;

    const result = await response.json();
    if (result && result.success) {
      expensesList = (result.data.expenses || []).map(exp => ({
        id: exp.id,
        title: exp.description || exp.merchant || 'Expense Transaction',
        amount: parseFloat(exp.amount),
        category: exp.category_name || 'others',
        category_id: exp.category_id,
        date: exp.expense_date
      }));
      renderExpensesPage();
    } else {
      expensesList = [];
      showToast(result?.message || "Failed to load expenses.", "danger", "API Error");
      renderExpensesPage();
    }
  } catch (err) {
    console.error("Failed to load expenses list:", err);
    showToast("Error connecting to server to load expenses.", "danger", "API Connection Error");
  }
}

function populateCategoryDropdowns() {
  const filterSelect = document.getElementById('categoryFilter');
  const inputSelect = document.getElementById('txCategory');

  // Fill modal input select
  if (inputSelect) {
    inputSelect.innerHTML = '';
    categoriesList.forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat.id;
      opt.textContent = cat.name;
      inputSelect.appendChild(opt);
    });
  }

  // Fill filter dropdown (preserve 'all' as the default first option)
  if (filterSelect && filterSelect.options.length <= 1) {
    filterSelect.innerHTML = '<option value="all">All Categories</option>';
    categoriesList.forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat.id;
      opt.textContent = cat.name;
      filterSelect.appendChild(opt);
    });
    filterSelect.value = categoryFilterVal;
  }
}

function renderExpensesPage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const countEl = document.getElementById('activeExpensesVal');
  const totalEl = document.getElementById('totalExpensesVal');
  const monthEl = document.getElementById('monthlySpentVal');
  const avgEl = document.getElementById('averageDailyVal');

  let totalSum = 0;
  let monthSum = 0;
  
  const now = new Date();
  const currentMonthStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

  expensesList.forEach(item => {
    totalSum += item.amount;
    if (item.date && item.date.startsWith(currentMonthStr)) {
      monthSum += item.amount;
    }
  });

  const activeCount = expensesList.length;
  const avgDaily = activeCount > 0 ? (totalSum / 30) : 0; // Simple rolling estimate

  if (countEl) countEl.textContent = `${activeCount} Transaction${activeCount !== 1 ? 's' : ''}`;
  if (totalEl) totalEl.textContent = '₹' + totalSum.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (monthEl) monthEl.textContent = '₹' + monthSum.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (avgEl) avgEl.textContent = '₹' + avgDaily.toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  const totalItems = expensesList.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;

  const startIdx = (currentPage - 1) * itemsPerPage;
  const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
  const paginated = expensesList.slice(startIdx, endIdx);

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
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h.007m-.007 3h.007m-.007 3h.007m-1.5-6h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6h1.5m-1.5 3h1.5m-1.5 3h1.5M9 4.5h1.5m-1.5 3h1.5m-1.5 3h1.5M12 4.5h1.5m-1.5 3h1.5m-1.5 3h1.5M15 4.5h1.5m-1.5 3h1.5m-1.5 3h1.5M18 4.5h1.5m-1.5 3h1.5m-1.5 3h1.5" />
            </svg>
            <div class="empty-state-title">No transactions logged</div>
            <div class="empty-state-desc">Capture your daily expenses here to feed budget limits and statistics.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  paginated.forEach(item => {
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';
    tr.style.transition = 'background-color var(--motion-hover)';
    
    let badgeClass = 'badge-primary';
    const lowerCat = item.category.toLowerCase();
    if (lowerCat === 'food' || lowerCat === 'groceries') badgeClass = 'badge-success';
    if (lowerCat === 'bills' || lowerCat === 'utilities') badgeClass = 'badge-danger';
    if (lowerCat === 'rent' || lowerCat === 'housing') badgeClass = 'badge-warning';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 500; color: var(--text-secondary);">${item.date}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.title)}</td>
      <td style="padding: 1rem 1.25rem;">
        <span class="badge ${badgeClass}" style="text-transform: capitalize;">
          ${escapeHTML(item.category)}
        </span>
      </td>
      <td style="padding: 1rem 1.25rem; font-weight: 700; text-align: right; color: var(--color-danger);">
        -₹${item.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
      </td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
          <button class="btn btn-secondary btn-sm edit-tx-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto;">
            <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i>
          </button>
          <button class="btn btn-secondary btn-sm delete-tx-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; color: var(--color-danger);">
            <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
          </button>
        </div>
      </td>
    `;

    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('.edit-tx-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-tx-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      deleteExpense(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const openModalBtn = document.getElementById('openAddExpenseModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('expenseModal');
  const form = document.getElementById('expenseForm');
  const refreshBtn = document.getElementById('pageRefreshBtn');

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');
  const searchInput = document.getElementById('searchInput');
  const filterSelect = document.getElementById('categoryFilter');

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadExpensesData();
      showToast("Expenses listing refreshed and synced.", "success", "Refreshed Ledger");
    });
  }

  if (filterSelect) {
    filterSelect.addEventListener('change', () => {
      categoryFilterVal = filterSelect.value;
      currentPage = 1;
      loadExpensesData();
    });
  }

  if (searchInput) {
    let timeout = null;
    searchInput.addEventListener('input', () => {
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        searchQuery = searchInput.value;
        currentPage = 1;
        loadExpensesData();
      }, 350);
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
      document.getElementById('modalTitle').textContent = 'Add Expense Transaction';
      form.reset();
      document.getElementById('txId').value = '';
      
      // Default to today's date
      document.getElementById('txDate').value = new Date().toISOString().split('T')[0];
      
      modal.classList.add('show');
      setTimeout(() => document.getElementById('txTitle').focus(), 100);
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
      
      const id = document.getElementById('txId').value;
      const title = document.getElementById('txTitle').value.trim();
      const amount = parseFloat(document.getElementById('txAmount').value);
      const categoryId = document.getElementById('txCategory').value;
      const date = document.getElementById('txDate').value;

      if (!title || isNaN(amount) || amount <= 0) {
        showToast("Please enter a valid amount and description title.", "danger", "Validation Failed");
        return;
      }

      if (!categoryId) {
        showToast("Configure active categories first.", "danger", "Configuration Error");
        return;
      }

      const payload = {
        description: title,
        amount: amount,
        category_id: categoryId,
        expense_date: date,
        merchant: title // Replicate title to merchant column for completeness
      };

      try {
        let response;
        if (id) {
          response = await fetchApi(`/api/expenses/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
          });
        } else {
          response = await fetchApi('/api/expenses', {
            method: 'POST',
            body: JSON.stringify(payload)
          });
        }

        if (response && response.ok) {
          const result = await response.json();
          if (result.success) {
            showToast(id ? `Expense "${title}" updated successfully.` : `Expense "${title}" logged successfully.`, "success", "Success");
            loadExpensesData();
            closeModal();
          } else {
            showToast(result.message || "Failed to log expense.", "danger", "API Error");
          }
        }
      } catch (err) {
        console.error("Expense submit error:", err);
        showToast("Server connection error.", "danger", "Connection Error");
      }
    });
  }
}

function openEditModal(id) {
  const item = expensesList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Edit Expense Transaction';
  document.getElementById('txId').value = item.id;
  document.getElementById('txTitle').value = item.title;
  document.getElementById('txAmount').value = item.amount;
  document.getElementById('txCategory').value = item.category_id;
  document.getElementById('txDate').value = item.date;

  const modal = document.getElementById('expenseModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('txTitle').focus(), 100);
  }
}

async function deleteExpense(id) {
  if (confirm("Are you sure you want to delete this expense entry?")) {
    try {
      const response = await fetchApi(`/api/expenses/${id}`, { method: 'DELETE' });
      if (response && response.ok) {
        showToast("Expense entry deleted.", "warning", "Transaction Removed");
        loadExpensesData();
      } else {
        showToast("Failed to delete expense entry.", "danger", "API Error");
      }
    } catch (err) {
      console.error("Expense deletion failed:", err);
      showToast("Server connection error.", "danger", "Connection Error");
    }
  }
}
