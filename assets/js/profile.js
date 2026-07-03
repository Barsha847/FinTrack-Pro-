/**
 * FinTrack Pro - User Profile Controller (Phase 4)
 */

import { showToast } from './utils.js';

export function initProfilePage() {
  const isProfilePage = document.querySelector('.profile-page-layout');
  if (!isProfilePage) return;

  console.warn("Initializing User Profile Page...");

  loadProfileDetails();
  setupEventListeners();
}

function loadProfileDetails() {
  const name = localStorage.getItem('fintrack_user_name') || 'John Doe';
  const email = localStorage.getItem('fintrack_user_email') || 'john.doe@example.com';
  
  const nameInput = document.getElementById('profileNameInput');
  const emailInput = document.getElementById('profileEmailInput');
  
  if (nameInput) nameInput.value = name;
  if (emailInput) emailInput.value = email;
}

function setupEventListeners() {
  const form = document.getElementById('profileDetailsForm');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const name = document.getElementById('profileNameInput').value.trim();
    const email = document.getElementById('profileEmailInput').value.trim();

    if (!name || !email) {
      showToast("Profile inputs cannot be empty.", "danger", "Validation Error");
      return;
    }

    // Save details to LocalStorage
    localStorage.setItem('fintrack_user_name', name);
    localStorage.setItem('fintrack_user_email', email);

    // Sync header profile and sidebar profiles
    const sidebarName = document.querySelector('.sidebar-profile-name');
    const sidebarEmail = document.querySelector('.sidebar-profile-email');
    if (sidebarName) sidebarName.textContent = name;
    if (sidebarEmail) sidebarEmail.textContent = email;

    showToast("Profile details updated successfully.", "success", "Profile Updated");
  });
}
