/**
 * FinTrack Pro - Command Palette & Keyboard Shortcuts Controller (Phase 5)
 */

const COMMANDS = [
  { title: 'Go to Dashboard', url: 'dashboard.html', shortcut: 'g d' },
  { title: 'Go to Income Tracking', url: 'income.html', shortcut: 'g i' },
  { title: 'Go to Expenses', url: 'expenses.html', shortcut: 'g e' },
  { title: 'Go to Budgets', url: 'budgets.html', shortcut: 'g b' },
  { title: 'Go to Savings Goals', url: 'savings.html', shortcut: 'g s' },
  { title: 'Go to Investments', url: 'investments.html', shortcut: 'g v' },
  { title: 'Go to Loans & EMI', url: 'loans.html', shortcut: 'g l' },
  { title: 'Go to Bill Reminders', url: 'bills.html', shortcut: 'g r' },
  { title: 'Go to Profile', url: 'profile.html', shortcut: 'g p' },
  { title: 'Go to Settings', url: 'settings.html', shortcut: 'g t' }
];

let activeIndex = 0;
let filteredCommands = [...COMMANDS];

export function initCommandPalette() {
  // Ensure we are inside a dashboard page layout, not index.html landing page
  const hasLayout = document.querySelector('.dashboard-layout');
  if (!hasLayout) return;

  console.warn("Initializing Command Palette & Hotkeys...");

  createPaletteDOM();
  setupListeners();
}

function createPaletteDOM() {
  if (document.getElementById('commandPalette')) return;

  const backdrop = document.createElement('div');
  backdrop.id = 'commandPalette';
  backdrop.className = 'palette-backdrop';
  backdrop.setAttribute('role', 'dialog');
  backdrop.setAttribute('aria-modal', 'true');
  backdrop.setAttribute('aria-label', 'Command search palette');

  backdrop.innerHTML = `
    <div class="palette-container">
      <div class="palette-search-wrapper">
        <i data-lucide="search" style="width: 18px; height: 18px; color: var(--text-muted);"></i>
        <input type="text" class="palette-search-input" id="paletteSearchInput" placeholder="Type a navigation command (e.g. 'Expenses')..." autocomplete="off">
        <kbd class="palette-shortcut">ESC</kbd>
      </div>
      <div class="palette-results" id="paletteResultsContainer"></div>
    </div>
  `;

  document.body.appendChild(backdrop);
  if (window.lucide) window.lucide.createIcons();
}

function renderResults() {
  const container = document.getElementById('paletteResultsContainer');
  if (!container) return;

  container.innerHTML = '';
  
  if (filteredCommands.length === 0) {
    container.innerHTML = `
      <div style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 11px;">
        No matching commands found.
      </div>
    `;
    return;
  }

  filteredCommands.forEach((cmd, idx) => {
    const el = document.createElement('div');
    el.className = `palette-item ${idx === activeIndex ? 'active' : ''}`;
    el.innerHTML = `
      <div class="d-flex align-center gap-2">
        <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
        <span>${cmd.title}</span>
      </div>
      <kbd class="palette-shortcut">${cmd.shortcut.toUpperCase()}</kbd>
    `;

    el.addEventListener('click', () => {
      navigateCommand(cmd);
    });

    container.appendChild(el);
  });

  if (window.lucide) window.lucide.createIcons();
}

function navigateCommand(cmd) {
  closePalette();
  window.location.href = cmd.url;
}

function openPalette() {
  const palette = document.getElementById('commandPalette');
  const input = document.getElementById('paletteSearchInput');
  if (!palette || !input) return;

  filteredCommands = [...COMMANDS];
  activeIndex = 0;
  input.value = '';
  renderResults();

  palette.classList.add('show');
  setTimeout(() => input.focus(), 50);
}

function closePalette() {
  const palette = document.getElementById('commandPalette');
  if (palette) palette.classList.remove('show');
}

function setupListeners() {
  const palette = document.getElementById('commandPalette');
  const input = document.getElementById('paletteSearchInput');

  // Trigger Open (Ctrl+K or Cmd+K)
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      openPalette();
    }
  });

  // Hotkey Sequences (g then key)
  let lastKeyTime = 0;
  let activeG = false;

  document.addEventListener('keydown', (e) => {
    // Avoid firing sequences when inside inputs or textareas
    if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'SELECT' || document.activeElement.tagName === 'TEXTAREA') {
      return;
    }

    const now = Date.now();
    if (e.key === 'g') {
      activeG = true;
      lastKeyTime = now;
      return;
    }

    if (activeG && (now - lastKeyTime < 1000)) {
      const match = COMMANDS.find(cmd => cmd.shortcut === `g ${e.key.toLowerCase()}`);
      if (match) {
        e.preventDefault();
        window.location.href = match.url;
      }
    }

    activeG = false;
  });

  if (!palette || !input) return;

  // Backdrop click closes palette
  palette.addEventListener('click', (e) => {
    if (e.target === palette) closePalette();
  });

  // Search input filtering
  input.addEventListener('input', () => {
    const query = input.value.toLowerCase().trim();
    filteredCommands = COMMANDS.filter(cmd => cmd.title.toLowerCase().includes(query));
    activeIndex = 0;
    renderResults();
  });

  // Arrow Keys Navigation
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closePalette();
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % filteredCommands.length;
      renderResults();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + filteredCommands.length) % filteredCommands.length;
      renderResults();
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (filteredCommands[activeIndex]) {
        navigateCommand(filteredCommands[activeIndex]);
      }
    }
  });
}
