/**
 * SIGED - Login Subdirección Académica
 * JavaScript adaptado para la estructura HTML existente
 */

// Variables globales
let loginForm = null;
let submitButton = null;
let isSubmitting = false;

// Inicialización cuando carga la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando login de Subdirección Académica...');
    
    // Obtener elementos del DOM existentes
    loginForm = document.querySelector('form');
    submitButton = document.querySelector('.button-azul-completo');
    
    if (loginForm && submitButton) {
        configurarFormulario();
        configurarValidaciones();
        configurarEfectosVisuales();
    }
    
    // Configurar botón volver
    configurarBotonVolver();
    
    // Mostrar mensaje de bienvenida con animación
    mostrarMensajeBienvenida();
    
    // Configurar efectos de partículas (opcional)
    crearEfectoParticulas();
    
    // Animación del título
    animarTitulo();
    
    console.log('Login de Subdirección Académica inicializado correctamente');
});

/**
 * Configurar eventos del formulario
 */
function configurarFormulario() {
    // Prevenir envío múltiple
    loginForm.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        
        // Validar campos antes del envío
        if (!validarFormulario()) {
            e.preventDefault();
            return false;
        }
        
        // Mostrar estado de carga
        mostrarEstadoCarga();
        isSubmitting = true;
        
        // Permitir envío normal del formulario
        return true;
    });
    
    // Eventos de los campos de entrada
    const inputs = loginForm.querySelectorAll('input');
    inputs.forEach(input => {
        // Limpiar mensajes de error al escribir
        input.addEventListener('input', function() {
            limpiarErrores();
            validarCampoEnTiempoReal(this);
        });
        
        // Efecto visual al enfocar
        input.addEventListener('focus', function() {
            this.closest('.labels-formulario').classList.add('focused');
            this.style.transform = 'scale(1.02)';
        });
        
        // Quitar efecto al desenfocar
        input.addEventListener('blur', function() {
            this.closest('.labels-formulario').classList.remove('focused');
            this.style.transform = 'scale(1)';
            
            if (this.value.trim() === '') {
                this.closest('.labels-formulario').classList.remove('filled');
            } else {
                this.closest('.labels-formulario').classList.add('filled');
            }
        });
        
        // Efectos de hover
        input.addEventListener('mouseenter', function() {
            if (document.activeElement !== this) {
                this.style.borderColor = '#4a90e2';
            }
        });
        
        input.addEventListener('mouseleave', function() {
            if (document.activeElement !== this) {
                this.style.borderColor = '';
            }
        });
    });
}

/**
 * Configurar validaciones del formulario
 */
function configurarValidaciones() {
    // Validación en tiempo real
    const usuarioInput = document.querySelector('input[name="usuario"]');
    const contrasenaInput = document.querySelector('input[name="contrasena"]');
    
    if (usuarioInput) {
        usuarioInput.addEventListener('input', function() {
            if (this.value.length >= 3) {
                validarUsuario(this.value);
            }
        });
    }
    
    if (contrasenaInput) {
        contrasenaInput.addEventListener('input', function() {
            if (this.value.length >= 4) {
                validarContrasena(this.value);
            }
        });
    }
}

/**
 * Configurar efectos visuales adicionales
 */
function configurarEfectosVisuales() {
    // Efectos para el botón principal
    if (submitButton) {
        submitButton.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
            this.style.boxShadow = '0 8px 25px rgba(74, 144, 226, 0.4)';
        });
        
        submitButton.addEventListener('mouseleave', function() {
            if (!isSubmitting) {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '';
            }
        });
        
        // Efecto de ripple al hacer click
        submitButton.addEventListener('click', function(e) {
            crearEfectoRipple(e, this);
        });
    }
    
    // Efectos para el botón volver
    const btnVolver = document.querySelector('.btn-volver');
    if (btnVolver) {
        btnVolver.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(-5px)';
            this.style.boxShadow = '0 4px 15px rgba(108, 117, 125, 0.3)';
        });
        
        btnVolver.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
            this.style.boxShadow = '';
        });
    }
    
    // Animación de entrada para el formulario
    const formulario = document.querySelector('.Formularion-login');
    if (formulario) {
        formulario.style.opacity = '0';
        formulario.style.transform = 'translateY(50px) scale(0.95)';
        
        setTimeout(() => {
            formulario.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            formulario.style.opacity = '1';
            formulario.style.transform = 'translateY(0) scale(1)';
        }, 200);
    }
}

