// URL del backend (ajusta según tu configuración)
const API_URL = 'http://localhost:8080/api/validar-requisitos';

// Cargar requisitos al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    cargarRequisitos();
});

// Función para cargar requisitos desde el backend
async function cargarRequisitos() {
    try {
        // Simula obtener el ID del docente (en producción vendrá de la sesión)
        const docenteId = obtenerDocenteId();
        
        // Hacer petición al backend
        const response = await fetch(`${API_URL}?docenteId=${docenteId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                // Agregar token de autenticación si es necesario
                // 'Authorization': 'Bearer ' + token
            }
        });

        if (!response.ok) {
            throw new Error('Error al obtener requisitos');
        }

        const data = await response.json();
        
        // Renderizar requisitos
        renderizarRequisitos(data);
        
    } catch (error) {
        console.error('Error:', error);
        mostrarError('No se pudieron cargar los requisitos. Por favor, intente más tarde.');
    }
}

// Función para renderizar requisitos en la tabla
function renderizarRequisitos(data) {
    const tbody = document.getElementById('tablaRequisitos');
    const mensajeCumplimiento = document.getElementById('mensajeCumplimiento');
    
    // Limpiar tabla
    tbody.innerHTML = '';
    
    // Verificar si cumple todos los requisitos
    const cumpleTodos = data.requisitos.every(req => req.cumple);
    
    // Actualizar mensaje de cumplimiento
    if (cumpleTodos) {
        mensajeCumplimiento.textContent = 'Cumple los requisitos para participar en la convocatoria EDD 2025.';
        mensajeCumplimiento.classList.remove('no-cumple');
    } else {
        mensajeCumplimiento.textContent = 'NO cumple todos los requisitos para participar en la convocatoria EDD 2025.';
        mensajeCumplimiento.classList.add('no-cumple');
    }
    
    // Renderizar cada requisito
    data.requisitos.forEach(requisito => {
        const tr = document.createElement('tr');
        
        const tdNombre = document.createElement('td');
        tdNombre.textContent = requisito.nombre;
        
        const tdEstado = document.createElement('td');
        if (requisito.cumple) {
            tdEstado.innerHTML = '<span class="icono-check-fila">✓</span> Cumple';
            tdEstado.classList.add('estado-cumple');
        } else {
            tdEstado.innerHTML = '<span class="icono-x-fila">✗</span> No cumple';
            tdEstado.classList.add('estado-no-cumple');
        }
        
        tr.appendChild(tdNombre);
        tr.appendChild(tdEstado);
        tbody.appendChild(tr);
    });
}

// Función para obtener ID del docente (ajustar según tu implementación)
function obtenerDocenteId() {
    // Opción 1: Desde URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('docenteId');
    if (id) return id;
    
    // Opción 2: Desde sessionStorage
    const sessionId = sessionStorage.getItem('docenteId');
    if (sessionId) return sessionId;
    
    // Opción 3: Valor por defecto para pruebas
    return '12345';
}

// Función para mostrar errores
function mostrarError(mensaje) {
    const tbody = document.getElementById('tablaRequisitos');
    tbody.innerHTML = `
        <tr>
            <td colspan="2" style="text-align: center; color: #f44336; padding: 2rem;">
                ${mensaje}
            </td>
        </tr>
    `;
}

// Función para cerrar ventana
function cerrarVentana() {
    // Opción 1: Regresar a la página anterior
    window.history.back();
    
    // Opción 2: Redirigir a una página específica
    // window.location.href = 'dashboard.html';
    
    // Opción 3: Cerrar ventana modal (si se usa como modal)
    // window.close();
}