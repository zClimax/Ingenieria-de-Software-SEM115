/**
 * Script para la página de revisión de solicitudes
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('jefe_ver.js cargado');

    // Referencias a elementos
    const decisionForm = document.querySelector('.decision-form');
    const comentarioTextarea = document.querySelector('.form-textarea');
    const btnAprobar = document.querySelector('button[value="APROBADA"]');
    const btnRechazar = document.querySelector('button[value="RECHAZADA"]');

    // Validación del formulario de decisión
    if (decisionForm) {
        decisionForm.addEventListener('submit', function(e) {
            const decision = e.submitter.value;
            const comentario = comentarioTextarea ? comentarioTextarea.value.trim() : '';

            // Confirmación para aprobar
            if (decision === 'APROBADA') {
                if (!confirm('¿Está seguro de aprobar esta solicitud? Esto autorizará la firma digital.')) {
                    e.preventDefault();
                    return false;
                }
            }

            // Confirmación para rechazar
            if (decision === 'RECHAZADA') {
                if (!confirm('¿Está seguro de rechazar esta solicitud?')) {
                    e.preventDefault();
                    return false;
                }

                // Opcional: requerir comentario al rechazar
                if (comentario === '') {
                    const requireComment = confirm('No ha ingresado un comentario. ¿Desea continuar sin comentario?');
                    if (!requireComment) {
                        e.preventDefault();
                        comentarioTextarea.focus();
                        return false;
                    }
                }
            }

            // Mostrar loading
            showLoading(e.submitter);
        });
    }

    // Función para mostrar estado de carga en botones
    function showLoading(button) {
        if (!button) return;

        const originalText = button.innerHTML;
        button.disabled = true;
        button.style.opacity = '0.6';
        button.style.cursor = 'not-allowed';
        
        const icon = button.querySelector('i');
        if (icon) {
            icon.className = 'bx bx-loader-alt bx-spin';
        }
        
        const textSpan = button.querySelector('span');
        if (textSpan) {
            textSpan.textContent = 'Procesando...';
        }
    }

    // Auto-resize del textarea
    if (comentarioTextarea) {
        comentarioTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }

    // Botones de descarga con animación
    const downloadButtons = document.querySelectorAll('.btn-download');
    downloadButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.add('bx-burst');
                setTimeout(() => {
                    icon.classList.remove('bx-burst');
                }, 1000);
            }
        });
    });

    // Previsualización de evidencias (si son imágenes)
    const evidenciaItems = document.querySelectorAll('.evidencia-item');
    evidenciaItems.forEach(item => {
        const nombre = item.querySelector('.evidencia-nombre')?.textContent || '';
        const ext = nombre.split('.').pop().toLowerCase();
        
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            item.style.cursor = 'pointer';
            item.addEventListener('click', function(e) {
                if (!e.target.closest('.btn-download')) {
                    // Aquí podrías agregar un modal de previsualización
                    console.log('Preview de imagen:', nombre);
                }
            });
        }
    });

    // Marcar evidencias visitadas
    const evidenciaLinks = document.querySelectorAll('.btn-download');
    evidenciaLinks.forEach(link => {
        link.addEventListener('click', function() {
            const item = this.closest('.evidencia-item');
            if (item) {
                item.style.opacity = '0.7';
                setTimeout(() => {
                    item.style.opacity = '1';
                }, 2000);
            }
        });
    });

    // Animación de entrada para las secciones
    const sections = document.querySelectorAll('.content-section, .decision-section');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeIn 0.4s ease forwards';
            }
        });
    }, observerOptions);

    sections.forEach(section => {
        observer.observe(section);
    });

    // Contador de caracteres para el comentario (opcional)
    if (comentarioTextarea) {
        const maxLength = 500;
        const counter = document.createElement('div');
        counter.style.cssText = 'text-align: right; font-size: 0.85rem; color: #6b7280; margin-top: 4px;';
        comentarioTextarea.parentElement.appendChild(counter);

        function updateCounter() {
            const length = comentarioTextarea.value.length;
            counter.textContent = `${length} / ${maxLength} caracteres`;
            
            if (length > maxLength * 0.9) {
                counter.style.color = '#ef4444';
            } else {
                counter.style.color = '#6b7280';
            }
        }

        comentarioTextarea.addEventListener('input', updateCounter);
        comentarioTextarea.maxLength = maxLength;
        updateCounter();
    }

    console.log('Funcionalidades de jefe_ver.js inicializadas');
});