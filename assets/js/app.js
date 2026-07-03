/**
 * FinTrack Pro - Main Entry Point (ES6 Module Core)
 */

import { initTheme } from './theme.js';
import { initSidebar } from './sidebar.js';
import { initNavigation } from './navigation.js';
import { initValidation } from './validation.js';
import { initAnimations } from './animation.js';
import { initRipples, initIcons } from './utils.js';
import { initDashboard } from './dashboard.js';
import { initIncomePage } from './income.js';
import { initExpensesPage } from './expenses.js';
import { initBudgetsPage } from './budgets.js';
import { initSavingsPage } from './savings.js';
import { initInvestmentsPage } from './investments.js';
import { initLoansPage } from './loans.js';
import { initBillsPage } from './bills.js';
import { initAnalyticsPage } from './analytics.js';
import { initReportsPage } from './reports.js';
import { initNotificationsPage } from './notifications.js';
import { initCalendarPage } from './calendar.js';
import { initProfilePage } from './profile.js';
import { initSettingsPage } from './settings.js';
import { initCommandPalette } from './palette.js';
import { initPolish } from './polish.js';

document.addEventListener('DOMContentLoaded', () => {
  // Initialize all subsystems
  initTheme();
  initSidebar();
  initNavigation();
  initValidation();
  initAnimations();
  initRipples();
  initIcons();
  
  // Dashboard & Finance Modules
  initDashboard();
  initIncomePage();
  initExpensesPage();
  initBudgetsPage();
  initSavingsPage();
  initInvestmentsPage();
  initLoansPage();
  initBillsPage();

  // Final Modules (Phase 4)
  initAnalyticsPage();
  initReportsPage();
  initNotificationsPage();
  initCalendarPage();
  initProfilePage();
  initSettingsPage();

  // Interface Polish (Phase 5)
  initCommandPalette();
  initPolish();

  // Initialize Navbar Mobile Drawer (landing index.html page specific)
  initMobileNavbar();
});

function initMobileNavbar() {
  const menuBtn = document.getElementById('mobileMenuToggle');
  const closeBtn = document.getElementById('mobileMenuClose');
  const drawer = document.getElementById('mobileNavPanel');
  
  if (!menuBtn || !drawer) return;

  // Create backdrop if not already in document
  let backdrop = document.getElementById('navBackdrop');
  if (!backdrop) {
    backdrop = document.createElement('div');
    backdrop.id = 'navBackdrop';
    backdrop.className = 'nav-backdrop';
    document.body.appendChild(backdrop);
  }

  menuBtn.addEventListener('click', () => {
    drawer.classList.add('open');
    backdrop.classList.add('show');
  });

  const closeMenu = () => {
    drawer.classList.remove('open');
    backdrop.classList.remove('show');
  };

  if (closeBtn) closeBtn.addEventListener('click', closeMenu);
  backdrop.addEventListener('click', closeMenu);

  const drawerLinks = drawer.querySelectorAll('.nav-link');
  drawerLinks.forEach(link => {
    link.addEventListener('click', closeMenu);
  });
}
