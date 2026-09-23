/**
 * FinTrack Pro - Income Tracking Controller (Phase 2)
 */

import { showToast } from './utils.js';

// LocalStorage Namespace
const TX_STORAGE_KEY = 'fintrack_transactions_v1';

let incomesList = [];
let currentPage = 1;
const itemsPerPage = 8;
let searchQuery = '';
let selectedCategory = 'all';

export function initIncomePage() {
  const isIncomePage = document.querySelector('.income-page-layout');
  if (!isIncomePage) return;

  console.warn("Initializing Income Tracking Module...");

  loadIncomeData();
  setupEventListeners();
  renderIncomePage();
}

function loadIncomeData() {
  const data = localStorage.getItem(TX_STORAGE_KEY);
  if (data) {
    try {
      const allTx = JSON.parse(data);
      // Filter for type === 'income'
      incomesList = allTx.filter(tx => tx.type === 'income');
    } catch (e) {
      console.error(e);
      incomesList = [];
    }
  } else {
    incomesList = [
      { id: 1, title: 'Regular Salary Credited', amount: 85000.00, type: 'income', date: '2026-06-28', category: 'salary' }
    ];
  }
}

function saveIncomeData() {
  // Sync changes back to overall transactions cache
  let allTx = [];
  const rawAll = localStorage.getItem(TX_STORAGE_KEY);
  if (rawAll) {
    try {
      // Keep expenses, replace incomes
      const existingTx = JSON.parse(rawAll);
      const expenses = existingTx.filter(tx => tx.type !== 'income');
      allTx = [...incomesList, ...expenses];
    } catch (e) {
      allTx = [...incomesList];
    }
  } else {
    allTx = [...incomesList];
  }
  localStorage.setItem(TX_STORAGE_KEY, JSON.stringify(allTx));
}

function renderIncomePage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const totalEl = document.getElementById('totalIncomeVal');
  const freelanceEl = document.getElementById('freelanceVal');
  const avgEl = document.getElementById('avgIncomeVal');

  let total = 0;
  let freelance = 0;

  incomesList.forEach(item => {
    total += item.amount;
    if (item.category === 'others') {
      freelance += item.amount; // Demo map others to freelance revenue
    }
  });

  const avg = incomesList.length > 0 ? (total / incomesList.length) : 0;

  if (totalEl) totalEl.textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (freelanceEl) freelanceEl.textContent = '₹' + freelance.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (avgEl) avgEl.textContent = '₹' + avg.toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  // Apply filters
  let filtered = incomesList.filter(item => {
    const matchesSearch = item.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      item.category.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesCat = selectedCategory === 'all' || item.category === selectedCategory;
    return matchesSearch && matchesCat;
  });

  // Sort descending by date
  filtered.sort((a, b) => new Date(b.date) - new Date(a.date));

  // Pagination bounds
  const totalItems = filtered.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;

  const startIdx = (currentPage - 1) * itemsPerPage;
  const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
  const paginated = filtered.slice(startIdx, endIdx);

  // Setup Pagination Buttons State
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

  // Handle Empty State
  if (totalItems === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="padding: 3rem; text-align: center;">
          <div class="empty-state-container">
            <svg class="empty-state-svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="empty-state-title">No matching inflows found</div>
            <div class="empty-state-desc">Try modifying your filter categories or log a new income deposit.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  // Populate paginated rows
  paginated.forEach(item => {
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';
    tr.style.transition = 'background-color var(--motion-hover)';

    // Action event listeners
    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 500; color: var(--text-secondary);">${item.date}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.title)}</td>
      <td style="padding: 1rem 1.25rem;">
        <span class="badge ${item.category === 'salary' ? 'badge-success' : 'badge-primary'}">
          ${item.category}
        </span>
      </td>
      <td style="padding: 1rem 1.25rem; font-weight: 700; text-align: right; color: var(--color-success);">
        +₹${item.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
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

  // Bind Actions Buttons
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
      deleteIncome(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const searchInput = document.getElementById('dashboardSearchInput');
  const catFilter = document.getElementById('categoryFilter');
  const openModalBtn = document.getElementById('openAddIncomeModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('incomeModal');
  const form = document.getElementById('incomeForm');
  const refreshBtn = document.getElementById('pageRefreshBtn');

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');

  // Search Filter
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      searchQuery = searchInput.value;
      currentPage = 1;
      renderTable();
    });
  }

  // Category Filter
  if (catFilter) {
    catFilter.addEventListener('change', () => {
      selectedCategory = catFilter.value;
      currentPage = 1;
      renderTable();
    });
  }

  // Page Refresh simulation (skeletons)
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      const tbody = document.getElementById('tableBodyContainer');
      if (tbody) {
        tbody.innerHTML = `
          <tr class="skeleton" style="height: 50px;"><td colspan="5"></td></tr>
          <tr class="skeleton" style="height: 50px;"><td colspan="5"></td></tr>
          <tr class="skeleton" style="height: 50px;"><td colspan="5"></td></tr>
        `;
      }
      setTimeout(() => {
        loadIncomeData();
        renderIncomePage();
        showToast("Income ledger records synced.", "success", "Database Refreshed");
      }, 1000);
    });
  }

  // Pagination navigation
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

  // Modal display toggles
  if (openModalBtn && modal) {
    openModalBtn.addEventListener('click', () => {
      document.getElementById('modalTitle').textContent = 'Add Income Transaction';
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

  // Form Submit
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
        // Edit mode
        const index = incomesList.findIndex(x => x.id === parseInt(id));
        if (index !== -1) {
          incomesList[index] = { ...incomesList[index], title, amount, category, date };
          showToast(`Income "${title}" updated successfully.`, "success", "Transaction Updated");
        }
      } else {
        // Add mode
        const newTx = {
          id: Date.now(),
          title,
          amount,
          type: 'income',
          category,
          date
        };
        incomesList.unshift(newTx);
        showToast(`Income "${title}" logged successfully.`, "success", "Transaction Logged");
      }

      saveIncomeData();
      renderIncomePage();
      closeModal();
    });
  }
}

function openEditModal(id) {
  const item = incomesList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Edit Income Transaction';
  document.getElementById('txId').value = item.id;
  document.getElementById('txTitle').value = item.title;
  document.getElementById('txAmount').value = item.amount;
  document.getElementById('txCategory').value = item.category;
  document.getElementById('txDate').value = item.date;

  const modal = document.getElementById('incomeModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('txTitle').focus(), 100);
  }
}

function deleteIncome(id) {
  if (confirm("Are you sure you want to delete this income entry?")) {
    const item = incomesList.find(x => x.id === id);
    const title = item ? item.title : '';
    incomesList = incomesList.filter(x => x.id !== id);
    saveIncomeData();
    renderIncomePage();
    showToast(`Income "${title}" deleted successfully.`, "warning", "Transaction Removed");
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
