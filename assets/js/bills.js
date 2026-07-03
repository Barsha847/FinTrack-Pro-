/**
 * FinTrack Pro - Bills Controller (Phase 2)
 */

import { showToast } from './utils.js';

// LocalStorage Namespaces
const BILLS_STORAGE_KEY = 'fintrack_bills_v1';
const TX_STORAGE_KEY = 'fintrack_transactions_v1';

let billsList = [];
let currentPage = 1;
const itemsPerPage = 8;
let searchQuery = '';

export function initBillsPage() {
  const isBillsPage = document.querySelector('.bills-page-layout');
  if (!isBillsPage) return;

  console.warn("Initializing Bills & Invoices Module...");

  loadBillsData();
  setupEventListeners();
  renderBillsPage();
}

function loadBillsData() {
  const data = localStorage.getItem(BILLS_STORAGE_KEY);
  if (data) {
    try { billsList = JSON.parse(data); } catch (e) { billsList = []; }
  } else {
    billsList = [
      { id: 1, name: 'AWS Cloud Hosting Charge', amount: 1850.00, category: 'bills', status: 'pending', date: '2026-07-05' },
      { id: 2, name: 'Apartment Monthly Rent', amount: 15000.00, category: 'rent', status: 'overdue', date: '2026-07-01' }
    ];
    localStorage.setItem(BILLS_STORAGE_KEY, JSON.stringify(billsList));
  }
}

function saveBillsData() {
  localStorage.setItem(BILLS_STORAGE_KEY, JSON.stringify(billsList));
}

