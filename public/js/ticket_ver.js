/**
 * Script para la página de detalle de ticket
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('ticket_ver.js cargado');

    // Referencias
    const commentForm = document.querySelector('.comment-form');
    const commentTextarea = document.querySelector('.comment-form textarea');
    const actionsForm = document.querySelector('.actions-form');
    const statusSelect = document.querySelector('select[name="estatus"]');
    const resolutionTextarea = document.querySelector('textarea[name="resolucion"]');

    // Auto-resize del textarea de comentarios
    if (commentTextarea) {
        commentTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Contador de caracteres
        const maxLength = 500;
        const counter = document.createElement('div');
        counter.style.cssText = 'text-align: right; font-size: 0.85rem; color: #6b7280; margin-top: 4px;';
        commentTextarea.parentElement.appendChild(counter);

        function updateCounter() {
            const length = commentTextarea.value.length;
            counter.textContent = `${length} / ${maxLength} caracteres`;
            
            if (length > maxLength * 0.9) {
                counter.style.color = '#ef4444';
            } else {
                counter.style.color = '#6b7280';
            }
        }

        commentTextarea.addEventListener('input', updateCounter);
        commentTextarea.maxLength = maxLength;
        updateCounter();
    }

    // Validación del formulario de comentarios
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            const text = commentTextarea.value.trim();
            
            if (text === '') {
                e.preventDefault();
                alert('Por favor ingrese un comentario');
                commentTextarea.focus();
                return false;
            }

            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Enviando...';
            }
        });
    }

    // Validación del formulario de acciones
    if (actionsForm) {
        actionsForm.addEventListener('submit', function(e) {
            const status = statusSelect.value;
            const resolution = resolutionTextarea ? resolutionTextarea.value.trim() : '';

            // Si se cierra el ticket, pedir confirmación
            if (status === 'CERRADO') {
                if (!confirm('¿Está seguro de cerrar este ticket?')) {
                    e.preventDefault();
                    return false;
                }

                // Opcional: requerir nota de resolución al cerrar
                if (resolution === '') {
                    const requireNote = confirm('No ha ingresado una nota de resolución. ¿Desea continuar sin nota?');
                    if (!requireNote) {
                        e.preventDefault();
                        resolutionTextarea.focus();
                        return false;
                    }
                }
            }

            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Guardando...';
            }
        });
    }

    // Mostrar/ocultar campo de resolución según el estado
    if (statusSelect && resolutionTextarea) {
        statusSelect.addEventListener('change', function() {
            const formGroup = resolutionTextarea.closest('.form-group');
            if (this.value === 'CERRADO') {
                formGroup.style.display = 'flex';
                resolutionTextarea.setAttribute('required', 'required');
            } else {
                resolutionTextarea.removeAttribute('required');
            }
        });

        // Trigger inicial
        statusSelect.dispatchEvent(new Event('change'));
    }

    // Auto-scroll a los comentarios más recientes
    const commentsList = document.querySelector('.comments-list');
    if (commentsList && commentsList.children.length > 0) {
        // Scroll suave al último comentario al cargar
        setTimeout(() => {
            const lastComment = commentsList.lastElementChild;
            if (lastComment) {
                lastComment.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 300);
    }

    // Animación de entrada para comentarios
    const comments = document.querySelectorAll('.comment-item');
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

    comments.forEach(comment => {
        observer.observe(comment);
    });

    // Resaltar comentario si viene desde notificación
    const urlParams = new URLSearchParams(window.location.search);
    const highlightComment = urlParams.get('comment');
    if (highlightComment) {
        const commentToHighlight = document.querySelector(`[data-comment-id="${highlightComment}"]`);
        if (commentToHighlight) {
            commentToHighlight.style.borderLeft = '4px solid #ef4444';
            commentToHighlight.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    console.log('Funcionalidades de ticket_ver.js inicializadas');
});