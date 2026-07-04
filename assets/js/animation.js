/**
 * FinTrack Pro - Animation, Scroll Reveal, and Counters Module
 */

export function initAnimations() {
  // 1. Handle Preloader Fadeout
  const preloader = document.getElementById('preloader');
  if (preloader) {
    const dismissPreloader = () => {
      setTimeout(() => {
        preloader.classList.add('fade-out');
        // Fully remove from DOM after CSS transition finishes (400ms)
        setTimeout(() => preloader.remove(), 400);
      }, 500); // subtle delay for polished look
    };

    if (document.readyState === 'complete') {
      dismissPreloader();
    } else {
      window.addEventListener('load', dismissPreloader);
    }
  }

  // 2. Scroll Reveal Intersection Observer
  const revealElements = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
  if (revealElements.length > 0) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('active');
          observer.unobserve(entry.target); // Reveal only once
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px' // trigger slightly before entering viewport
    });

    revealElements.forEach(el => revealObserver.observe(el));
  }

  // 3. Stats Counter Animation
  const counters = document.querySelectorAll('.stat-counter');
  if (counters.length > 0) {
    const counterObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(counter => counterObserver.observe(counter));
  }

  // 4. Hero Section Typing Effect
  initTypingEffect();

  // 5. Scroll effect on Navbar (Glass blur density increase)
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 20) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });
  }
}

function animateCounter(el) {
  const target = +el.getAttribute('data-target');
  const duration = 2000; // 2 seconds
  const startTime = performance.now();

  function updateCount(currentTime) {
    const elapsedTime = currentTime - startTime;
    const progress = Math.min(elapsedTime / duration, 1);
    
    // Easing function (easeOutQuad)
    const easeProgress = progress * (2 - progress);
    const currentValue = Math.floor(easeProgress * target);

    // Format output (e.g. currency, commas, percentages)
    const isCurrency = el.classList.contains('counter-currency');
    const isPercent = el.classList.contains('counter-percent');
    
    let formatted = currentValue.toLocaleString();
    if (isCurrency) formatted = '₹' + formatted;
    if (isPercent) formatted = formatted + '%';

    el.textContent = formatted;

    if (progress < 1) {
      requestAnimationFrame(updateCount);
    } else {
      // Set exact target at the end
      let finalFormatted = target.toLocaleString();
      if (isCurrency) finalFormatted = '₹' + finalFormatted;
      if (isPercent) finalFormatted = finalFormatted + '%';
      el.textContent = finalFormatted;
    }
  }

  requestAnimationFrame(updateCount);
}

function initTypingEffect() {
  const typingEl = document.querySelector('.typing-effect');
  if (!typingEl) return;

  const words = JSON.parse(typingEl.getAttribute('data-words') || '[]');
  let wordIndex = 0;
  let charIndex = 0;
  let isDeleting = false;
  let typingSpeed = 100;

  function type() {
    const currentWord = words[wordIndex];
    
    if (isDeleting) {
      typingEl.textContent = currentWord.substring(0, charIndex - 1);
      charIndex--;
      typingSpeed = 50; // delete faster
    } else {
      typingEl.textContent = currentWord.substring(0, charIndex + 1);
      charIndex++;
      typingSpeed = 100; // type normal
    }

    if (!isDeleting && charIndex === currentWord.length) {
      typingSpeed = 2000; // pause at full word
      isDeleting = true;
    } else if (isDeleting && charIndex === 0) {
      isDeleting = false;
      wordIndex = (wordIndex + 1) % words.length;
      typingSpeed = 500; // pause before typing next word
    }

    setTimeout(type, typingSpeed);
  }

  // Start loop after loader is likely gone
  setTimeout(type, 1000);
}
