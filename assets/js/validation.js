/**
 * FinTrack Pro - Validation & Authentication Integration Module
 */

import { showToast, fetchApi, getRoutePath } from './utils.js';

export function initValidation() {
  // Page access check and dynamic email display
  verifyPageAccess();

  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    // Initial validation state for custom check
    form.dataset.valid = 'false';
    
    const inputs = form.querySelectorAll('input:not([type="checkbox"])');
    inputs.forEach(input => {
      // Live validation on blur & input
      input.addEventListener('blur', () => validateInput(input, form));
      input.addEventListener('input', () => {
        if (input.closest('.form-group').classList.contains('is-invalid')) {
          validateInput(input, form);
        }
        checkFormValidity(form);
      });
    });

    // Handle show/hide password buttons
    const togglePasswordButtons = form.querySelectorAll('.password-toggle-btn');
    togglePasswordButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const input = btn.closest('.form-control-wrapper').querySelector('input');
        if (input.type === 'password') {
          input.type = 'text';
          btn.innerHTML = '<i data-lucide="eye-off"></i>';
        } else {
          input.type = 'password';
          btn.innerHTML = '<i data-lucide="eye"></i>';
        }
        if (window.lucide) window.lucide.createIcons();
      });
    });

    // Password strength check (if meter is present)
    const passwordInput = form.querySelector('.password-strength-input');
    if (passwordInput) {
      passwordInput.addEventListener('input', () => {
        evaluatePasswordStrength(passwordInput, form);
      });
    }

    // Passwords match check
    const confirmPasswordInput = form.querySelector('.confirm-password-input');
    if (confirmPasswordInput && passwordInput) {
      confirmPasswordInput.addEventListener('input', () => {
        validatePasswordMatch(passwordInput, confirmPasswordInput, form);
      });
    }

    // Initial check
    checkFormValidity(form);
  });

  // Attach submit listeners to forms
  setupFormHandlers();

  // OTP inputs auto-advance logic
  initOtpAdvancement();
}

/**
 * Verify page access permissions and fill email context fields
 */
function verifyPageAccess() {
  const path = window.location.pathname;

  if (path.includes('verify-email.html')) {
    const email = sessionStorage.getItem('verify_email');
    if (!email) {
      const container = document.querySelector('.card-glass');
      if (container) {
        container.innerHTML = `
          <h3 style="font-weight: 700; text-align: center; margin-bottom: 1rem; color: var(--color-danger);">No Pending Verification</h3>
          <p style="text-align: center; font-size: var(--fs-sm); margin-bottom: 2rem; color: var(--text-secondary);">No pending email verification was found. Please register a new account or log in if already verified.</p>
          <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 2rem;">
            <a href="signup.html" class="btn btn-primary" style="text-align: center;">Register Account</a>
            <a href="login.html" class="btn btn-outline" style="text-align: center; border: 1px solid var(--border-color); padding: 0.85rem; border-radius: var(--radius-md); font-weight: 600; color: var(--text-primary); text-decoration: none;">Back to Log In</a>
          </div>
        `;
      }
      return;
    }
    const textEl = document.getElementById('verifyEmailText');
    if (textEl) textEl.textContent = email;
  }

  if (path.includes('otp.html')) {
    const email = sessionStorage.getItem('reset_email');
    if (!email) {
      const container = document.querySelector('.card-glass');
      if (container) {
        container.innerHTML = `
          <h3 style="font-weight: 700; text-align: center; margin-bottom: 1rem; color: var(--color-danger);">No Pending Reset</h3>
          <p style="text-align: center; font-size: var(--fs-sm); margin-bottom: 2rem; color: var(--text-secondary);">No pending password reset request was found. Please initiate password recovery first.</p>
          <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 2rem;">
            <a href="forgot-password.html" class="btn btn-primary" style="text-align: center;">Forgot Password</a>
            <a href="login.html" class="btn btn-outline" style="text-align: center; border: 1px solid var(--border-color); padding: 0.85rem; border-radius: var(--radius-md); font-weight: 600; color: var(--text-primary); text-decoration: none;">Back to Log In</a>
          </div>
        `;
      }
      return;
    }
    const textEl = document.getElementById('resetEmailText');
    if (textEl) textEl.textContent = email;
  }

  if (path.includes('reset-password.html')) {
    const email = sessionStorage.getItem('reset_email') || new URLSearchParams(window.location.search).get('email');
    const token = sessionStorage.getItem('reset_token') || new URLSearchParams(window.location.search).get('token');
    if (!email && !token) {
      window.location.href = getRoutePath('login');
      return;
    }
  }
}

