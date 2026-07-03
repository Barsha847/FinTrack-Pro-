/**
 * FinTrack Pro - Settings Controller (Phase 4)
 */

import { showToast } from './utils.js';

export function initSettingsPage() {
  const isSettingsPage = document.querySelector('.settings-page-layout');
  if (!isSettingsPage) return;

  console.warn("Initializing System Settings Page...");

  loadSettings();
  setupEventListeners();
}

function loadSettings() {
  const currency = localStorage.getItem('fintrack_currency') || 'INR';
  const currencySelect = document.getElementById('settingsCurrencySelect');
  if (currencySelect) currencySelect.value = currency;

  const is2FA = localStorage.getItem('fintrack_2fa_enabled') === 'true';
  const tfaToggle = document.getElementById('settingsTfaToggle');
  if (tfaToggle) tfaToggle.checked = is2FA;
}

function setupEventListeners() {
  const form = document.getElementById('settingsPreferencesForm');
  const secForm = document.getElementById('settingsSecurityForm');
  const tfaToggle = document.getElementById('settingsTfaToggle');

  // Preferences (Currency / Theme)
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const currency = document.getElementById('settingsCurrencySelect').value;
      localStorage.setItem('fintrack_currency', currency);

      showToast(`Global currency standard set to ${currency}. All listings updated.`, "success", "Settings Saved");
      
      // Auto-reload to apply currency transformations globally
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    });
  }

  // 2FA Toggle Action
  if (tfaToggle) {
    tfaToggle.addEventListener('change', () => {
      const active = tfaToggle.checked;
      localStorage.setItem('fintrack_2fa_enabled', active ? 'true' : 'false');
      
      if (active) {
        showToast("Two-Factor Authentication (2FA) enabled. Enter passcode on login.", "success", "Security Activated");
      } else {
        showToast("Two-Factor Authentication deactivated.", "warning", "Security Config Updated");
      }
    });
  }

  // Password Update
  if (secForm) {
    secForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const current = document.getElementById('currentPasswordInput').value;
      const newPass = document.getElementById('newPasswordInput').value;
      const confirmPass = document.getElementById('confirmPasswordInput').value;

      if (!current || !newPass || !confirmPass) {
        showToast("Password validation parameters cannot be empty.", "danger", "Validation Error");
        return;
      }

      if (newPass !== confirmPass) {
        showToast("New Passwords do not match.", "danger", "Validation Error");
        return;
      }

      showToast("Security passwords changed successfully.", "success", "Password Updated");
      secForm.reset();
    });
  }
}
