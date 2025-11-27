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