function renderBillsPage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const pendingEl = document.getElementById('pendingBillsVal');
  const paidEl = document.getElementById('paidBillsVal');
  const dueEl = document.getElementById('totalDueVal');
  const overdueEl = document.getElementById('overdueCountVal');

  let pendingCount = 0;
  let totalPaid = 0;
  let totalDue = 0;
  let overdueCount = 0;

  billsList.forEach(item => {
    if (item.status === 'pending') {
      pendingCount++;
      totalDue += item.amount;
    } else if (item.status === 'overdue') {
      overdueCount++;
      pendingCount++;
      totalDue += item.amount;
    } else if (item.status === 'paid') {
      totalPaid += item.amount;
    }
  });

  if (pendingEl) pendingEl.textContent = `${pendingCount} Bill${pendingCount !== 1 ? 's' : ''}`;
  if (paidEl) paidEl.textContent = '₹' + totalPaid.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (dueEl) dueEl.textContent = '₹' + totalDue.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (overdueEl) {
    overdueEl.textContent = overdueCount > 0 ? `${overdueCount} Overdue` : '0 Overdue';
    overdueEl.className = overdueCount > 0 ? 'stats-value text-danger' : 'stats-value';
  }
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  let filtered = billsList.filter(item => {
    return item.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
           item.category.toLowerCase().includes(searchQuery.toLowerCase());
  });

  filtered.sort((a, b) => new Date(a.date) - new Date(b.date));

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
        <td colspan="7" style="padding: 3rem; text-align: center;">
          <div class="empty-state-container">
            <svg class="empty-state-svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="empty-state-title">No reminders scheduled</div>
            <div class="empty-state-desc">Configure billing timelines to log invoice deadlines.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  paginated.forEach(item => {
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    // Status colors
    let statusClass = 'badge-primary';
    if (item.status === 'paid') statusClass = 'badge-success';
    if (item.status === 'overdue') statusClass = 'badge-danger';

    // Mark paid button state
    const isPaid = item.status === 'paid';
    const payBtnHtml = isPaid 
      ? `<button class="btn btn-secondary btn-sm" disabled style="padding: 0.25rem 0.5rem; height: auto; opacity: 0.5;"><i data-lucide="check" style="width: 12px; height: 12px; color: var(--color-success);"></i></button>`
      : `<button class="btn btn-primary btn-sm pay-bill-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; font-size: 10px;">Pay Now</button>`;

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.name)}</td>
      <td style="padding: 1rem 1.25rem; text-transform: capitalize;"><span class="badge badge-primary">${item.category}</span></td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right; color: var(--color-danger);">₹${item.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; color: var(--text-secondary);">${item.date}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;"><span class="badge ${statusClass}">${item.status}</span></td>
      <td style="padding: 1rem 1.25rem; text-align: center;">${payBtnHtml}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
          <button class="btn btn-secondary btn-sm edit-bill-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto;">
            <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i>
          </button>
          <button class="btn btn-secondary btn-sm delete-bill-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; color: var(--color-danger);">
            <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
          </button>
        </div>
      </td>
    `;

    tbody.appendChild(tr);
  });

  // Bind Actions Buttons
  tbody.querySelectorAll('.pay-bill-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      payBill(id);
    });
  });

  tbody.querySelectorAll('.edit-bill-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-bill-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      deleteBill(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function payBill(id) {
  const item = billsList.find(x => x.id === id);
  if (!item || item.status === 'paid') return;

  // 1. Update bill status
  item.status = 'paid';
  saveBillsData();

  // 2. Log automated expense transaction in `fintrack_transactions_v1`
  let allTx = [];
  const rawTx = localStorage.getItem(TX_STORAGE_KEY);
  if (rawTx) {
    try { allTx = JSON.parse(rawTx); } catch (e) { allTx = []; }
  }
  
  const autoExpense = {
    id: Date.now(),
    title: `Bill Paid: ${item.name}`,
    amount: item.amount,
    type: 'expense',
    date: new Date().toISOString().split('T')[0],
    category: item.category
  };

  allTx.unshift(autoExpense);
  localStorage.setItem(TX_STORAGE_KEY, JSON.stringify(allTx));

  // 3. Render page and trigger toast
  renderBillsPage();
  showToast(`Bill "${item.name}" marked as Paid. Auto expense logged.`, "success", "Invoice Settled");
}

function setupEventListeners() {
  const searchInput = document.getElementById('dashboardSearchInput');
  const openModalBtn = document.getElementById('openAddBillModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('billModal');
  const form = document.getElementById('billForm');
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

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadBillsData();
      renderBillsPage();
      showToast("Subscription bill logs synced.", "success", "Synced Invoices");
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
      document.getElementById('modalTitle').textContent = 'Log Bill Reminder';
      form.reset();
      document.getElementById('billId').value = '';
      modal.classList.add('show');
      setTimeout(() => document.getElementById('billName').focus(), 100);
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
      
      const id = document.getElementById('billId').value;
      const name = document.getElementById('billName').value.trim();
      const amount = parseFloat(document.getElementById('billAmount').value);
      const category = document.getElementById('billCategory').value;
      const date = document.getElementById('billDate').value;
      const status = document.getElementById('billStatus').value;

      if (!name || isNaN(amount) || amount <= 0 || !date) {
        showToast("Please input valid metrics.", "danger", "Validation Error");
        return;
      }

      if (id) {
        const index = billsList.findIndex(x => x.id === parseInt(id));
        if (index !== -1) {
          billsList[index] = { ...billsList[index], name, amount, category, date, status };
          showToast(`Reminder details updated for "${name}".`, "success", "Invoice Updated");
        }
      } else {
        const newBill = {
          id: Date.now(),
          name,
          amount,
          category,
          date,
          status
        };
        billsList.push(newBill);
        showToast(`Bill reminder configured for "${name}".`, "success", "Invoice Logged");
      }

      saveBillsData();
      renderBillsPage();
      closeModal();
    });
  }
}

function openEditModal(id) {
  const item = billsList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Modify Bill Reminder';
  document.getElementById('billId').value = item.id;
  document.getElementById('billName').value = item.name;
  document.getElementById('billAmount').value = item.amount;
  document.getElementById('billCategory').value = item.category;
  document.getElementById('billDate').value = item.date;
  document.getElementById('billStatus').value = item.status;

  const modal = document.getElementById('billModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('billName').focus(), 100);
  }
}

function deleteBill(id) {
  if (confirm("Are you sure you want to remove this bill reminder?")) {
    const item = billsList.find(x => x.id === id);
    const name = item ? item.name : '';
    billsList = billsList.filter(x => x.id !== id);
    saveBillsData();
    renderBillsPage();
    showToast(`Bill reminder for "${name}" deleted.`, "warning", "Invoice Removed");
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
