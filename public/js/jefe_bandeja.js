(function() {
  'use strict';

  // =========================================================================
  // ANIMACIONES AL CARGAR
  // =========================================================================
  document.addEventListener('DOMContentLoaded', function() {
    // Fade in de las tarjetas
    const cards = document.querySelectorAll('.correccion-card, .solicitudes-table tbody tr');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      
      setTimeout(() => {
        card.style.transition = 'all 0.4s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, index * 50);
    });
  });

  // =========================================================================
  // FILTROS Y BÚSQUEDA (opcional para futuro)
  // =========================================================================
  const searchInput = document.getElementById('searchSolicitudes');
  if (searchInput) {
    searchInput.addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('.solicitudes-table tbody tr');
      
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
      });
    });
  }

})();