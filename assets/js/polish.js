/**
 * FinTrack Pro - Frontend Polish & Micro-interactions Controller (Phase 5)
 */

export function initPolish() {
  const hasLayout = document.querySelector('.dashboard-layout');
  if (!hasLayout) return;

  console.warn("Initializing Interface Polish Subsystems...");

  createFABDOM();
  createBackToTopDOM();
  setupURLActionTrigger();
}

function createFABDOM() {
  if (document.querySelector('.fab-container')) return;

  const container = document.createElement('div');
  container.className = 'fab-container';
  container.innerHTML = `
    <button class="fab-trigger" aria-haspopup="true" aria-expanded="false" aria-label="Quick action menu">
      <i data-lucide="plus"></i>
    </button>
    <div class="fab-menu" role="menu">
      <a href="income.html?action=add" class="fab-item" data-tooltip="Add Income" role="menuitem"><i data-lucide="arrow-down-left"></i></a>
      <a href="expenses.html?action=add" class="fab-item" data-tooltip="Log Expense" role="menuitem"><i data-lucide="arrow-up-right"></i></a>
      <a href="budgets.html?action=add" class="fab-item" data-tooltip="Set Budget" role="menuitem"><i data-lucide="piggy-bank"></i></a>
    </div>
  `;

  document.body.appendChild(container);

  // Toggle open
  const trigger = container.querySelector('.fab-trigger');
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = container.classList.toggle('open');
    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Close radial menu on outside click
  document.addEventListener('click', () => {
    container.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
  });

  if (window.lucide) window.lucide.createIcons();
}

function createBackToTopDOM() {
  if (document.querySelector('.back-to-top-btn')) return;

  const btn = document.createElement('button');
  btn.className = 'back-to-top-btn';
  btn.setAttribute('aria-label', 'Back to top of page');
  btn.innerHTML = `<i data-lucide="chevron-up"></i>`;
  document.body.appendChild(btn);

  // Scroll toggle visibility
  window.addEventListener('scroll', () => {
    if (window.scrollY > 300) {
      btn.classList.add('show');
    } else {
      btn.classList.remove('show');
    }
  });

  // Smooth scroll action
  btn.addEventListener('click', () => {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  if (window.lucide) window.lucide.createIcons();
}

function setupURLActionTrigger() {
  const params = new URLSearchParams(window.location.search);
  const action = params.get('action');

  if (action === 'add') {
    // Attempt to locate and press "Add" button
    setTimeout(() => {
      const addIncomeBtn = document.getElementById('openAddIncomeModalBtn');
      const addExpenseBtn = document.getElementById('openAddExpenseModalBtn');
      const addBudgetBtn = document.getElementById('openAddBudgetModalBtn');

      if (addIncomeBtn) addIncomeBtn.click();
      if (addExpenseBtn) addExpenseBtn.click();
      if (addBudgetBtn) addBudgetBtn.click();
    }, 300);
  }
}
