/**
 * FinTrack Pro - Loans Controller (Phase 5)
 */

import { showToast, fetchApi } from './utils.js';

let loansList = [];
let currentPage = 1;
const itemsPerPage = 8;
let activeEmiLoanId = null;

export async function initLoansPage() {
  const isLoansPage = document.querySelector('.loans-page-layout');
  if (!isLoansPage) return;

  console.warn("Initializing Loans & EMI Module...");

  setupEventListeners();
  await loadLoansData();
  renderLoansPage();
}

async function loadLoansData() {
  try {
    const response = await fetchApi('/api/loans');
    if (!response) return;

    const result = await response.json();
    if (result && result.success && result.data) {
      loansList = result.data.loans || [];
    } else {
      loansList = [];
      showToast(result?.message || "Failed to load loans list.", "danger", "API Error");
    }
  } catch (err) {
    console.error("Failed to load loans:", err);
    showToast("Error connecting to server to load liabilities.", "danger", "API Connection Error");
  }
}

function renderLoansPage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const countEl = document.getElementById('activeLoansVal');
  const principalEl = document.getElementById('loansPrincipalVal');
  const emiEl = document.getElementById('nextEmiVal');
  const paidPctEl = document.getElementById('loansPaidVal');

  let totalOutstanding = 0;
  let activeLoansCount = 0;
  let totalEmiSum = 0;
  let totalPaidRatio = 0;

  loansList.forEach(item => {
    totalOutstanding += parseFloat(item.remaining_amount || 0);
    if (item.status !== 'closed' && item.status !== 'paid') {
      activeLoansCount++;
      totalEmiSum += parseFloat(item.emi_amount || 0);
    }
    totalPaidRatio += (parseFloat(item.paid_months || 0) / parseInt(item.tenure_months || 1));
  });

  const avgPaidRatio = loansList.length > 0 ? (totalPaidRatio / loansList.length) * 100 : 0;

  if (countEl) countEl.textContent = `${activeLoansCount} Accounts`;
  if (principalEl) principalEl.textContent = '₹' + totalOutstanding.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (emiEl) emiEl.textContent = '₹' + totalEmiSum.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (paidPctEl) paidPctEl.textContent = `${Math.round(avgPaidRatio)}%`;
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  const totalItems = loansList.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;

  const startIdx = (currentPage - 1) * itemsPerPage;
  const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
  const paginated = loansList.slice(startIdx, endIdx);

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
            <div class="empty-state-title">No loan records logged</div>
            <div class="empty-state-desc">Log your active EMI timelines to plan monthly amortization limits.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  paginated.forEach(item => {
    const tenure = parseInt(item.tenure_months || 1);
    const paidMonths = parseInt(item.paid_months || 0);
    const ratio = Math.min((paidMonths / tenure) * 100, 100);
    const ratioRounded = Math.round(ratio);
    const principal = parseFloat(item.principal_amount || 0);
    const emi = parseFloat(item.emi_amount || 0);
    const interest = parseFloat(item.interest_rate || 0);

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.loan_name)}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">₹${principal.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">${interest}%</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right; color: var(--color-danger);">₹${emi.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">${tenure} Mo</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex align-center gap-2">
          <div style="flex: 1; height: 8px; background-color: var(--bg-secondary); border-radius: var(--radius-full); overflow: hidden;">
            <div style="width: ${ratio}%; height: 100%; background: linear-gradient(135deg, var(--color-primary-start), var(--color-primary-end)); border-radius: var(--radius-full); transition: width 0.4s ease;"></div>
          </div>
          <span style="font-size: 10px; font-weight: 700;">${paidMonths}/${tenure} (${ratioRounded}%)</span>
        </div>
      </td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
          <button class="btn btn-secondary btn-sm emi-schedule-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; color: var(--color-primary);" title="EMI Schedule">
            <i data-lucide="calendar" style="width: 12px; height: 12px;"></i>
          </button>
          <button class="btn btn-secondary btn-sm edit-loan-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto;">
            <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i>
          </button>
          <button class="btn btn-secondary btn-sm delete-loan-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; color: var(--color-danger);">
            <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
          </button>
        </div>
      </td>
    `;

    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('.emi-schedule-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      openEmiScheduleModal(id);
    });
  });

  tbody.querySelectorAll('.edit-loan-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-loan-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      deleteLoan(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const openModalBtn = document.getElementById('openAddLoanModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('loanModal');
  const form = document.getElementById('loanForm');
  const refreshBtn = document.getElementById('pageRefreshBtn');

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');

  const emiModal = document.getElementById('emiModal');
  const closeEmiBtn = document.getElementById('closeEmiModalBtn');
  const closeEmiFooterBtn = document.getElementById('closeEmiModalFooterBtn');

  if (refreshBtn) {
    refreshBtn.addEventListener('click', async () => {
      await loadLoansData();
      renderLoansPage();
      showToast("Loan liabilities synced successfully.", "success", "Synced Loans");
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
      document.getElementById('modalTitle').textContent = 'Log Loan Account';
      form.reset();
      document.getElementById('loanId').value = '';
      document.getElementById('loanPaidMonths').disabled = false; // Enable for new loans
      modal.classList.add('show');
      setTimeout(() => document.getElementById('loanLender').focus(), 100);
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

  // EMI modal close hooks
  const closeEmiModal = () => { if (emiModal) emiModal.classList.remove('show'); activeEmiLoanId = null; };
  if (closeEmiBtn) closeEmiBtn.addEventListener('click', closeEmiModal);
  if (closeEmiFooterBtn) closeEmiFooterBtn.addEventListener('click', closeEmiModal);
  if (emiModal) {
    emiModal.addEventListener('click', (e) => {
      if (e.target === emiModal) closeEmiModal();
    });
  }

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const id = document.getElementById('loanId').value;
      const lender = document.getElementById('loanLender').value.trim();
      const principal = parseFloat(document.getElementById('loanPrincipal').value);
      const rate = parseFloat(document.getElementById('loanRate').value);
      const emi = parseFloat(document.getElementById('loanEmi').value);
      const tenor = parseInt(document.getElementById('loanTenor').value);
      const paidMonths = parseInt(document.getElementById('loanPaidMonths').value);

      if (!lender || isNaN(principal) || principal <= 0 || isNaN(rate) || rate < 0 || isNaN(emi) || emi <= 0 || isNaN(tenor) || tenor <= 0 || isNaN(paidMonths) || paidMonths < 0) {
        showToast("Please input valid loan details.", "danger", "Validation Error");
        return;
      }

      if (paidMonths > tenor) {
        showToast("Paid installments cannot exceed total tenor months.", "danger", "Validation Error");
        return;
      }

      const payload = {
        lender,
        principal,
        rate,
        emi,
        tenor,
        paidMonths,
        start_date: dateToYMD(new Date()) // Default start date to today
      };

      try {
        let response;
        if (id) {
          response = await fetchApi(`/api/loans/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
          });
        } else {
          response = await fetchApi('/api/loans', {
            method: 'POST',
            body: JSON.stringify(payload)
          });
        }

        if (response && response.ok) {
          showToast(id ? `Loan account details updated for "${lender}".` : `Loan account configured for "${lender}".`, "success", id ? "Loan Updated" : "Loan Added");
          await loadLoansData();
          renderLoansPage();
          closeModal();
        } else {
          const resJson = response ? await response.json() : null;
          showToast(resJson?.message || "Failed to configure loan account.", "danger", "API Error");
        }
      } catch (err) {
        console.error("Save loan error:", err);
        showToast("Failed to connect to server.", "danger", "Network Error");
      }
    });
  }
}

