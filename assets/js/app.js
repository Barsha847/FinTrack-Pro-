/**
 * FinTrack Pro - Main Entry Point (ES6 Module Core)
 */

import { initTheme } from './theme.js';
import { initSidebar } from './sidebar.js';
import { initNavigation } from './navigation.js';
import { initValidation } from './validation.js';
import { initAnimations } from './animation.js';
import { initRipples, initIcons, syncUserProfile, setupLogout, initGlobalModalManager, showToast, checkPageAuth, initGlobalNotifications } from './utils.js';
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

/**
 * Centralized page authentication policy.
 * Only pages listed here will execute the authentication guard (checkPageAuth).
 * All other pages (including landing, login, signup, etc.) render freely.
 */
const PROTECTED_PAGES = new Set([
  'dashboard.html',
  'income.html',
  'expenses.html',
  'budgets.html',
  'savings.html',
  'investments.html',
  'loans.html',
  'bills.html',
  'reports.html',
  'analytics.html',
  'notifications.html',
  'calendar.html',
  'profile.html',
  'settings.html'
]);

/**
 * Safely dismiss the preloader. Called in finally blocks and as a fallback timeout.
 */
function dismissPreloaderSafely() {
  const preloader = document.getElementById('preloader');
  if (preloader && !preloader.classList.contains('fade-out')) {
    preloader.classList.add('fade-out');
    setTimeout(() => preloader.remove(), 400);
  }
}

// Maximum 5-second safety net: preloader is ALWAYS removed even if initialization completely fails.
const preloaderSafetyTimeout = setTimeout(() => {
  dismissPreloaderSafely();
}, 5000);

document.addEventListener('DOMContentLoaded', async () => {
  try {
    // Determine if the current page requires authentication
    const currentFile = window.location.pathname.split('/').pop() || '';
    const isProtectedPage = PROTECTED_PAGES.has(currentFile);

    // Only run authentication guard on protected application pages
    if (isProtectedPage) {
      await checkPageAuth();
    }

    // Initialize all subsystems
    initTheme();
    initSidebar();
    initNavigation();
    initValidation();
    initAnimations();
    initRipples();
    initIcons();
    
    // Global QA & UX Systems
    syncUserProfile();
    initGlobalNotifications();
    setupLogout();
    initGlobalModalManager();
    initContactForm();
    
    // Dashboard & Finance Modules
    initDashboard();
    initIncomePage();
    initExpensesPage();
    initBudgetsPage();
    initSavingsPage();
    initInvestmentsPage();
    initLoansPage();
    initBillsPage();

    // Final Modules
    initAnalyticsPage();
    initReportsPage();
    initNotificationsPage();
    initCalendarPage();
    initProfilePage();
    initSettingsPage();

    // Interface Polish
    initCommandPalette();
    initPolish();

    // Initialize Navbar Mobile Drawer (landing index.html page specific)
    initMobileNavbar();
  } catch (err) {
    console.error('FinTrack Pro initialization error:', err);
  } finally {
    // Always dismiss the preloader, even on error
    clearTimeout(preloaderSafetyTimeout);
    dismissPreloaderSafely();
  }
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

function initContactForm() {
  const form = document.getElementById('contactForm');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const submitBtn = form.querySelector('[type="submit"]');
    if (!submitBtn) return;

    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Sending...';

    setTimeout(() => {
      showToast("Your support message has been sent successfully. We will reply shortly.", "success", "Message Sent");
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
      form.reset();
    }, 1200);
  });
}
