// Inicializa funcionalidad del UI de la página
(function init() {
  const menuToggle = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('contenidoPrincipal') || document.querySelector('.main-content');

  if (menuToggle && sidebar) {
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
  }

  // Links de navegación: sólo los elementos marcados como 'clickable' deben ser interactivos
  const navLinks = document.querySelectorAll('.lista.clickable');
  navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      navLinks.forEach(l => l.classList.remove('active'));
      e.currentTarget.classList.add('active');

      if (window.innerWidth <= 768 && sidebar) {
        sidebar.classList.remove('open');
      }
    });
  });

  // Filtros dinámicos
  const departamentoSelect = document.getElementById('departamento');
  const tipoSelect = document.getElementById('tipo');
  const busquedaInput = document.getElementById('busqueda');

  function filtrarTabla() {
    const filas = document.querySelectorAll('.tabla-actas tbody tr');
    const departamento = (departamentoSelect && departamentoSelect.value || '').toLowerCase();
    const tipo = (tipoSelect && tipoSelect.value || '').toLowerCase();
    const busqueda = (busquedaInput && busquedaInput.value || '').toLowerCase();

    filas.forEach(fila => {
      const textoDepartamento = (fila.cells[4] && fila.cells[4].textContent || '').toLowerCase();
      const textoTipo = (fila.cells[2] && fila.cells[2].textContent || '').toLowerCase();
      const textoNombre = (fila.cells[1] && fila.cells[1].textContent || '').toLowerCase();

      const cumpleDepartamento = !departamento || textoDepartamento.includes(departamento);
      const cumpleTipo = !tipo || textoTipo.includes(tipo);
      const cumpleBusqueda = !busqueda || textoNombre.includes(busqueda) || textoDepartamento.includes(busqueda);

      if (cumpleDepartamento && cumpleTipo && cumpleBusqueda) {
        fila.style.display = '';
      } else {
        fila.style.display = 'none';
      }
    });
  }

  if (departamentoSelect) departamentoSelect.addEventListener('change', filtrarTabla);
  if (tipoSelect) tipoSelect.addEventListener('change', filtrarTabla);
  if (busquedaInput) busquedaInput.addEventListener('input', filtrarTabla);

  // Botones de acción: coincidir con las clases usadas en el HTML
  document.querySelectorAll('.boton-vista').forEach(btn => {
    btn.addEventListener('click', () => {
      alert('Vista previa del documento');
    });
  });

  document.querySelectorAll('.boton-descarga').forEach(btn => {
    btn.addEventListener('click', () => {
      alert('Descargando documento...');
    });
  });

})();