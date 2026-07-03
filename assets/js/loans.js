/**
 * FinTrack Pro - Loans Controller (Phase 2)
 */

import { showToast } from './utils.js';

const LOANS_STORAGE_KEY = 'fintrack_loans_v1';

let loansList = [];
let currentPage = 1;
const itemsPerPage = 8;

export function initLoansPage() {
  const isLoansPage = document.querySelector('.loans-page-layout');
  if (!isLoansPage) return;

  console.warn("Initializing Loans & EMI Module...");

  loadLoansData();
  setupEventListeners();
  renderLoansPage();
}

function loadLoansData() {
  const data = localStorage.getItem(LOANS_STORAGE_KEY);
  if (data) {
    try { loansList = JSON.parse(data); } catch (e) { loansList = []; }
  } else {
    loansList = [
      { id: 1, lender: 'HDFC Education Loan', principal: 300000.00, rate: 8.5, emi: 8500.00, tenor: 36, paidMonths: 12 }
    ];
    localStorage.setItem(LOANS_STORAGE_KEY, JSON.stringify(loansList));
  }
}

function saveLoansData() {
  localStorage.setItem(LOANS_STORAGE_KEY, JSON.stringify(loansList));
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

  let totalPrincipal = 0;
  let totalEmi = 0;
  let totalPaidRatio = 0;

  loansList.forEach(item => {
    totalPrincipal += item.principal;
    totalEmi += item.emi;
    totalPaidRatio += (item.paidMonths / item.tenor);
  });

  const avgPaidRatio = loansList.length > 0 ? (totalPaidRatio / loansList.length) * 100 : 0;

  if (countEl) countEl.textContent = `${loansList.length} Accounts`;
  if (principalEl) principalEl.textContent = '₹' + totalPrincipal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (emiEl) emiEl.textContent = '₹' + totalEmi.toLocaleString('en-IN', { minimumFractionDigits: 2 });
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
    const ratio = Math.min((item.paidMonths / item.tenor) * 100, 100);
    const ratioRounded = Math.round(ratio);

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.lender)}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">₹${item.principal.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">${item.rate}%</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right; color: var(--color-danger);">₹${item.emi.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">${item.tenor} Mo</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex align-center gap-2">
          <div style="flex: 1; height: 8px; background-color: var(--bg-secondary); border-radius: var(--radius-full); overflow: hidden;">
            <div style="width: ${ratio}%; height: 100%; background: linear-gradient(135deg, var(--color-primary-start), var(--color-primary-end)); border-radius: var(--radius-full); transition: width 0.4s ease;"></div>
          </div>
          <span style="font-size: 10px; font-weight: 700;">${item.paidMonths}/${item.tenor} (${ratioRounded}%)</span>
        </div>
      </td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
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

  tbody.querySelectorAll('.edit-loan-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-loan-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
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

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadLoansData();
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

  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const id = document.getElementById('loanId').value;
      const lender = document.getElementById('loanLender').value.trim();
      const principal = parseFloat(document.getElementById('loanPrincipal').value);
      const rate = parseFloat(document.getElementById('loanRate').value);
      const emi = parseFloat(document.getElementById('loanEmi').value);
      const tenor = parseInt(document.getElementById('loanTenor').value);
      const paidMonths = parseInt(document.getElementById('loanPaidMonths').value);

      if (!lender || isNaN(principal) || principal <= 0 || isNaN(rate) || rate <= 0 || isNaN(emi) || emi <= 0 || isNaN(tenor) || tenor <= 0 || isNaN(paidMonths) || paidMonths < 0) {
        showToast("Please input valid loan details.", "danger", "Validation Error");
        return;
      }

      if (paidMonths > tenor) {
        showToast("Paid installments cannot exceed total tenor months.", "danger", "Validation Error");
        return;
      }

      if (id) {
        const index = loansList.findIndex(x => x.id === parseInt(id));
        if (index !== -1) {
          loansList[index] = { ...loansList[index], lender, principal, rate, emi, tenor, paidMonths };
          showToast(`Loan account details updated for "${lender}".`, "success", "Loan Updated");
        }
      } else {
        const newLoan = {
          id: Date.now(),
          lender,
          principal,
          rate,
          emi,
          tenor,
          paidMonths
        };
        loansList.push(newLoan);
        showToast(`Loan account configured for "${lender}".`, "success", "Loan Added");
      }

      saveLoansData();
      renderLoansPage();
      closeModal();
    });
  }
}

function openEditModal(id) {
  const item = loansList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Modify Loan Account Details';
  document.getElementById('loanId').value = item.id;
  document.getElementById('loanLender').value = item.lender;
  document.getElementById('loanPrincipal').value = item.principal;
  document.getElementById('loanRate').value = item.rate;
  document.getElementById('loanEmi').value = item.emi;
  document.getElementById('loanTenor').value = item.tenor;
  document.getElementById('loanPaidMonths').value = item.paidMonths;

  const modal = document.getElementById('loanModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('loanLender').focus(), 100);
  }
}

function deleteLoan(id) {
  if (confirm("Are you sure you want to delete this loan account record?")) {
    const item = loansList.find(x => x.id === id);
    const lender = item ? item.lender : '';
    loansList = loansList.filter(x => x.id !== id);
    saveLoansData();
    renderLoansPage();
    showToast(`Loan record for "${lender}" deleted.`, "warning", "Loan Removed");
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