/**
 * Sets up custom async form handlers connected to PHP backend endpoints
 */
function setupFormHandlers() {
  // 1. Sign Up Form
  const signupForm = document.getElementById('signupForm');
  if (signupForm) {
    signupForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      validateFormInputs(signupForm);
      if (signupForm.dataset.valid !== 'true') {
        showToast("Please fix the validation errors in the form.", "warning", "Validation Failure");
        return;
      }

      const submitBtn = signupForm.querySelector('[type="submit"]');
      const origHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Sending Code... <span style="display:inline-block; animation: spin 1s linear infinite; margin-left: 5px;">&#8635;</span>';

      try {
        const email = document.getElementById('signupEmail').value;
        const response = await fetchApi('/api/auth/register', {
          method: 'POST',
          body: JSON.stringify({
            name: document.getElementById('signupName').value,
            username: document.getElementById('signupUsername').value,
            email: email,
            phone: document.getElementById('signupPhone').value,
            password: document.getElementById('signupPassword').value,
            confirm_password: document.getElementById('signupConfirmPassword').value
          })
        });

        const result = await response.json();
        if (response.ok && result.success) {
          sessionStorage.setItem('verify_email', email);
          // Store OTP for display on verify page (dev/test mode)
          if (result.data && result.data.otp) {
            sessionStorage.setItem('dev_otp', result.data.otp);
          }
          showToast(result.message, 'success', 'Registration Pending');
          setTimeout(() => {
            window.location.href = getRoutePath('verifyOtp');
          }, 1500);
        } else {
          showToast(result.errors ? result.errors.join('<br>') : 'Registration failed', 'danger', 'Registration Error');
        }
      } catch (err) {
        showToast('Network connection failure. Please try again.', 'danger', 'Connection Error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
      }
    });
  }

  // 2. Verify Email OTP Form
  const verifyOtpForm = document.getElementById('verifyOtpForm');
  if (verifyOtpForm) {
    verifyOtpForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const code = document.getElementById('otpCode').value;
      if (code.length !== 6) {
        showToast("Please enter the complete 6-digit OTP code.", "warning", "Invalid Code");
        return;
      }

      const submitBtn = verifyOtpForm.querySelector('[type="submit"]');
      const origHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Verifying... <span style="display:inline-block; animation: spin 1s linear infinite; margin-left: 5px;">&#8635;</span>';

      try {
        const email = sessionStorage.getItem('verify_email');
        const response = await fetchApi('/api/auth/verify-email', {
          method: 'POST',
          body: JSON.stringify({ email, otp: code })
        });

        const result = await response.json();
        if (response.ok && result.success) {
          // Save session state to localStorage
          localStorage.setItem('fintrack_user_name', result.data.full_name);
          localStorage.setItem('fintrack_user_email', result.data.email);
          localStorage.setItem('fintrack_currency', result.data.currency);
          sessionStorage.setItem('csrf_token', result.data.csrf_token);
          
          sessionStorage.removeItem('verify_email');
          sessionStorage.removeItem('dev_otp'); // Clean up dev OTP

          showToast("Email verified successfully! Redirecting...", 'success', 'Verification Success');
          setTimeout(() => {
            window.location.href = getRoutePath('dashboard');
          }, 1500);
        } else {
          showToast(result.errors ? result.errors.join('<br>') : 'Verification failed', 'danger', 'Verification Error');
        }
      } catch (err) {
        showToast('Network connection failure.', 'danger', 'Connection Error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
      }
    });
  }

  // 3. Login Form
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      validateFormInputs(loginForm);
      if (loginForm.dataset.valid !== 'true') return;

      const submitBtn = loginForm.querySelector('[type="submit"]');
      const origHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Logging in... <span style="display:inline-block; animation: spin 1s linear infinite; margin-left: 5px;">&#8635;</span>';

      try {
        const response = await fetchApi('/api/auth/login', {
          method: 'POST',
          body: JSON.stringify({
            email: document.getElementById('loginEmail').value,
            password: document.getElementById('loginPassword').value
          })
        });

        const result = await response.json();
        if (response.ok && result.success) {
          // Store user preferences
          localStorage.setItem('fintrack_user_name', result.data.full_name);
          localStorage.setItem('fintrack_user_email', result.data.email);
          localStorage.setItem('fintrack_currency', result.data.currency);
          sessionStorage.setItem('csrf_token', result.data.csrf_token);

          showToast("Login successful. Welcome back!", 'success', 'Access Granted');
          setTimeout(() => {
            window.location.href = getRoutePath('dashboard');
          }, 1000);
        } else {
          const isUnverified = result.errors && result.errors.includes('email_unverified');
          if (isUnverified) {
            const email = document.getElementById('loginEmail').value;
            sessionStorage.setItem('verify_email', email);
            showToast("Please verify your email address to continue.", 'warning', 'Email Verification Required');
            setTimeout(() => {
              window.location.href = getRoutePath('verifyOtp');
            }, 1500);
          } else {
            const errMessage = result.errors ? result.errors[0] : 'Invalid email or password.';
            showToast(errMessage, 'danger', 'Authentication Failed');
          }
        }
      } catch (err) {
        showToast('Network connection failure.', 'danger', 'Connection Error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
      }
    });
  }

  // 4. Forgot Password Recovery Form
  const forgotForm = document.getElementById('forgotForm');
  if (forgotForm) {
    forgotForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      validateFormInputs(forgotForm);
      if (forgotForm.dataset.valid !== 'true') return;

      const submitBtn = forgotForm.querySelector('[type="submit"]');
      const origHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Sending... <span style="display:inline-block; animation: spin 1s linear infinite; margin-left: 5px;">&#8635;</span>';

      try {
        const email = document.getElementById('forgotEmail').value;
        const response = await fetchApi('/api/auth/forgot-password', {
          method: 'POST',
          body: JSON.stringify({ email })
        });

        const result = await response.json();
        if (response.ok && result.success) {
          showToast(result.message, 'success', 'Link Dispatched');
          setTimeout(() => {
            window.location.href = getRoutePath('login');
          }, 1500);
        } else {
          showToast(result.errors ? result.errors.join('<br>') : 'Failed to send reset link', 'danger', 'Error');
        }
      } catch (err) {
        showToast('Network connection failure.', 'danger', 'Connection Error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
      }
    });
  }

  // Mock Reset OTP flow removed. Users click high-entropy reset links directly from emails.

  // 6. Save Reset Password Form
  const resetForm = document.getElementById('resetForm');
  if (resetForm) {
    resetForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      validateFormInputs(resetForm);
      if (resetForm.dataset.valid !== 'true') {
        showToast("Ensure both passwords match and strength requirements are met.", "warning", "Form Invalid");
        return;
      }

      const submitBtn = resetForm.querySelector('[type="submit"]');
      const origHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving... <span style="display:inline-block; animation: spin 1s linear infinite; margin-left: 5px;">&#8635;</span>';

      try {
        const email = sessionStorage.getItem('reset_email') || new URLSearchParams(window.location.search).get('email') || '';
        const token = sessionStorage.getItem('reset_token') || new URLSearchParams(window.location.search).get('token') || '';
        const password = document.getElementById('resetPassword').value;
        const confirm_password = document.getElementById('resetConfirmPassword').value;
        const response = await fetchApi('/api/auth/reset-password', {
          method: 'POST',
          body: JSON.stringify({ email, token, password, confirm_password })
        });

        const result = await response.json();
        if (response.ok && result.success) {
          sessionStorage.removeItem('reset_email');
          sessionStorage.removeItem('reset_token');
          showToast("Password updated successfully. Please log in.", 'success', 'Password Reset');
          setTimeout(() => {
            window.location.href = getRoutePath('login');
          }, 1500);
        } else {
          showToast(result.errors ? result.errors.join('<br>') : 'Reset password failed', 'danger', 'Error');
        }
      } catch (err) {
        showToast('Network connection failure.', 'danger', 'Connection Error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
      }
    });
  }
}

