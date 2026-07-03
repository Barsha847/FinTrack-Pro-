/**
 * FinTrack Pro - Expenses Tracking Controller (Phase 2)
 */

import { showToast } from './utils.js';

// LocalStorage Namespaces
const TX_STORAGE_KEY = 'fintrack_transactions_v1';
const BUDGET_STORAGE_KEY = 'fintrack_budgets_v1';

let expensesList = [];
let budgetsList = [];
let currentPage = 1;
const itemsPerPage = 8;
let searchQuery = '';
let selectedCategory = 'all';

export function initExpensesPage() {
  const isExpensesPage = document.querySelector('.expenses-page-layout');
  if (!isExpensesPage) return;

  console.warn("Initializing Expenses Tracking Module...");

  loadExpensesData();
  setupEventListeners();
  renderExpensesPage();
}

function loadExpensesData() {
  const data = localStorage.getItem(TX_STORAGE_KEY);
  if (data) {
    try {
      const allTx = JSON.parse(data);
      expensesList = allTx.filter(tx => tx.type === 'expense');
    } catch (e) {
      console.error(e);
      expensesList = [];
    }
  } else {
    expensesList = [
      { id: 2, title: 'Whole Foods Outflow', amount: 4520.00, type: 'expense', date: '2026-06-27', category: 'food' },
      { id: 3, title: 'AWS Cloud Hosting Invoice', amount: 1850.00, type: 'expense', date: '2026-06-26', category: 'bills' }
    ];
  }

  // Load budgets for alerts comparison
  const budgetData = localStorage.getItem(BUDGET_STORAGE_KEY);
  if (budgetData) {
    try { budgetsList = JSON.parse(budgetData); } catch (e) { budgetsList = []; }
  } else {
    budgetsList = [
      { id: 1, category: 'food', limit: 12000.00 },
      { id: 2, category: 'bills', limit: 8000.00 }
    ];
  }
}

function saveExpensesData() {
  let allTx = [];
  const rawAll = localStorage.getItem(TX_STORAGE_KEY);
  if (rawAll) {
    try {
      const existingTx = JSON.parse(rawAll);
      const incomes = existingTx.filter(tx => tx.type !== 'expense');
      allTx = [...expensesList, ...incomes];
    } catch (e) {
      allTx = [...expensesList];
    }
  } else {
    allTx = [...expensesList];
  }
  localStorage.setItem(TX_STORAGE_KEY, JSON.stringify(allTx));
}