function openEditModal(id) {
  const item = loansList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Modify Loan Account Details';
  document.getElementById('loanId').value = item.id;
  document.getElementById('loanLender').value = item.loan_name;
  document.getElementById('loanPrincipal').value = item.principal_amount;
  document.getElementById('loanRate').value = item.interest_rate;
  document.getElementById('loanEmi').value = item.emi_amount;
  document.getElementById('loanTenor').value = item.tenure_months;
  document.getElementById('loanPaidMonths').value = item.paid_months;
  document.getElementById('loanPaidMonths').disabled = true; // Disable paid months on update to prevent mismatch

  const modal = document.getElementById('loanModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('loanLender').focus(), 100);
  }
}

async function deleteLoan(id) {
  if (confirm("Are you sure you want to delete this loan account record?")) {
    const item = loansList.find(x => x.id === id);
    const lender = item ? item.loan_name : '';
    try {
      const response = await fetchApi(`/api/loans/${id}`, {
        method: 'DELETE'
      });
      if (response && response.ok) {
        showToast(`Loan record for "${lender}" deleted.`, "warning", "Loan Removed");
        await loadLoansData();
        renderLoansPage();
      } else {
        showToast("Failed to delete loan account.", "danger", "Error");
      }
    } catch (err) {
      console.error("Delete loan error:", err);
      showToast("Error connecting to server.", "danger", "Network Error");
    }
  }
}