function validateInput(input, form) {
  const group = input.closest('.form-group');
  if (!group) return;

  let isValid = true;
  let errorMsg = '';

  // Check email
  if (input.type === 'email') {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(input.value)) {
      isValid = false;
      errorMsg = 'Please enter a valid email address';
    }
  }

  // Check required
  if (input.required && !input.value.trim()) {
    isValid = false;
    errorMsg = 'This field is required';
  }

  // Handle CSS invalid states
  if (!isValid) {
    group.classList.add('is-invalid');
    group.classList.remove('is-valid');
    const errDiv = group.querySelector('.error-message');
    if (errDiv) errDiv.textContent = errorMsg;
  } else {
    group.classList.remove('is-invalid');
    group.classList.add('is-valid');
  }

  checkFormValidity(form);
}

function checkFormValidity(form) {
  const groups = form.querySelectorAll('.form-group');
  let hasErrors = false;

  groups.forEach(group => {
    if (group.classList.contains('is-invalid')) {
      hasErrors = true;
    }
  });

  // Verify required inputs are not empty
  const requiredInputs = form.querySelectorAll('input[required]');
  requiredInputs.forEach(input => {
    if (!input.value.trim()) {
      hasErrors = true;
    }
  });

  // Verify checkbox for terms is checked
  const termsCheckbox = form.querySelector('.terms-checkbox');
  if (termsCheckbox && !termsCheckbox.checked) {
    hasErrors = true;
  }

  form.dataset.valid = (!hasErrors).toString();
}

