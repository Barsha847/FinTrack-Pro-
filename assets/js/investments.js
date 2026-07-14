/**
 * FinTrack Pro - Investments Controller (Phase 5)
 */

import { showToast, fetchApi } from './utils.js';

let investmentsList = [];
let currentPage = 1;
const itemsPerPage = 8;
let searchQuery = '';
let selectedCategory = 'all';

export async function initInvestmentsPage() {
  const isInvestmentsPage = document.querySelector('.investments-page-layout');
  if (!isInvestmentsPage) return;

  console.warn("Initializing Investments Module...");

  setupEventListeners();
  await loadInvestmentsData();
  renderInvestmentsPage();
}

async function loadInvestmentsData() {
  try {
    const response = await fetchApi('/api/investments');
    if (!response) return;

    const result = await response.json();
    if (result && result.success && result.data) {
      investmentsList = result.data.investments || [];
    } else {
      investmentsList = [];
      showToast(result?.message || "Failed to load investments.", "danger", "API Error");
    }
  } catch (err) {
    console.error("Failed to load investments:", err);
    showToast("Error connecting to server to load portfolio.", "danger", "API Connection Error");
  }
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
    totalInvested += parseFloat(item.invested_amount || 0);
    totalCurrent += parseFloat(item.current_value || 0);
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

  const reverseMap = {
    'stock': 'stocks',
    'mutual_fund': 'mutualfunds',
    'gold': 'gold',
    'crypto': 'crypto',
    'other': 'others'
  };

  let filtered = investmentsList.filter(item => {
    const matchesSearch = (item.asset_name || '').toLowerCase().includes(searchQuery.toLowerCase()) || 
                          (item.asset_type || '').toLowerCase().includes(searchQuery.toLowerCase());
    
    const dbCat = item.asset_type;
    const filterCat = reverseMap[dbCat] || dbCat;
    const matchesCat = selectedCategory === 'all' || filterCat === selectedCategory;
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

  const categoryDisplayNames = {
    'stock': 'Equity Stocks',
    'mutual_fund': 'Mutual Funds',
    'gold': 'Gold & Commodities',
    'crypto': 'Cryptocurrency',
    'other': 'Other Assets'
  };

  paginated.forEach(item => {
    const invested = parseFloat(item.invested_amount || 0);
    const current = parseFloat(item.current_value || 0);
    const profit = current - invested;
    const roi = parseFloat(item.roi || 0);
    const textClass = profit >= 0 ? 'text-success' : 'text-danger';
    const displayCat = categoryDisplayNames[item.asset_type] || item.asset_type;

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.asset_name)}</td>
      <td style="padding: 1rem 1.25rem; text-transform: uppercase;"><span class="badge badge-primary">${escapeHTML(displayCat)}</span></td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">₹${invested.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right; color: var(--text-primary);">₹${current.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
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
      const id = btn.getAttribute('data-id');
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-asset-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
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
    refreshBtn.addEventListener('click', async () => {
      await loadInvestmentsData();
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
    form.addEventListener('submit', async (e) => {
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

      const payload = {
        asset_name: name,
        asset_type: category,
        invested_amount: invested,
        current_value: current
      };

      try {
        let response;
        if (id) {
          response = await fetchApi(`/api/investments/${id}`, {
            method: 'PUT',
            body: JSON.stringify(payload)
          });
        } else {
          response = await fetchApi('/api/investments', {
            method: 'POST',
            body: JSON.stringify(payload)
          });
        }

        if (response && response.ok) {
          showToast(id ? `Asset details updated for "${name}".` : `Asset holding locked for "${name}".`, "success", id ? "Portfolio Updated" : "Portfolio Logged");
          await loadInvestmentsData();
          renderInvestmentsPage();
          closeModal();
        } else {
          const resJson = response ? await response.json() : null;
          showToast(resJson?.message || "Failed to log asset.", "danger", "Error");
        }
      } catch (err) {
        console.error("Save investment error:", err);
        showToast("Failed to connect to server.", "danger", "Network Error");
      }
    });
  }
}

function openEditModal(id) {
  const item = investmentsList.find(x => x.id === id);
  if (!item) return;

  const reverseMap = {
    'stock': 'stocks',
    'mutual_fund': 'mutualfunds',
    'gold': 'gold',
    'crypto': 'crypto',
    'other': 'others'
  };

  document.getElementById('modalTitle').textContent = 'Modify Investment Asset';
  document.getElementById('assetId').value = item.id;
  document.getElementById('assetName').value = item.asset_name;
  document.getElementById('assetCategory').value = reverseMap[item.asset_type] || item.asset_type;
  document.getElementById('assetInvested').value = item.invested_amount;
  document.getElementById('assetCurrent').value = item.current_value;

  const modal = document.getElementById('investmentModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('assetName').focus(), 100);
  }
}

async function deleteAsset(id) {
  if (confirm("Are you sure you want to delete this asset log?")) {
    const item = investmentsList.find(x => x.id === id);
    const name = item ? item.asset_name : '';
    try {
      const response = await fetchApi(`/api/investments/${id}`, {
        method: 'DELETE'
      });
      if (response && response.ok) {
        showToast(`Asset log for "${name}" deleted.`, "warning", "Portfolio Log Removed");
        await loadInvestmentsData();
        renderInvestmentsPage();
      } else {
        showToast("Failed to delete asset log.", "danger", "Error");
      }
    } catch (err) {
      console.error("Delete asset error:", err);
      showToast("Error connecting to server.", "danger", "Network Error");
    }
  }
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