/**
 * Configurar botón volver
 */
function configurarBotonVolver() {
    const btnVolver = document.querySelector('.btn-volver');
    if (btnVolver) {
        btnVolver.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Animación de salida
            this.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                window.location.href = '/SIGED/public/index.php';
            }, 150);
        });
    }
}

/**
 * Crear efecto ripple en botones
 */
function crearEfectoRipple(event, elemento) {
    const rect = elemento.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    const ripple = document.createElement('span');
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
        z-index: 1;
    `;
    
    elemento.style.position = 'relative';
    elemento.style.overflow = 'hidden';
    elemento.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Validar formulario completo
 */
function validarFormulario() {
    const usuario = document.querySelector('input[name="usuario"]').value.trim();
    const contrasena = document.querySelector('input[name="contrasena"]').value;
    
    let esValido = true;
    
    // Validar usuario
    if (!validarUsuario(usuario)) {
        esValido = false;
    }
    
    // Validar contraseña
    if (!validarContrasena(contrasena)) {
        esValido = false;
    }
    
    return esValido;
}

/**
 * Validar campo usuario
 */
function validarUsuario(valor) {
    const campo = document.querySelector('input[name="usuario"]');
    
    if (valor.length < 3) {
        mostrarErrorCampo(campo, 'El usuario debe tener al menos 3 caracteres');
        return false;
    }
    
    if (!/^[a-zA-Z0-9._-]+$/.test(valor)) {
        mostrarErrorCampo(campo, 'Usuario inválido');
        return false;
    }
    
    limpiarErrorCampo(campo);
    return true;
}

/**
 * Validar campo contraseña
 */
function validarContrasena(valor) {
    const campo = document.querySelector('input[name="contrasena"]');
    
    if (valor.length < 4) {
        mostrarErrorCampo(campo, 'Contraseña muy corta');
        return false;
    }
    
    limpiarErrorCampo(campo);
    return true;
}

/**
 * Validar campo en tiempo real
 */
function validarCampoEnTiempoReal(campo) {
    const valor = campo.value.trim();
    
    switch (campo.name) {
        case 'usuario':
            if (valor.length >= 3) {
                validarUsuario(valor);
            }
            break;
        case 'contrasena':
            if (valor.length >= 4) {
                validarContrasena(valor);
            }
            break;
    }
}

/**
 * Mostrar error en un campo específico
 */
function mostrarErrorCampo(campo, mensaje) {
    limpiarErrorCampo(campo);
    
    // Cambiar estilo del campo
    campo.style.borderColor = '#e74c3c';
    campo.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
    campo.style.background = '#fdf2f2';
    
    // Crear elemento de error
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error-message';
    errorElement.innerHTML = `
        <i class='bx bx-error-circle' style="color: #e74c3c; margin-right: 5px;"></i>
        <span>${mensaje}</span>
    `;
    errorElement.style.cssText = `
        color: #e74c3c;
        font-size: 0.8rem;
        margin-top: 5px;
        display: flex;
        align-items: center;
        animation: slideDown 0.3s ease-out;
        font-weight: 500;
    `;
    
    // Insertar después del contenedor del campo
    const container = campo.closest('.labels-formulario');
    container.appendChild(errorElement);
    
    // Efecto de vibración en el campo
    campo.style.animation = 'shake 0.5s ease-in-out';
    setTimeout(() => {
        campo.style.animation = '';
    }, 500);
}

/**
 * Limpiar error de un campo específico
 */
function limpiarErrorCampo(campo) {
    const container = campo.closest('.labels-formulario');
    const errorElement = container.querySelector('.field-error-message');
    
    if (errorElement) {
        errorElement.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => {
            errorElement.remove();
        }, 300);
    }
    
    // Restaurar estilo del campo
    campo.style.borderColor = '';
    campo.style.boxShadow = '';
    campo.style.background = '';
}

/**
 * Limpiar todos los errores
 */
function limpiarErrores() {
    const errores = document.querySelectorAll('.field-error-message');
    errores.forEach(error => {
        error.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => {
            error.remove();
        }, 300);
    });
    
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.style.borderColor = '';
        input.style.boxShadow = '';
        input.style.background = '';
    });
}

/**
 * Mostrar estado de carga en el botón
 */
function mostrarEstadoCarga() {
    if (!submitButton) return;
    
    submitButton.disabled = true;
    submitButton.style.pointerEvents = 'none';
    submitButton.style.opacity = '0.8';
    
    const iconoOriginal = submitButton.querySelector('i');
    if (iconoOriginal) {
        iconoOriginal.className = 'bx bx-loader-alt';
        iconoOriginal.style.animation = 'spin 1s linear infinite';
    }
    
    const textoOriginal = submitButton.textContent.trim();
    submitButton.innerHTML = `
        <i class='bx bx-loader-alt' style="animation: spin 1s linear infinite; margin-right: 8px;"></i>
        Autenticando...
    `;
    
    // Restaurar después de 10 segundos como fallback
    setTimeout(() => {
        if (submitButton.disabled) {
            submitButton.disabled = false;
            submitButton.style.pointerEvents = '';
            submitButton.style.opacity = '';
            submitButton.innerHTML = `<i class='bx bx-log-in-circle'></i> ${textoOriginal}`;
            isSubmitting = false;
        }
    }, 10000);
}

/**
 * Mostrar mensaje de bienvenida
 */
function mostrarMensajeBienvenida() {
    const bienvenida = document.createElement('div');
    bienvenida.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        animation: slideInRight 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    `;
    
    bienvenida.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class='bx bx-shield-alt-2' style="font-size: 1.3rem; color: #74b9ff;"></i>
            <div>
                <div style="font-weight: 700; font-size: 0.9rem;">Subdirección Académica</div>
                <div style="font-size: 0.75rem; opacity: 0.8;">Acceso Administrativo</div>
            </div>
        </div>
    `;
    
    document.body.appendChild(bienvenida);
    
    // Remover después de 5 segundos
    setTimeout(() => {
        bienvenida.style.animation = 'slideOutRight 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards';
        setTimeout(() => {
            if (document.body.contains(bienvenida)) {
                document.body.removeChild(bienvenida);
            }
        }, 600);
    }, 5000);
}

/**
 * Animar título con efecto de escritura
 */
function animarTitulo() {
    const titulo = document.querySelector('.TITULO-SIGED-GRANDE-AZUL');
    if (!titulo) return;
    
    const textoOriginal = titulo.textContent;
    titulo.textContent = '';
    titulo.style.borderRight = '2px solid #4a90e2';
    
    let i = 0;
    const intervalo = setInterval(() => {
        if (i < textoOriginal.length) {
            titulo.textContent += textoOriginal.charAt(i);
            i++;
        } else {
            clearInterval(intervalo);
            // Remover cursor después de 1 segundo
            setTimeout(() => {
                titulo.style.borderRight = 'none';
            }, 1000);
        }
    }, 100);
}

/**
 * Crear efecto de partículas de fondo (opcional)
 */
function crearEfectoParticulas() {
    // Solo crear si el dispositivo puede manejarlas
    if (window.innerWidth < 768 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    
    const canvas = document.createElement('canvas');
    canvas.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: -1;
        opacity: 0.15;
    `;
    
    document.body.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    const particulas = [];
    const numParticulas = 25;
    
    // Crear partículas
    for (let i = 0; i < numParticulas; i++) {
        particulas.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: (Math.random() - 0.5) * 0.3,
            vy: (Math.random() - 0.5) * 0.3,
            size: Math.random() * 1.5 + 0.5,
            opacity: Math.random() * 0.3 + 0.1
        });
    }
    
    // Animar partículas
    function animarParticulas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        particulas.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;
            
            if (p.x < 0 || p.x > canvas.width) p.vx *= -1;
            if (p.y < 0 || p.y > canvas.height) p.vy *= -1;
            
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${p.opacity})`;
            ctx.fill();
        });
        
        requestAnimationFrame(animarParticulas);
    }
    
    animarParticulas();
    
    window.addEventListener('resize', () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    });
}

// Inyectar estilos adicionales para animaciones
const estilosAdicionales = document.createElement('style');
estilosAdicionales.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-10px);
        }
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    
    .labels-formulario.focused input {
        border-color: #4a90e2 !important;
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1) !important;
    }
    
    .labels-formulario.filled label {
        color: #4a90e2;
        font-weight: 600;
    }
    
    /* Estilos adicionales para mejor apariencia */
    .btn-volver {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .button-azul-completo {
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
`;
document.head.appendChild(estilosAdicionales);

// Agregar el script al archivo PHP
console.log('Script de Subdirección Académica cargado');

// Exportar funciones para uso global
window.subdLogin = {
    validarFormulario,
    limpiarErrores,
    mostrarEstadoCarga,
    crearEfectoRipple
};