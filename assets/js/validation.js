/**
 * FinTrack Pro - Validation Module (Forms, Password Strength, and OTP inputs)
 */

import { showToast } from './utils.js';

export function initValidation() {
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

  // OTP inputs auto-advance logic
  initOtpAdvancement();
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
  let timeRemaining = 120; // 2 minutes

  function startTimer() {
    timeRemaining = 120;
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
    resendBtn.addEventListener('click', () => {
      showToast('A new 6-digit verification code has been dispatched to your email address.', 'success', 'Code Dispatched');
      
      // Clear fields
      otpInputs.forEach(input => input.value = '');
      const hiddenOtpInput = document.getElementById('otpCode');
      if (hiddenOtpInput) hiddenOtpInput.value = '';
      
      // Select first field
      otpInputs[0].focus();

      // Restart timer
      startTimer();
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
