/**
 * FinTrack Pro - Savings Controller (Phase 2)
 */

import { showToast } from './utils.js';

const SAVINGS_STORAGE_KEY = 'fintrack_savings_v1';

let savingsList = [];
let currentPage = 1;
const itemsPerPage = 8;

export function initSavingsPage() {
  const isSavingsPage = document.querySelector('.savings-page-layout');
  if (!isSavingsPage) return;

  console.warn("Initializing Savings Goals Module...");

  loadSavingsData();
  setupEventListeners();
  renderSavingsPage();
}

function loadSavingsData() {
  const data = localStorage.getItem(SAVINGS_STORAGE_KEY);
  if (data) {
    try { savingsList = JSON.parse(data); } catch (e) { savingsList = []; }
  } else {
    savingsList = [
      { id: 1, name: 'Premium Workstation Setup', target: 120000.00, saved: 102000.00, date: '2026-09-30' }
    ];
    localStorage.setItem(SAVINGS_STORAGE_KEY, JSON.stringify(savingsList));
  }
}

function saveSavingsData() {
  localStorage.setItem(SAVINGS_STORAGE_KEY, JSON.stringify(savingsList));
}

function renderSavingsPage() {
  renderMetrics();
  renderTable();
}

function renderMetrics() {
  const countEl = document.getElementById('activeGoalsVal');
  const targetEl = document.getElementById('totalTargetVal');
  const savedEl = document.getElementById('savedAmountVal');
  const pctEl = document.getElementById('overallSavingsPct');

  let totalTarget = 0;
  let totalSaved = 0;

  savingsList.forEach(item => {
    totalTarget += item.target;
    totalSaved += item.saved;
  });

  const overallPct = totalTarget > 0 ? Math.round((totalSaved / totalTarget) * 100) : 0;

  if (countEl) countEl.textContent = `${savingsList.length} Goals`;
  if (targetEl) targetEl.textContent = '₹' + totalTarget.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (savedEl) savedEl.textContent = '₹' + totalSaved.toLocaleString('en-IN', { minimumFractionDigits: 2 });
  if (pctEl) pctEl.textContent = `${overallPct}%`;
}

function renderTable() {
  const tbody = document.getElementById('tableBodyContainer');
  if (!tbody) return;

  tbody.innerHTML = '';

  const totalItems = savingsList.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;

  const startIdx = (currentPage - 1) * itemsPerPage;
  const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
  const paginated = savingsList.slice(startIdx, endIdx);

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
            <div class="empty-state-title">No savings targets configured</div>
            <div class="empty-state-desc">Set a savings objective to organize your deposits and targets.</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  paginated.forEach(item => {
    const ratio = Math.min((item.saved / item.target) * 100, 100);
    const ratioRounded = Math.round((item.saved / item.target) * 100);

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--border-color)';

    tr.innerHTML = `
      <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-primary);">${escapeHTML(item.name)}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right;">₹${item.target.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; font-weight: 600; text-align: right; color: var(--color-success);">₹${item.saved.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex align-center gap-2">
          <div style="flex: 1; height: 8px; background-color: var(--bg-secondary); border-radius: var(--radius-full); overflow: hidden;">
            <div style="width: ${ratio}%; height: 100%; background: linear-gradient(135deg, var(--color-success-light), var(--color-success)); border-radius: var(--radius-full); transition: width 0.4s ease;"></div>
          </div>
          <span style="font-size: 10px; font-weight: 700; color: var(--color-success);">${ratioRounded}%</span>
        </div>
      </td>
      <td style="padding: 1rem 1.25rem; color: var(--text-secondary);">${item.date}</td>
      <td style="padding: 1rem 1.25rem; text-align: center;">
        <div class="d-flex gap-2 justify-center">
          <button class="btn btn-secondary btn-sm edit-goal-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto;">
            <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i>
          </button>
          <button class="btn btn-secondary btn-sm delete-goal-btn" data-id="${item.id}" style="padding: 0.25rem 0.5rem; height: auto; color: var(--color-danger);">
            <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
          </button>
        </div>
      </td>
    `;

    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('.edit-goal-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      openEditModal(id);
    });
  });

  tbody.querySelectorAll('.delete-goal-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = parseInt(btn.getAttribute('data-id'));
      deleteGoal(id);
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupEventListeners() {
  const openModalBtn = document.getElementById('openAddSavingsModalBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const modal = document.getElementById('savingsModal');
  const form = document.getElementById('savingsForm');
  const refreshBtn = document.getElementById('pageRefreshBtn');

  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadSavingsData();
      renderSavingsPage();
      showToast("Savings target balances synced.", "success", "Synced Savings");
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
      document.getElementById('modalTitle').textContent = 'Configure Savings Goal';
      form.reset();
      document.getElementById('goalId').value = '';
      modal.classList.add('show');
      setTimeout(() => document.getElementById('goalName').focus(), 100);
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
      
      const id = document.getElementById('goalId').value;
      const name = document.getElementById('goalName').value.trim();
      const target = parseFloat(document.getElementById('goalTarget').value);
      const saved = parseFloat(document.getElementById('goalSaved').value);
      const date = document.getElementById('goalDate').value;

      if (!name || isNaN(target) || target <= 0 || isNaN(saved) || saved < 0) {
        showToast("Please input valid target balances.", "danger", "Validation Error");
        return;
      }

      if (id) {
        const index = savingsList.findIndex(x => x.id === parseInt(id));
        if (index !== -1) {
          savingsList[index] = { ...savingsList[index], name, target, saved, date };
          showToast(`Savings goal "${name}" details updated.`, "success", "Goal Updated");
        }
      } else {
        const newGoal = {
          id: Date.now(),
          name,
          target,
          saved,
          date
        };
        savingsList.push(newGoal);
        showToast(`Savings target "${name}" created.`, "success", "Goal Configured");
      }

      saveSavingsData();
      renderSavingsPage();
      closeModal();
    });
  }
}

function openEditModal(id) {
  const item = savingsList.find(x => x.id === id);
  if (!item) return;

  document.getElementById('modalTitle').textContent = 'Modify Savings Goal';
  document.getElementById('goalId').value = item.id;
  document.getElementById('goalName').value = item.name;
  document.getElementById('goalTarget').value = item.target;
  document.getElementById('goalSaved').value = item.saved;
  document.getElementById('goalDate').value = item.date;

  const modal = document.getElementById('savingsModal');
  if (modal) {
    modal.classList.add('show');
    setTimeout(() => document.getElementById('goalName').focus(), 100);
  }
}

function deleteGoal(id) {
  if (confirm("Are you sure you want to remove this savings goal?")) {
    const item = savingsList.find(x => x.id === id);
    const name = item ? item.name : '';
    savingsList = savingsList.filter(x => x.id !== id);
    saveSavingsData();
    renderSavingsPage();
    showToast(`Savings goal target for "${name}" deleted.`, "warning", "Goal Removed");
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
