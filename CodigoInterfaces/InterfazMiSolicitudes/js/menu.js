// Elementos del DOM
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

// Toggle del sidebar
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('minimized');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('active');
    }
});

// Cerrar sidebar al hacer clic fuera en móviles
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    }
});

// Links de navegación
const navLinks = document.querySelectorAll('.nav-link');

navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        navLinks.forEach(l => l.classList.remove('active'));
        e.currentTarget.classList.add('active');
        
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
        }
    });
});

// Filtros dinámicos
const departamentoSelect = document.getElementById('departamento');
const tipoSelect = document.getElementById('tipo');
const busquedaInput = document.getElementById('busqueda');

function filtrarTabla() {
    const filas = document.querySelectorAll('.tabla-actas tbody tr');
    const departamento = departamentoSelect.value.toLowerCase();
    const tipo = tipoSelect.value.toLowerCase();
    const busqueda = busquedaInput.value.toLowerCase();
    
    filas.forEach(fila => {
        const textoDepartamento = fila.cells[4].textContent.toLowerCase();
        const textoTipo = fila.cells[2].textContent.toLowerCase();
        const textoNombre = fila.cells[1].textContent.toLowerCase();
        
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

departamentoSelect.addEventListener('change', filtrarTabla);
tipoSelect.addEventListener('change', filtrarTabla);
busquedaInput.addEventListener('input', filtrarTabla);

// Botones de acción
document.querySelectorAll('.btn-vista').forEach(btn => {
    btn.addEventListener('click', () => {
        alert('Vista previa del documento');
    });
});

document.querySelectorAll('.btn-descarga').forEach(btn => {
    btn.addEventListener('click', () => {
        alert('Descargando documento...');
    });
});