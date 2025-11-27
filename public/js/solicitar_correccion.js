(function() {
    'use strict';

    const APP_DATA = window.APP_DATA || {};

    // ========================================
    // CONTADOR DE CARACTERES
    // ========================================
    const campoMotivo = document.getElementById('motivo');
    const contadorActual = document.getElementById('contadorActual');

    if (campoMotivo && contadorActual) {
        campoMotivo.addEventListener('input', function() {
            const longitud = this.value.length;
            contadorActual.textContent = longitud;

            // Cambiar color si se acerca al límite
            if (longitud > 450) {
                contadorActual.style.color = '#ef4444';
            } else if (longitud > 400) {
                contadorActual.style.color = '#f59e0b';
            } else {
                contadorActual.style.color = '#24268b';
            }
        });
    }

    // ========================================
    // VALIDACIÓN DEL FORMULARIO
    // ========================================
    const formCorreccion = document.getElementById('formCorreccion');

    if (formCorreccion) {
        formCorreccion.addEventListener('submit', function(e) {
            const motivo = campoMotivo.value.trim();

            // Validar longitud mínima
            if (motivo.length < 10) {
                e.preventDefault();
                alert('El motivo debe tener al menos 10 caracteres.');
                campoMotivo.focus();
                return false;
            }

            // Confirmar envío
            if (!confirm('¿Estás seguro de solicitar esta corrección?\n\nEl documento volverá a estado "En edición".')) {
                e.preventDefault();
                return false;
            }

            // Deshabilitar botón para evitar doble envío
            const btnSubmit = formCorreccion.querySelector('button[type="submit"]');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Enviando...';
        });
    }

    // ========================================
    // AUTO-FOCUS EN EL TEXTAREA
    // ========================================
    if (campoMotivo) {
        campoMotivo.focus();
    }

    // ========================================
    // PREVENIR SALIDA ACCIDENTAL
    // ========================================
    let formularioModificado = false;

    if (campoMotivo) {
        campoMotivo.addEventListener('input', function() {
            formularioModificado = this.value.trim().length > 0;
        });
    }

    window.addEventListener('beforeunload', function(e) {
        if (formularioModificado && formCorreccion && !formCorreccion.classList.contains('enviado')) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });

    // Marcar como enviado cuando se envía el formulario
    if (formCorreccion) {
        formCorreccion.addEventListener('submit', function() {
            this.classList.add('enviado');
            formularioModificado = false;
        });
    }

})();