/**
 * FinTrack Pro - Theme Management Module (Light/Dark Mode)
 */

const THEME_KEY = 'fintrack_theme';

export function initTheme() {
  const savedTheme = localStorage.getItem(THEME_KEY);
  const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  
  // Choose theme: LocalStorage -> System preference -> Default light
  const initialTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
  
  setTheme(initialTheme);
  
  // Set up listeners on theme toggle buttons (can be multiple: desktop navbar, mobile drawer)
  const toggleButtons = document.querySelectorAll('.theme-toggle-trigger-btn');
  toggleButtons.forEach(btn => {
    btn.addEventListener('click', toggleTheme);
  });
}

export function toggleTheme() {
  const currentTheme = document.documentElement.getAttribute('data-theme');
  const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
  setTheme(targetTheme);
}

function setTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem(THEME_KEY, theme);
  
  // Dispatch a custom event to notify other components (e.g. Chart.js when integrated)
  const themeEvent = new CustomEvent('themeChanged', { detail: { theme } });
  window.dispatchEvent(themeEvent);
}
