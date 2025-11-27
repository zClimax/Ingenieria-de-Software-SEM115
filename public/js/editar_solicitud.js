document.addEventListener('DOMContentLoaded', () => {
    // Referencia al contenedor de la lista de evidencias
    const listaEvidencias = document.querySelector('.lista-evidencias');

    if (listaEvidencias) {
        listaEvidencias.addEventListener('click', (e) => {
            // Buscamos si el clic fue dentro de un botón eliminar
            const btn = e.target.closest('.boton-eliminar');
            
            // Si no es el botón eliminar, ignoramos
            if (!btn) return;

            // Prevenimos cualquier acción por defecto
            e.preventDefault();

            const id = btn.dataset.id;
            if (!id) return;

            if (!confirm('¿Estás seguro de que deseas eliminar esta evidencia permanentemente?')) {
                return;
            }

            // Preparamos los datos para enviar
            const formData = new FormData();
            formData.append('id', id);

            // Efecto visual de carga (opcional)
            const itemEvidencia = btn.closest('.item-evidencia');
            itemEvidencia.style.opacity = '0.5';

            // Petición AJAX (Fetch)
            fetch('?action=sol_evid_del', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    // Éxito: Eliminamos el elemento del DOM con una pequeña animación
                    itemEvidencia.style.transition = 'opacity 0.3s, transform 0.3s';
                    itemEvidencia.style.transform = 'translateX(20px)';
                    itemEvidencia.style.opacity = '0';
                    
                    setTimeout(() => {
                        itemEvidencia.remove();
                        
                        // Si ya no quedan evidencias, mostramos el mensaje de vacío
                        if (document.querySelectorAll('.item-evidencia').length === 0) {
                            listaEvidencias.innerHTML = `
                                <div class="contenido-vacio">
                                    <i class='bx bx-folder-open'></i>
                                    <p>No hay evidencias cargadas aún.</p>
                                    <small>Sube al menos una evidencia antes de enviar a validación.</small>
                                </div>
                            `;
                        }
                    }, 300);
                    
                } else {
                    // Error del servidor
                    alert('Error: ' + data.msg);
                    itemEvidencia.style.opacity = '1'; // Restaurar opacidad
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error de conexión al intentar eliminar.');
                itemEvidencia.style.opacity = '1';
            });
        });
    }
});