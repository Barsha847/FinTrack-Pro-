/**
 * FinTrack Pro - Landing Page Animation Controller
 * Powered by GSAP & ScrollTrigger
 */

export function initLandingAnimations() {
  if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
    console.warn("GSAP not loaded. Animations disabled.");
    return;
  }

  gsap.registerPlugin(ScrollTrigger);

  // Check for reduced motion preference
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    console.info("Reduced motion enabled, disabling advanced animations.");
    // Show all elements immediately
    gsap.set('.hero-blob, .hero-glass-card, .scroll-text, .feature-3d-card, .journey-node, .showcase-mockup, .cta-aggressive h2, .cta-buttons', { opacity: 1, y: 0, scale: 1 });
    return;
  }

  // Common ease
  const premiumEase = "power4.out";

  // --- 1. HERO ANIMATION (Aggressive Sequence) ---
  const heroTl = gsap.timeline({ defaults: { ease: premiumEase } });
  
  // Stage 1: Blobs
  heroTl.to('.hero-blob', {
    opacity: 0.15,
    duration: 2,
    stagger: 0.2
  }, 0)
  
  // Stage 2: Main Headline
  .fromTo('.hero-headline', 
    { y: 50, opacity: 0, scale: 0.95 },
    { y: 0, opacity: 1, scale: 1, duration: 1.2 },
    "-=1.5"
  )
  
  // Stage 3: Subtext
  .fromTo('.hero-subtext',
    { y: 30, opacity: 0 },
    { y: 0, opacity: 1, duration: 1 },
    "-=1"
  )
  
  // Stage 4: Buttons
  .fromTo('.hero-buttons',
    { y: 20, opacity: 0 },
    { y: 0, opacity: 1, duration: 1 },
    "-=0.8"
  )
  
  // Stage 5: Stats
  .fromTo('.hero-stats-wrapper > div',
    { y: 20, opacity: 0 },
    { y: 0, opacity: 1, stagger: 0.1, duration: 0.8 },
    "-=0.5"
  )

  // Stage 6: Floating glass cards
  .fromTo('.hero-glass-card',
    { y: 100, opacity: 0, rotation: 0, scale: 0.8 },
    { y: 0, opacity: 1, rotation: (i, target) => target.classList.contains('hero-glass-1') ? 5 : -5, scale: 1, duration: 1.5, stagger: 0.2 },
    "-=1.2"
  );

  // --- 2. CONTINUOUS FLOATING SYSTEM ---
  // Background blobs
  gsap.to('.hero-blob.b1', {
    y: 30, x: -10,
    duration: 6,
    repeat: -1,
    yoyo: true,
    ease: "sine.inOut"
  });
  
  gsap.to('.hero-blob.b2', {
    y: -25, x: 15,
    duration: 7,
    repeat: -1,
    yoyo: true,
    ease: "sine.inOut"
  });

  // Floating glass cards
  gsap.to('.hero-glass-1', {
    y: "-=15", x: "+=8", rotation: "+=1",
    duration: 4, repeat: -1, yoyo: true, ease: "sine.inOut"
  });
  
  gsap.to('.hero-glass-2', {
    y: "+=15", x: "-=5", rotation: "-=2",
    duration: 5, repeat: -1, yoyo: true, ease: "sine.inOut"
  });

  // --- 3. MOUSE PARALLAX (Desktop only) ---
  const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
  if (!isTouchDevice) {
    const heroWrapper = document.querySelector('.hero-wrapper');
    if (heroWrapper) {
      heroWrapper.addEventListener('mousemove', (e) => {
        const x = (e.clientX / window.innerWidth - 0.5);
        const y = (e.clientY / window.innerHeight - 0.5);
        
        gsap.to('.hero-blob.b1', { x: x * 40, y: y * 40, duration: 1, ease: "power2.out", overwrite: "auto" });
        gsap.to('.hero-blob.b2', { x: x * -50, y: y * -50, duration: 1, ease: "power2.out", overwrite: "auto" });
        gsap.to('.hero-glass-1', { x: x * 60, y: y * 60, duration: 1, ease: "power2.out", overwrite: "auto" });
        gsap.to('.hero-glass-2', { x: x * -30, y: y * -30, duration: 1, ease: "power2.out", overwrite: "auto" });
      });
      
      // Resume continuous float when mouse leaves
      heroWrapper.addEventListener('mouseleave', () => {
        gsap.to(['.hero-blob.b1', '.hero-blob.b2', '.hero-glass-1', '.hero-glass-2'], { x: 0, y: 0, duration: 1, ease: "power2.out" });
      });
    }
  }

  // --- 4. SCROLL-LINKED HORIZONTAL TYPOGRAPHY ---
  gsap.to('.scroll-text.dir-left', {
    x: "-40%",
    ease: "none",
    scrollTrigger: {
      trigger: '.scroll-text-container',
      start: "top bottom",
      end: "bottom top",
      scrub: 1
    }
  });

  gsap.to('.scroll-text.dir-right', {
    x: "20%",
    ease: "none",
    scrollTrigger: {
      trigger: '.scroll-text-container',
      start: "top bottom",
      end: "bottom top",
      scrub: 1
    }
  });

  // --- 5. AGGRESSIVE STAGGER FOR FEATURES ---
  gsap.fromTo('.feature-3d-card',
    { opacity: 0, y: 100, scale: 0.85, rotationX: 10 },
    {
      opacity: 1, y: 0, scale: 1, rotationX: 0,
      duration: 1,
      stagger: 0.15,
      ease: premiumEase,
      scrollTrigger: {
        trigger: '.feature-grid',
        start: "top 80%",
      }
    }
  );

  // --- 6. 3D CARD INTERACTION ---
  if (!isTouchDevice) {
    document.querySelectorAll('.feature-3d-card').forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        const rotateX = ((y - centerY) / centerY) * -10;
        const rotateY = ((x - centerX) / centerX) * 10;
        
        gsap.to(card, {
          rotateX: rotateX,
          rotateY: rotateY,
          duration: 0.5,
          ease: "power2.out"
        });
      });
      
      card.addEventListener('mouseleave', () => {
        gsap.to(card, {
          rotateX: 0,
          rotateY: 0,
          duration: 0.5,
          ease: "power2.out"
        });
      });
    });
  }

  // --- 7. PRODUCT VISUALIZATION PARALLAX ---
  const showcaseTl = gsap.timeline({
    scrollTrigger: {
      trigger: '.showcase-wrapper',
      start: "top 80%",
    }
  });

  showcaseTl.fromTo('.showcase-mockup',
    { opacity: 0, y: 100, scale: 0.9, rotationX: 15 },
    { opacity: 1, y: 0, scale: 1, rotationX: 0, duration: 1.5, ease: premiumEase }
  );

  // Background parallax inside showcase
  gsap.to('.showcase-bg-elements .circle-1', {
    y: -150,
    ease: "none",
    scrollTrigger: { trigger: '.showcase-wrapper', start: "top bottom", end: "bottom top", scrub: true }
  });
  
  gsap.to('.showcase-bg-elements .circle-2', {
    y: 100,
    ease: "none",
    scrollTrigger: { trigger: '.showcase-wrapper', start: "top bottom", end: "bottom top", scrub: true }
  });

  // --- 8. FINANCIAL JOURNEY ---
  gsap.fromTo('.journey-path-svg line', 
    { strokeDasharray: "0 1000" },
    { strokeDasharray: "1000 0", ease: "none", 
      scrollTrigger: {
        trigger: '.journey-container',
        start: "top 50%",
        end: "bottom 80%",
        scrub: true
      }
    }
  );

  const journeyNodes = document.querySelectorAll('.journey-node');
  journeyNodes.forEach((node, i) => {
    gsap.fromTo(node,
      { opacity: 0, y: 50, scale: 0.9 },
      { opacity: 1, y: 0, scale: 1, duration: 1, ease: premiumEase,
        scrollTrigger: {
          trigger: node,
          start: "top 80%"
        }
      }
    );
  });

  // --- 9. FINAL CTA ---
  const ctaTl = gsap.timeline({
    scrollTrigger: {
      trigger: '.cta-aggressive',
      start: "top 70%"
    }
  });

  ctaTl.fromTo('.cta-aggressive h2',
    { opacity: 0, y: 50, scale: 0.9 },
    { opacity: 1, y: 0, scale: 1, duration: 1, ease: premiumEase }
  ).fromTo('.cta-buttons',
    { opacity: 0, y: 30 },
    { opacity: 1, y: 0, duration: 0.8, ease: premiumEase },
    "-=0.6"
  );
}
