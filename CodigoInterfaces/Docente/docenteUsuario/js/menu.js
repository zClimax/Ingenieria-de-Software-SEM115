document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');

  if (!menuToggle || !sidebar) return;

  // Toggle sidebar open/close
  menuToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebar.classList.toggle('open');
  });

  // Close sidebar when clicking outside on small screens
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 900) {
      if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    }
  });

  // Keep layout consistent on resize
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) {
      sidebar.classList.remove('open');
    }
  });

  // Optional: allow Esc to close on small screens
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && window.innerWidth <= 900) {
      sidebar.classList.remove('open');
    }
  });
});
