/**
 * FinTrack Pro - Navigation and Submission Router Module
 */

import { showToast, getRoutePath } from './utils.js';
import { validateFormInputs } from './validation.js';

export function initNavigation() {
  // Define auth flows and their redirect destinations
  const authFlows = [
    { formId: 'loginForm', routeKey: 'dashboard', successMsg: 'Welcome back! Logging you in...', title: 'Login Successful' },
    { formId: 'signupForm', routeKey: 'login', successMsg: 'Account created! Redirecting to login...', title: 'Signup Successful' },
    { formId: 'forgotForm', routeKey: 'otp', successMsg: 'OTP sent! Redirecting to verification page...', title: 'Email Verified' },
    { formId: 'otpForm', routeKey: 'resetPassword', successMsg: 'OTP verified! Redirecting to password reset...', title: 'Code Verified' },
    { formId: 'resetForm', routeKey: 'login', successMsg: 'Password updated! Redirecting to login...', title: 'Password Reset Successful' }
  ];

  authFlows.forEach(flow => {
    const form = document.getElementById(flow.formId);
    if (!form) return;

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      
      // Call validator check if it is active. Form validation is checked by validation.js
      const isFormValid = form.checkValidity ? form.checkValidity() : true;
      const customValid = form.dataset.valid === 'true'; // Set by validation.js
      
      if (isFormValid && customValid) {
        // Collect form data if signing up or logging in to sync header profile
        if (flow.formId === 'signupForm') {
          const nameVal = document.getElementById('signupName')?.value.trim();
          const emailVal = document.getElementById('signupEmail')?.value.trim();
          if (nameVal) localStorage.setItem('fintrack_user_name', nameVal);
          if (emailVal) localStorage.setItem('fintrack_user_email', emailVal);
        } else if (flow.formId === 'loginForm') {
          const emailVal = document.getElementById('loginEmail')?.value.trim();
          if (emailVal) {
            localStorage.setItem('fintrack_user_email', emailVal);
            const currentName = localStorage.getItem('fintrack_user_name');
            if (!currentName) {
              const defaultName = emailVal.split('@')[0].split(/[._+-]+/)[0];
              const capitalizedName = defaultName.charAt(0).toUpperCase() + defaultName.slice(1);
              localStorage.setItem('fintrack_user_name', capitalizedName);
            }
          }
        }
        handleMockSubmission(form, getRoutePath(flow.routeKey), flow.successMsg, flow.title);
      } else {
        validateFormInputs(form);
        showToast('Please resolve all validation errors before proceeding.', 'danger', 'Form Invalid');
      }
    });
  });
}

function handleMockSubmission(form, targetUrl, successMessage, title) {
  const submitBtn = form.querySelector('[type="submit"]');
  if (!submitBtn) return;

  const originalContent = submitBtn.innerHTML;
  
  // Set button state to loading
  submitBtn.disabled = true;
  submitBtn.innerHTML = `
    <span class="preloader-spinner" style="width: 18px; height: 18px; border-width: 2px; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"></span>
    Processing...
  `;

  // Simulate network request (1.5 seconds latency)
  setTimeout(() => {
    showToast(successMessage, 'success', title);
    
    // Slide transition redirect
    setTimeout(() => {
      // In local filesystem (pages folder), make sure paths resolve correctly
      // Check if current page is inside pages/ directory and target doesn't need pages prefix
      const inPagesFolder = window.location.pathname.includes('/pages/');
      let destination = targetUrl;
      
      // If we are at index.html (root) and navigating to something in pages/
      if (!inPagesFolder && targetUrl !== 'dashboard.html' && targetUrl !== 'index.html') {
        destination = 'pages/' + targetUrl;
      }
      
      // If we are inside /pages/ and redirecting to index.html (go up)
      if (inPagesFolder && targetUrl === 'index.html') {
        destination = '../index.html';
      }
      
      window.location.href = destination;
    }, 1000);
  }, 1500);
}