function renderExpensesPage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const totalEl = document.getElementById('totalExpensesVal');
  const highCatEl = document.getElementById('highCatVal');
  const avgEl = document.getElementById('avgExpensesVal');
  const alertsEl = document.getElementById('budgetAlertsVal');

  let total = 0;
  const categorySums = {};

  expensesList.forEach(item => {
    total += item.amount;
    categorySums[item.category] = (categorySums[item.category] || 0) + item.amount;
  });

  // Calculate Peak Spending Category
  let peakCategory = 'None';
  let maxAmount = 0;
  for (const cat in categorySums) {
    if (categorySums[cat] > maxAmount) {
      maxAmount = categorySums[cat];
      peakCategory = cat.charAt(0).toUpperCase() + cat.slice(1);
    }
  }

  const avg = expensesList.length > 0 ? (total / expensesList.length) : 0;

  // Calculate Budget Warnings (utilization > 70%)
  let activeAlerts = 0;
  budgetsList.forEach(budget => {
    const spent = categorySums[budget.category] || 0;
    if (spent > budget.limit * 0.7) {
      activeAlerts++;
    }
  });

  if (totalEl) totalEl.textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (highCatEl) highCatEl.textContent = maxAmount > 0 ? `${peakCategory} (₹${maxAmount.toLocaleString('en-IN')})` : 'None';
  if (avgEl) avgEl.textContent = '₹' + avg.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (alertsEl) {
    alertsEl.textContent = activeAlerts > 0 ? `${activeAlerts} Warning${activeAlerts > 1 ? 's' : ''}` : '0 Warnings';
    if (activeAlerts > 0) {
      alertsEl.className = 'stats-value text-danger';
    } else {
      alertsEl.className = 'stats-value';
    }
  }
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  let filtered = expensesList.filter(item => {
    const matchesSearch = item.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
                          item.category.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesCat = selectedCategory === 'all' || item.category === selectedCategory;
    return matchesSearch && matchesCat;
  });

  filtered.sort((a, b) => new Date(b.date) - new Date(a.date));

  const totalItems = filtered.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;

  const startIdx = (currentPage - 1) * itemsPerPage;
  const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
  const paginated = filtered.slice(startIdx, endIdx);

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
            <div class="empty-state-title">No matching expenses found</div>
            <div class="empty-state-desc">Try modifying your filter categories or log a new expenditure invoice.</div>
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
    if (item.category === 'food') badgeClass = 'badge-success';
    if (item.category === 'bills') badgeClass = 'badge-danger';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 500; color: var(--text-secondary);">${item.date}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.title)}</td>
      <td style="padding: 1rem 1.25rem;">
        <span class="badge ${badgeClass}">
          ${item.category}
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
      const id = parseInt(btn.getAttribute('data-id'));
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-tx-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      deleteExpense(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const searchInput = document.getElementById('dashboardSearchInput');
  const catFilter = document.getElementById('categoryFilter');
  const openModalBtn = document.getElementById('openAddExpenseModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('expenseModal');
  const form = document.getElementById('expenseForm');
  const refreshBtn = document.getElementById('pageRefreshBtn');

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      searchQuery = searchInput.value;
      currentPage = 1;
      renderTable();
    });
  }

  if (catFilter) {
    catFilter.addEventListener('change', () => {
      selectedCategory = catFilter.value;
      currentPage = 1;
      renderTable();
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      const tbody = document.getElementById('tableBodyContainer');
      if (tbody) {
        tbody.innerHTML = `
          <tr class="skeleton" style="height: 50px;"><td colspan="5"></td></tr>
          <tr class="skeleton" style="height: 50px;"><td colspan="5"></td></tr>
        `;
      }
      setTimeout(() => {
        loadExpensesData();
        renderExpensesPage();
        showToast("Expenses ledger records synced.", "success", "Database Refreshed");
      }, 1000);
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
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const id = document.getElementById('txId').value;
      const title = document.getElementById('txTitle').value.trim();
      const amount = parseFloat(document.getElementById('txAmount').value);
      const category = document.getElementById('txCategory').value;
      const date = document.getElementById('txDate').value;

      if (!title || isNaN(amount) || amount <= 0) {
        showToast("Please input valid metrics.", "danger", "Validation Failed");
        return;
      }

      if (id) {
        const index = expensesList.findIndex(x => x.id === parseInt(id));
        if (index !== -1) {
          expensesList[index] = { ...expensesList[index], title, amount, category, date };
          showToast(`Expense "${title}" updated successfully.`, "success", "Transaction Updated");
        }
      } else {
        const newTx = {
          id: Date.now(),
          title,
          amount,
          type: 'expense',
          category,
          date
        };
        expensesList.unshift(newTx);
        showToast(`Expense "${title}" logged successfully.`, "success", "Transaction Logged");
      }

      saveExpensesData();
      renderExpensesPage();
      closeModal();
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
  document.getElementById('txCategory').value = item.category;
  document.getElementById('txDate').value = item.date;

  const modal = document.getElementById('expenseModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('txTitle').focus(), 100);
  }
}

function deleteExpense(id) {
  if (confirm("Are you sure you want to delete this expense entry?")) {
    const item = expensesList.find(x => x.id === id);
    const title = item ? item.title : '';
    expensesList = expensesList.filter(x => x.id !== id);
    saveExpensesData();
    renderExpensesPage();
    showToast(`Expense "${title}" deleted successfully.`, "warning", "Transaction Removed");
  }
}

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
