// Elementos del DOM
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');

// Toggle del sidebar
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('minimized');
    
    // En móviles, mostrar/ocultar completamente
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
        // Remover clase active de todos los links
        navLinks.forEach(l => l.classList.remove('active'));
        
        // Agregar clase active al link clickeado
        e.currentTarget.classList.add('active');
        
        // Cerrar sidebar en móviles después de seleccionar
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
        }
    });
});

// Responsive: ajustar sidebar en cambios de tamaño de ventana
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
    }
});
// En tu archivo menu.js
// En tu archivo menu.js
/* unction cargarFotoUsuario(rutaFoto) {
    const avatarSidebar = document.querySelector('.user-avatar');
    const avatarGrande = document.querySelector('.avatar-large');
    
    if (rutaFoto) {
        avatarSidebar.innerHTML = `<img src="${rutaFoto}" alt="Usuario" class="avatar-img">`;
        avatarGrande.innerHTML = `<img src="${rutaFoto}" alt="Usuario" class="avatar-img">`;
    }
}

// Llamar cuando cargue el usuario
cargarFotoUsuario('img/foto-usuario.jpg');*/