function evaluatePasswordStrength(input, form) {
  const value = input.value;
  const strengthMeter = form.querySelector('.strength-meter-bar');
  const strengthText = form.querySelector('.strength-meter-text');
  
  if (!strengthMeter || !strengthText) return;

  if (!value) {
    strengthMeter.style.width = '0%';
    strengthMeter.className = 'strength-meter-bar';
    strengthText.textContent = '';
    return;
  }

  let score = 0;
  
  // Rule checks
  const checks = {
    length: value.length >= 8,
    hasUpper: /[A-Z]/.test(value),
    hasLower: /[a-z]/.test(value),
    hasDigit: /[0-9]/.test(value),
    hasSpecial: /[^A-Za-z0-9]/.test(value)
  };

  // Update checkmarks checklist UI if present
  Object.keys(checks).forEach(key => {
    const indicator = form.querySelector(`[data-rule="${key}"]`);
    if (indicator) {
      if (checks[key]) {
        indicator.classList.add('active');
        indicator.querySelector('i')?.setAttribute('data-lucide', 'check-circle');
      } else {
        indicator.classList.remove('active');
        indicator.querySelector('i')?.setAttribute('data-lucide', 'circle');
      }
    }
  });
  if (window.lucide) window.lucide.createIcons();

  // Score evaluation
  if (checks.length) score++;
  if (checks.hasUpper && checks.hasLower) score++;
  if (checks.hasDigit) score++;
  if (checks.hasSpecial) score++;

  // Update strength bar color and size
  let pct = '25%';
  let barClass = 'weak';
  let text = 'Weak';

  if (score === 2) {
    pct = '50%';
    barClass = 'fair';
    text = 'Fair';
  } else if (score === 3) {
    pct = '75%';
    barClass = 'medium';
    text = 'Good';
  } else if (score === 4) {
    pct = '100%';
    barClass = 'strong';
    text = 'Strong & Secure';
  }

  strengthMeter.style.width = pct;
  strengthMeter.className = `strength-meter-bar ${barClass}`;
  strengthText.textContent = text;
  strengthText.className = `strength-meter-text ${barClass}`;

  // If password is too weak, set form invalid state
  const group = input.closest('.form-group');
  if (value.length < 8) {
    group.classList.add('is-invalid');
    group.classList.remove('is-valid');
  } else {
    group.classList.remove('is-invalid');
    group.classList.add('is-valid');
  }
  
  checkFormValidity(form);
}

function validatePasswordMatch(pass, confirmPass, form) {
  const group = confirmPass.closest('.form-group');
  if (!group) return;

  if (pass.value !== confirmPass.value) {
    group.classList.add('is-invalid');
    group.classList.remove('is-valid');
    const err = group.querySelector('.error-message');
    if (err) err.textContent = 'Passwords do not match';
  } else {
    group.classList.remove('is-invalid');
    group.classList.add('is-valid');
  }

  checkFormValidity(form);
}

