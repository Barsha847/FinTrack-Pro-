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

document.addEventListener('DOMContentLoaded', () => {
  // Initialize all subsystems
  initTheme();
  initSidebar();
  initNavigation();
  initValidation();
  initAnimations();
  initRipples();
  initIcons();
  initDashboard();

  // Initialize Navbar Mobile Drawer (specific to landing index.html)
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

  // Close menu drawer if a page anchor link is clicked
  const drawerLinks = drawer.querySelectorAll('.nav-link');
  drawerLinks.forEach(link => {
    link.addEventListener('click', closeMenu);
  });
}