async function openEmiScheduleModal(loanId) {
  const item = loansList.find(x => x.id === loanId);
  if (!item) return;

  activeEmiLoanId = loanId;
  document.getElementById('emiLoanName').textContent = item.loan_name;
  document.getElementById('emiLenderName').textContent = item.lender_name || 'Lender';
  document.getElementById('emiRemainingBal').textContent = '₹' + parseFloat(item.remaining_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });

  await renderEmiScheduleTable(loanId);

  const emiModal = document.getElementById('emiModal');
  if (emiModal) {
    emiModal.classList.add('show');
  }
}

async function renderEmiScheduleTable(loanId) {
  const tbody = document.getElementById('emiTableBody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">Loading schedule...</td></tr>';

  try {
    const response = await fetchApi(`/api/loans/${loanId}/emis`);
    if (!response) return;

    const result = await response.json();
    if (result && result.success && result.data && result.data.schedule) {
      const schedule = result.data.schedule;
      tbody.innerHTML = '';

      schedule.forEach((emi, index) => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border-color)';

        let statusBadge = '';
        let actionBtn = '';

        if (emi.status === 'paid') {
          statusBadge = '<span class="badge badge-success" style="font-weight: 700;">PAID</span>';
          actionBtn = '<span class="text-success" style="font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px;"><i data-lucide="check" style="width: 14px; height: 14px;"></i> Done</span>';
        } else if (emi.status === 'overdue') {
          statusBadge = '<span class="badge badge-danger" style="font-weight: 700;">OVERDUE</span>';
          actionBtn = `<button class="btn btn-primary btn-sm pay-emi-btn" data-emi-id="${emi.id}" style="padding: 0.15rem 0.4rem; font-size: 10px; height: auto;">Pay EMI</button>`;
        } else {
          statusBadge = '<span class="badge badge-secondary" style="font-weight: 700;">PENDING</span>';
          actionBtn = `<button class="btn btn-primary btn-sm pay-emi-btn" data-emi-id="${emi.id}" style="padding: 0.15rem 0.4rem; font-size: 10px; height: auto;">Pay EMI</button>`;
        }

        tr.innerHTML = `
          <td style="padding: 0.75rem 1rem; font-weight: 700;">EMI #${index + 1}</td>
          <td style="padding: 0.75rem 1rem;">${emi.due_date}</td>
          <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600;">₹${parseFloat(emi.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
          <td style="padding: 0.75rem 1rem; text-align: center;">${statusBadge}</td>
          <td style="padding: 0.75rem 1rem; text-align: center;">${actionBtn}</td>
        `;

        tbody.appendChild(tr);
      });

      tbody.querySelectorAll('.pay-emi-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const emiId = btn.getAttribute('data-emi-id');
          await payEmiInstallment(loanId, emiId);
        });
      });

      if (window.lucide) window.lucide.createIcons();

    } else {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--color-danger);">Failed to load schedule.</td></tr>';
    }
  } catch (err) {
    console.error("Load schedule error:", err);
    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--color-danger);">Network connection error.</td></tr>';
  }
}

async function payEmiInstallment(loanId, emiId) {
  try {
    const response = await fetchApi(`/api/loans/${loanId}/emis/${emiId}/pay`, {
      method: 'POST'
    });

    if (response && response.ok) {
      showToast("EMI installment paid successfully.", "success", "Payment Recorded");

      // Reload main page lists and metrics
      await loadLoansData();
      renderLoansPage();

      // Refresh the modal schedule
      const updatedLoan = loansList.find(x => x.id === loanId);
      if (updatedLoan) {
        document.getElementById('emiRemainingBal').textContent = '₹' + parseFloat(updatedLoan.remaining_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
      }
      await renderEmiScheduleTable(loanId);
    } else {
      const resJson = response ? await response.json() : null;
      showToast(resJson?.message || "Failed to record payment.", "danger", "Payment Error");
    }
  } catch (err) {
    console.error("Pay EMI error:", err);
    showToast("Network connection error.", "danger", "Network Error");
  }
}

function dateToYMD(date) {
  const d = date.getDate();
  const m = date.getMonth() + 1; // Month index starts at 0
  const y = date.getFullYear();
  return '' + y + '-' + (m <= 9 ? '0' + m : m) + '-' + (d <= 9 ? '0' + d : d);
}

function escapeHTML(str) {
  if (!str) return '';
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