function initOtpAdvancement() {
  const otpInputs = document.querySelectorAll('.otp-digit');
  if (otpInputs.length === 0) return;

  const timerEl = document.getElementById('otpTimer');
  const resendBtn = document.getElementById('otpResendBtn');
  let countdownInterval;
  let timeRemaining = 300; // 5 minutes

  function startTimer() {
    timeRemaining = 300;
    if (resendBtn) {
      resendBtn.classList.remove('active');
      resendBtn.disabled = true;
    }
    if (timerEl) {
      timerEl.style.color = 'var(--color-danger)';
    }

    clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
      timeRemaining--;
      if (timeRemaining <= 0) {
        clearInterval(countdownInterval);
        if (timerEl) {
          timerEl.textContent = 'Expired';
          timerEl.style.color = 'var(--text-muted)';
        }
        if (resendBtn) {
          resendBtn.classList.add('active');
          resendBtn.disabled = false;
        }
      } else {
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        if (timerEl) {
          timerEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
      }
    }, 1000);
  }

  // Start timer
  startTimer();

  // Reset/Resend Event Handler
  if (resendBtn) {
    resendBtn.addEventListener('click', async () => {
      const isEmailVerification = window.location.pathname.includes('verify-email.html');
      
      try {
        if (isEmailVerification) {
          const email = sessionStorage.getItem('verify_email');
          const response = await fetchApi('/api/auth/resend-verification', {
            method: 'POST',
            body: JSON.stringify({ email })
          });
          const result = await response.json();
          if (response.ok && result.success) {
            showToast(result.message, 'success', 'Code Dispatched');
          } else {
            showToast(result.errors ? result.errors[0] : 'Failed to resend code', 'danger', 'Error');
            return;
          }
        } else {
          // Reset password flow
          const email = sessionStorage.getItem('reset_email');
          const response = await fetchApi('/api/auth/forgot-password', {
            method: 'POST',
            body: JSON.stringify({ email })
          });
          const result = await response.json();
          if (response.ok && result.success) {
            showToast('A new 6-digit recovery code has been sent to your email.', 'success', 'Code Dispatched');
          } else {
            showToast(result.errors ? result.errors[0] : 'Failed to resend code', 'danger', 'Error');
            return;
          }
        }

        // Clear fields
        otpInputs.forEach(input => input.value = '');
        const hiddenOtpInput = document.getElementById('otpCode');
        if (hiddenOtpInput) hiddenOtpInput.value = '';
        
        // Select first field
        otpInputs[0].focus();

        // Restart timer
        startTimer();
      } catch (err) {
        showToast('Connection error during code resend.', 'danger', 'Connection Error');
      }
    });
  }

  otpInputs.forEach((input, index) => {
    input.addEventListener('input', (e) => {
      // Allow only numbers
      input.value = input.value.replace(/[^0-9]/g, '');
      
      if (input.value.length === 1 && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }
      
      combineOtpValues();
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !input.value && index > 0) {
        otpInputs[index - 1].focus();
      } else if (e.key === 'ArrowLeft' && index > 0) {
        otpInputs[index - 1].focus();
      } else if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }
    });

    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const pastedData = (e.clipboardData || window.clipboardData).getData('text');
      const digits = pastedData.replace(/[^0-9]/g, '').substring(0, 6);
      
      for (let i = 0; i < digits.length; i++) {
        if (otpInputs[i]) {
          otpInputs[i].value = digits[i];
        }
      }
      combineOtpValues();
      
      const focusIndex = Math.min(digits.length, otpInputs.length - 1);
      if (otpInputs[focusIndex]) {
        otpInputs[focusIndex].focus();
      }
    });
  });

  function combineOtpValues() {
    const hiddenOtpInput = document.getElementById('otpCode');
    if (!hiddenOtpInput) return;

    let code = '';
    otpInputs.forEach(input => {
      code += input.value;
    });

    hiddenOtpInput.value = code;
    
    // Set custom validation state on the parent form
    const form = hiddenOtpInput.closest('form');
    if (form) {
      if (code.length === 6) {
        form.dataset.valid = 'true';
      } else {
        form.dataset.valid = 'false';
      }
    }
  }
}

/**
 * Programmatically triggers validations on all inputs within a form
 * @param {HTMLFormElement} form 
 */
export function validateFormInputs(form) {
  const inputs = form.querySelectorAll('input, select, textarea');
  inputs.forEach(input => {
    validateInput(input, form);
  });
  checkFormValidity(form);
}
