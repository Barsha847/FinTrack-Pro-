/**
 * FinTrack Pro - Bills Controller (Phase 6)
 */

import { showToast, fetchApi, escapeHTML } from './utils.js';

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
}

async function loadBillsData() {
  try {
    let url = '/api/bills';
    const response = await fetchApi(url);
    if (!response) return;

    const result = await response.json();
    if (result && result.success) {
      billsList = (result.data.bills || []).map(bill => ({
        id: bill.id,
        name: bill.bill_name,
        amount: parseFloat(bill.amount || 0),
        category: bill.category || 'others',
        date: bill.due_date,
        status: bill.status,
        remind_before_days: parseInt(bill.remind_before_days || 3),
        is_recurring: !!bill.is_recurring,
        recurring_frequency: bill.recurring_frequency || 'monthly'
      }));
      renderBillsPage();
    } else {
      billsList = [];
      showToast(result?.message || "Failed to load bill reminders.", "danger", "API Error");
      renderBillsPage();
    }
  } catch (err) {
    console.error("Failed to load bills data:", err);
    showToast("Error connecting to database to load bills.", "danger", "Connection Error");
  }
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

    // Status classes
    let statusClass = 'badge-primary';
    if (item.status === 'paid') statusClass = 'badge-success';
    if (item.status === 'overdue') statusClass = 'badge-danger';

    const isPaid = item.status === 'paid';
    const payBtnHtml = isPaid 
      ? `<button class="btn btn-secondary btn-sm" disabled style="padding: 0.25rem 0.5rem; height: auto; opacity: 0.5;"><i data-lucide="check" style="width: 12px; height: 12px; color: var(--color-success);"></i></button>`
      : `<button class="btn btn-primary btn-sm pay-bill-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; font-size: 10px;">Pay Now</button>`;

    // Recurrence pill info
    const recurrenceHtml = item.is_recurring 
      ? `<span class="badge badge-warning" style="text-transform: capitalize; font-size: 9px;">${item.recurring_frequency}</span>`
      : `<span class="badge badge-secondary" style="font-size: 9px; opacity: 0.6;">One-Time</span>`;

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.name)}</td>
      <td style="padding: 1rem 1.25rem; text-transform: capitalize;"><span class="badge badge-primary">${escapeHTML(item.category)}</span></td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right; color: var(--color-danger);">₹${item.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 500; color: var(--text-secondary);">${item.date}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">${recurrenceHtml}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;"><span class="badge ${statusClass}" style="text-transform: capitalize;">${item.status}</span></td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
          ${payBtnHtml}
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

  // Action listeners
  tbody.querySelectorAll('.pay-bill-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      payBill(id);
    });
  });

  tbody.querySelectorAll('.edit-bill-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-bill-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      deleteBill(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const openModalBtn = document.getElementById('openAddBillModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('billModal');
  const form = document.getElementById('billForm');
  const searchInput = document.getElementById('dashboardSearchInput');
  const refreshBtn = document.getElementById('pageRefreshBtn');

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');

  const recurringCheckbox = document.getElementById('billRecurring');
  const frequencyGroup = document.getElementById('billFrequencyGroup');

  if (recurringCheckbox && frequencyGroup) {
    recurringCheckbox.addEventListener('change', () => {
      frequencyGroup.style.display = recurringCheckbox.checked ? 'block' : 'none';
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadBillsData();
      showToast("Bill deadlines and alert logs synchronized.", "success", "Synced Deadlines");
    });
  }

  if (searchInput) {
    let timeout = null;
    searchInput.addEventListener('input', () => {
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        searchQuery = searchInput.value;
        currentPage = 1;
        renderTable();
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
      document.getElementById('modalTitle').textContent = 'Log Bill Reminder';
      form.reset();
      document.getElementById('billId').value = '';
      if (frequencyGroup) frequencyGroup.style.display = 'none';
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
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const id = document.getElementById('billId').value;
      const name = document.getElementById('billName').value.trim();
      const amount = parseFloat(document.getElementById('billAmount').value);
      const category = document.getElementById('billCategory').value;
      const date = document.getElementById('billDate').value;
      const status = document.getElementById('billStatus').value;
      
      const isRecurring = recurringCheckbox ? recurringCheckbox.checked : false;
      const recurringFrequency = isRecurring ? document.getElementById('billFrequency').value : null;
      const leadDays = parseInt(document.getElementById('billLeadDays').value || 3);

      if (!name || isNaN(amount) || amount <= 0 || !date) {
        showToast("Please enter valid billing details.", "danger", "Validation Error");
        return;
      }

      const payload = {
        bill_name: name,
        amount: amount,
        category: category,
        due_date: date,
        status: status,
        is_recurring: isRecurring,
        recurring_frequency: recurringFrequency,
        remind_before_days: leadDays
      };

      try {
        let response;
        if (id) {
          response = await fetchApi(`/api/bills/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
          });
        } else {
          response = await fetchApi('/api/bills', {
            method: 'POST',
            body: JSON.stringify(payload)
          });
        }

        if (response && response.ok) {
          const result = await response.json();
          if (result.success) {
            showToast(id ? "Bill details updated successfully." : "Bill reminder configured successfully.", "success", "Success");
            loadBillsData();
            closeModal();
          } else {
            showToast(result.message || "Failed to configure reminder.", "danger", "API Error");
          }
        }
      } catch (err) {
        console.error("Bill submit error:", err);
        showToast("Server connection error.", "danger", "Connection Error");
      }
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
  
  const recurringCheckbox = document.getElementById('billRecurring');
  const frequencySelect = document.getElementById('billFrequency');
  const frequencyGroup = document.getElementById('billFrequencyGroup');
  const leadDaysInput = document.getElementById('billLeadDays');

  if (recurringCheckbox) {
    recurringCheckbox.checked = item.is_recurring;
  }
  if (frequencySelect) {
    frequencySelect.value = item.recurring_frequency;
  }
  if (frequencyGroup) {
    frequencyGroup.style.display = item.is_recurring ? 'block' : 'none';
  }
  if (leadDaysInput) {
    leadDaysInput.value = item.remind_before_days;
  }

  const modal = document.getElementById('billModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('billName').focus(), 100);
  }
}

async function payBill(id) {
  try {
    const response = await fetchApi(`/api/bills/${id}/paid`, { method: 'PUT' });
    if (response && response.ok) {
      const result = await response.json();
      if (result.success) {
        showToast("Bill recorded as paid.", "success", "Payment Captured");
        loadBillsData();
      } else {
        showToast(result.message || "Mark paid failed.", "danger", "API Error");
      }
    }
  } catch (err) {
    console.error("Failed to pay bill:", err);
    showToast("Server connection error during payment.", "danger", "Connection Error");
  }
}

async function deleteBill(id) {
  if (confirm("Are you sure you want to remove this bill reminder?")) {
    try {
      const response = await fetchApi(`/api/bills/${id}`, { method: 'DELETE' });
      if (response && response.ok) {
        showToast("Bill reminder removed.", "warning", "Invoice Removed");
        loadBillsData();
      } else {
        showToast("Failed to delete bill reminder.", "danger", "API Error");
      }
    } catch (err) {
      console.error("Bill deletion failed:", err);
      showToast("Server connection error.", "danger", "Connection Error");
    }
  }
}
