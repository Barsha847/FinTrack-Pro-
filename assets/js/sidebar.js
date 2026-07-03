/**
 * FinTrack Pro - Sidebar Management Module
 */

const SIDEBAR_STATE_KEY = 'fintrack_sidebar_collapsed';

export function initSidebar() {
  initializeSidebar();
}

function initializeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const layout = document.querySelector('.dashboard-layout');
  
  if (!sidebar) {
    console.warn("Sidebar element not found on this page.");
    return;
  }

  const toggleBtn = document.getElementById('sidebarToggle');
  const mobileToggleBtn = document.getElementById('mobileSidebarToggle');
  
  // Set up mobile backdrop overlay dynamically
  let backdrop = document.querySelector('.sidebar-backdrop');
  if (!backdrop) {
    backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);
  }

  // Load and apply initial state
  const isCollapsed = loadSidebarState();
  if (isCollapsed && window.innerWidth > 1024) {
    collapseSidebar(sidebar, layout);
  } else {
    expandSidebar(sidebar, layout);
  }

  // Toggle handlers
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      toggleSidebar(sidebar, layout);
    });
  }

  if (mobileToggleBtn) {
    mobileToggleBtn.addEventListener('click', () => {
      sidebar.classList.add('mobile-open');
      backdrop.classList.add('show');
      console.warn("Mobile sidebar drawer opened");
    });
  }

  backdrop.addEventListener('click', () => {
    sidebar.classList.remove('mobile-open');
    backdrop.classList.remove('show');
    console.warn("Mobile sidebar drawer closed");
  });

  // Close when tapping menu items on mobile
  const menuItems = sidebar.querySelectorAll('.sidebar-menu-item');
  menuItems.forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth <= 1024) {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('show');
      }
    });

    // Keyboard accessibility
    item.setAttribute('tabindex', '0');
    item.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        item.click();
      }
    });
  });

  // Handle window resizing
  window.addEventListener('resize', () => {
    if (window.innerWidth > 1024) {
      sidebar.classList.remove('mobile-open');
      backdrop.classList.remove('show');
      
      const shouldCollapse = loadSidebarState();
      if (shouldCollapse) {
        collapseSidebar(sidebar, layout);
      } else {
        expandSidebar(sidebar, layout);
      }
    } else {
      // Clean up desktop layout variables on mobile
      sidebar.classList.remove('collapsed');
      if (layout) layout.classList.remove('sidebar-collapsed');
    }
  });
}

function toggleSidebar(sidebar, layout) {
  const isCollapsed = sidebar.classList.contains('collapsed');
  if (isCollapsed) {
    expandSidebar(sidebar, layout);
  } else {
    collapseSidebar(sidebar, layout);
  }
}

function expandSidebar(sidebar, layout) {
  sidebar.classList.remove('collapsed');
  if (layout) {
    layout.classList.remove('sidebar-collapsed');
  }
  saveSidebarState(false);
}

function collapseSidebar(sidebar, layout) {
  sidebar.classList.add('collapsed');
  if (layout) {
    layout.classList.add('sidebar-collapsed');
  }
  saveSidebarState(true);
}

function saveSidebarState(isCollapsed) {
  localStorage.setItem(SIDEBAR_STATE_KEY, isCollapsed ? 'true' : 'false');
  console.warn("Sidebar state saved:", isCollapsed ? 'collapsed' : 'expanded');
}

function loadSidebarState() {
  const state = localStorage.getItem(SIDEBAR_STATE_KEY);
  
  // Validation for invalid local storage values
  if (state !== null && state !== 'true' && state !== 'false') {
    console.warn("Invalid sidebar state in LocalStorage. Resetting to expanded.");
    localStorage.setItem(SIDEBAR_STATE_KEY, 'false');
    return false;
  }
  
  const isCollapsed = state === 'true';
  console.warn("Sidebar state restored:", isCollapsed ? 'collapsed' : 'expanded');
  return isCollapsed;
}
