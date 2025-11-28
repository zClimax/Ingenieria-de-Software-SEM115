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
        if (item) item.style.opacity = '0.5';

        fetch('?action=tk_evid_del', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                if (item) {
                    item.style.transition = 'opacity 0.3s, transform 0.3s';
                    item.style.transform = 'translateX(20px)';
                    item.style.opacity = '0';
                    setTimeout(() => {
                        item.remove();
                        if (!document.querySelector('.item-evidencia')) {
                            listaEvidencias.innerHTML = `
                                <div class="no-comments">
                                    <i class='bx bx-folder-open'></i>
                                    <p>No hay evidencias cargadas aún.</p>
                                </div>
                            `;
                        }
                    }, 300);
                }
            } else {
                alert('Error: ' + (data.msg || 'No se pudo eliminar la evidencia.'));
                if (item) item.style.opacity = '1';
            }
        })
        .catch(err => {
            console.error('Error eliminando evidencia:', err);
            alert('Ocurrió un error de conexión al intentar eliminar.');
            if (item) item.style.opacity = '1';
        });
    });
});
