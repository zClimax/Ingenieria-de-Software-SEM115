document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Evita el envío tradicional del formulario
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const mensajeError = document.getElementById('mensajeError');
    
    // Validación básica (ejemplo)
    if (username === '' || password === '') {
        mostrarError('Por favor, completa todos los campos');
        return;
    }
    
    // Simulación de validación (reemplaza con tu lógica real)
    if (username !== 'admin@culiacan.tecnm.mx' || password !== '12345') {
        mostrarError('Usuario o contraseña incorrectos');
        return;
    }
    
    // Si todo está bien
    ocultarError();
    // Redirigir o hacer lo que necesites
    window.location.href = 'dashboard.html';
});

function mostrarError(mensaje) {
    const mensajeError = document.getElementById('mensajeError');
    mensajeError.querySelector('p').textContent = mensaje;
    mensajeError.style.display = 'block';
    
    // Opcional: ocultar después de 5 segundos
    setTimeout(() => {
        ocultarError();
    }, 5000);
}

function ocultarError() {
    const mensajeError = document.getElementById('mensajeError');
    mensajeError.style.display = 'none';
}