/**
 * FinTrack Pro - Investments Controller (Phase 2)
 */

import { showToast } from './utils.js';

const INVEST_STORAGE_KEY = 'fintrack_investments_v1';

let investmentsList = [];
let currentPage = 1;
const itemsPerPage = 8;
let searchQuery = '';
let selectedCategory = 'all';

export function initInvestmentsPage() {
  const isInvestmentsPage = document.querySelector('.investments-page-layout');
  if (!isInvestmentsPage) return;

  console.warn("Initializing Investments Module...");

  loadInvestmentsData();
  setupEventListeners();
  renderInvestmentsPage();
}

function loadInvestmentsData() {
  const data = localStorage.getItem(INVEST_STORAGE_KEY);
  if (data) {
    try { investmentsList = JSON.parse(data); } catch (e) { investmentsList = []; }
  } else {
    investmentsList = [
      { id: 1, name: 'HDFC Equity Growth Fund', category: 'mutualfunds', invested: 45000.00, current: 52400.00 }
    ];
    localStorage.setItem(INVEST_STORAGE_KEY, JSON.stringify(investmentsList));
  }
}

function saveInvestmentsData() {
  localStorage.setItem(INVEST_STORAGE_KEY, JSON.stringify(investmentsList));
}

function renderInvestmentsPage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const portfolioEl = document.getElementById('portfolioVal');
  const investedEl = document.getElementById('investedVal');
  const profitEl = document.getElementById('profitVal');
  const roiEl = document.getElementById('avgRoiVal');

  let totalInvested = 0;
  let totalCurrent = 0;

  investmentsList.forEach(item => {
    totalInvested += item.invested;
    totalCurrent += item.current;
  });

  const netProfit = totalCurrent - totalInvested;
  const overallRoi = totalInvested > 0 ? ((netProfit / totalInvested) * 100) : 0;

  if (portfolioEl) portfolioEl.textContent = '₹' + totalCurrent.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (investedEl) investedEl.textContent = '₹' + totalInvested.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  
  if (profitEl) {
    profitEl.textContent = (netProfit >= 0 ? '+' : '') + '₹' + netProfit.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    profitEl.className = netProfit >= 0 ? 'stats-value text-success' : 'stats-value text-danger';
  }

  if (roiEl) {
    roiEl.textContent = (overallRoi >= 0 ? '+' : '') + overallRoi.toFixed(2) + '%';
    roiEl.className = overallRoi >= 0 ? 'stats-value text-success' : 'stats-value text-danger';
  }
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  let filtered = investmentsList.filter(item => {
    const matchesSearch = item.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
                          item.category.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesCat = selectedCategory === 'all' || item.category === selectedCategory;
    return matchesSearch && matchesCat;
  });

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
        <td colspan="6" style="padding: 3rem; text-align: center;">
          <div class="empty-state-container">
            <svg class="empty-state-svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="empty-state-title">No matching assets found</div>
            <div class="empty-state-desc">Try modifying your filter categories or log a new asset holding.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  paginated.forEach(item => {
    const profit = item.current - item.invested;
    const roi = item.invested > 0 ? ((profit / item.invested) * 100) : 0;
    const textClass = profit >= 0 ? 'text-success' : 'text-danger';

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.name)}</td>
      <td style="padding: 1rem 1.25rem; text-transform: uppercase;"><span class="badge badge-primary">${item.category}</span></td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">₹${item.invested.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right; color: var(--text-primary);">₹${item.current.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 700; text-align: right;" class="${textClass}">
        ${roi >= 0 ? '+' : ''}${roi.toFixed(2)}%
      </td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
          <button class="btn btn-secondary btn-sm edit-asset-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto;">
            <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i>
          </button>
          <button class="btn btn-secondary btn-sm delete-asset-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; color: var(--color-danger);">
            <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
          </button>
        </div>
      </td>
    `;

    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('.edit-asset-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-asset-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      deleteAsset(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const searchInput = document.getElementById('dashboardSearchInput');
  const catFilter = document.getElementById('categoryFilter');
  const openModalBtn = document.getElementById('openAddAssetModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('investmentModal');
  const form = document.getElementById('investmentForm');
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
      loadInvestmentsData();
      renderInvestmentsPage();
      showToast("Investment portfolios synced.", "success", "Portfolio Synced");
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
      document.getElementById('modalTitle').textContent = 'Log Investment Asset';
      form.reset();
      document.getElementById('assetId').value = '';
      modal.classList.add('show');
      setTimeout(() => document.getElementById('assetName').focus(), 100);
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
      
      const id = document.getElementById('assetId').value;
      const name = document.getElementById('assetName').value.trim();
      const category = document.getElementById('assetCategory').value;
      const invested = parseFloat(document.getElementById('assetInvested').value);
      const current = parseFloat(document.getElementById('assetCurrent').value);

      if (!name || isNaN(invested) || invested <= 0 || isNaN(current) || current <= 0) {
        showToast("Please input valid asset evaluations.", "danger", "Validation Error");
        return;
      }

      if (id) {
        const index = investmentsList.findIndex(x => x.id === parseInt(id));
        if (index !== -1) {
          investmentsList[index] = { ...investmentsList[index], name, category, invested, current };
          showToast(`Asset details updated for "${name}".`, "success", "Portfolio Updated");
        }
      } else {
        const newAsset = {
          id: Date.now(),
          name,
          category,
          invested,
          current
        };
        investmentsList.push(newAsset);
        showToast(`Asset holding locked for "${name}".`, "success", "Portfolio Logged");
      }

      saveInvestmentsData();
      renderInvestmentsPage();
      closeModal();
    });
  }
}

function openEditModal(id) {
  const item = investmentsList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Modify Investment Asset';
  document.getElementById('assetId').value = item.id;
  document.getElementById('assetName').value = item.name;
  document.getElementById('assetCategory').value = item.category;
  document.getElementById('assetInvested').value = item.invested;
  document.getElementById('assetCurrent').value = item.current;

  const modal = document.getElementById('investmentModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('assetName').focus(), 100);
  }
}

function deleteAsset(id) {
  if (confirm("Are you sure you want to delete this asset log?")) {
    const item = investmentsList.find(x => x.id === id);
    const name = item ? item.name : '';
    investmentsList = investmentsList.filter(x => x.id !== id);
    saveInvestmentsData();
    renderInvestmentsPage();
    showToast(`Asset log for "${name}" deleted.`, "warning", "Portfolio Log Removed");
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
