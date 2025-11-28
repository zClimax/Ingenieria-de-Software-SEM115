(function() {
    'use strict';
    
    const APP_DATA = window.APP_DATA || {};
    
    // ========================================
    // VALIDACIÓN DEL FORMULARIO DE COMENTARIOS
    // ========================================
    const commentForm = document.querySelector('.comment-form');
    
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            const textarea = document.getElementById('texto');
            const texto = textarea.value.trim();
            
            if (!texto) {
                e.preventDefault();
                alert('Por favor escribe un comentario antes de enviar.');
                textarea.focus();
                return false;
            }
            
            if (texto.length < 3) {
                e.preventDefault();
                alert('El comentario debe tener al menos 3 caracteres.');
                textarea.focus();
                return false;
            }
            
            // Deshabilitar botón para evitar doble envío
            const submitBtn = this.querySelector('.btn-submit');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Enviando...';
            }
        });
    }
    
    // ========================================
    // AUTO-RESIZE DEL TEXTAREA
    // ========================================
    const textarea = document.getElementById('texto');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
    
    // ========================================
    // SCROLL SUAVE A COMENTARIOS
    // ========================================
    const commentLinks = document.querySelectorAll('a[href^="#comentarios"]');
    commentLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // ========================================
    // CONFIRMAR ANTES DE SALIR SI HAY TEXTO
    // ========================================
    if (textarea) {
        let originalValue = textarea.value;
        
        window.addEventListener('beforeunload', function(e) {
            if (textarea.value !== originalValue && textarea.value.trim() !== '') {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
        
        // Actualizar valor original después de enviar
        if (commentForm) {
            commentForm.addEventListener('submit', function() {
                originalValue = '';
            });
        }
    }
    
})();

document.addEventListener('DOMContentLoaded', () => {
    const listaEvidencias = document.querySelector('.lista-evidencias');

    if (!listaEvidencias) return;

    listaEvidencias.addEventListener('click', (e) => {
        const btn = e.target.closest('.boton-eliminar');
        if (!btn) return;

        e.preventDefault();

        const id = btn.dataset.id;
        if (!id) return;

        if (!confirm('¿Estás seguro de que deseas eliminar esta evidencia permanentemente?')) {
            return;
        }

        const formData = new FormData();
        formData.append('id', id);

        const item = btn.closest('.item-evidencia');
        if (item) {
            item.style.opacity = '0.5';
        }

        fetch('/SIGED/public/index.php?action=tk_evid_del', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                alert('Error: ' + (data.msg || 'No se pudo eliminar la evidencia'));
                if (item) item.style.opacity = '1';
                return;
            }

            if (item) {
                item.style.transition = 'opacity 0.3s, transform 0.3s';
                item.style.transform = 'translateX(20px)';
                item.style.opacity = '0';

                setTimeout(() => {
                    item.remove();

                    // Si ya no quedan evidencias, mostramos mensaje vacío
                    if (!document.querySelector('.item-evidencia')) {
                        const cont = document.querySelector('.lista-evidencias');
                        if (cont) {
                            cont.innerHTML = `
                                <div class="contenido-vacio">
                                    <i class='bx bx-folder-open'></i>
                                    <p>No hay evidencias cargadas aún.</p>
                                </div>
                            `;
                        }
                    }
                }, 300);
            }
        })
        .catch(err => {
            console.error('Error eliminando evidencia:', err);
            alert('Ocurrió un error de conexión al intentar eliminar la evidencia.');
            if (item) item.style.opacity = '1';
        });
    });
});
