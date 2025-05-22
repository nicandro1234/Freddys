<?php
// admin/includes/admin_footer.php

// Obtener datos necesarios para el modal de estado, si no están ya globales
if (!isset($storeStatus) && function_exists('getStoreStatus')) {
    $storeStatus = getStoreStatus(); 
}
?>

<!-- El cierre de </main> debe estar en la página principal ANTES de incluir el footer -->
<!-- </main> --> 

<!-- Modal Global para cambiar estado de la tienda -->
<?php if (!empty($storeStatus) && isset($storeStatus['is_open'])): ?>
<div class="modal fade" id="storeStatusModal" tabindex="-1" aria-labelledby="storeStatusModalLabel" aria-hidden="true" style="z-index: 1060;"> <!-- Asegurar z-index alto -->
    <div class="modal-dialog">
        <div class="modal-content admin-card"> <!-- Añadir clase admin-card si se quiere estilo consistente -->
            <div class="modal-header admin-card-header">
                <h5 class="modal-title" id="storeStatusModalLabel">Cambiar Estado de la Tienda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-card-body">
                <p>La tienda está actualmente <strong><?php echo $storeStatus['is_open'] ? 'ABIERTA' : 'CERRADA'; ?></strong>.</p>
                <p>¿Deseas <strong><?php echo $storeStatus['is_open'] ? 'CERRAR' : 'ABRIR'; ?></strong> la tienda ahora?</p>
            </div>
            <div class="modal-footer admin-card-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmStoreStatusChangeBtn">
                    Confirmar Cambio
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Scripts JS comunes para el panel de admin pueden ir aquí -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Función Global para actualizar el estado de la tienda (vía AJAX)
    function updateStoreStatus(isOpen) {
        console.log('Intentando actualizar estado a:', isOpen ? 'Abierto' : 'Cerrado');
        // Cambiar a ruta absoluta desde la raíz del sitio web
        const apiUrl = '/admin/api/update_store_status.php'; // <-- CORREGIDO: API está dentro de admin

        fetch(apiUrl, { 
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // Considerar añadir CSRF token si es aplicable
            },
            body: JSON.stringify({
                is_open: isOpen
            })
        })
        .then(response => {
             if (!response.ok) {
                 // Si la respuesta no es OK, intenta leer el cuerpo como texto para ver el error
                 return response.text().then(text => { 
                     throw new Error('Error del servidor: ' + response.status + ' ' + response.statusText + ' - ' + text);
                 });
             }
            // Si la respuesta es OK, procesa el JSON
            return response.json();
        })
        .then(data => {
            console.log('Respuesta de la API:', data);
            if (data.success) {
                // alert('Estado de la tienda actualizado con éxito.'); // Opcional, se puede quitar
                
                // --- Actualizar UI Inmediatamente --- 
                const statusButton = document.querySelector('.admin-store-status .status-toggle-btn');
                if (statusButton) {
                    const icon = statusButton.querySelector('i.fas');
                    const wantsToOpen = (document.querySelector('#storeStatusModal .modal-body p:first-child')?.textContent || '').includes('CERRADA');
                    
                    statusButton.classList.remove('status-open', 'status-closed');
                    icon?.classList.remove('fa-store', 'fa-store-slash');

                    if (wantsToOpen) {
                        statusButton.classList.add('status-open');
                        statusButton.textContent = ' Tienda Abierta'; // Añadir espacio si icono va primero
                        icon?.classList.add('fa-store');
                    } else {
                        statusButton.classList.add('status-closed');
                        statusButton.textContent = ' Tienda Cerrada'; // Añadir espacio si icono va primero
                        icon?.classList.add('fa-store-slash');
                    }
                    // Re-insertar el icono si existe y se borró con textContent
                     if (icon) {
                         statusButton.prepend(icon);
                     }
                }
                // --- Fin Actualizar UI ---

                // Cerrar el modal antes de recargar (opcional, pero puede mejorar UX)
                 var storeStatusModalElement = document.getElementById('storeStatusModal');
                 if (storeStatusModalElement) {
                     var modalInstance = bootstrap.Modal.getInstance(storeStatusModalElement);
                     if (modalInstance) {
                         modalInstance.hide();
                     }
                 }
                
                 // Pequeña pausa antes de recargar para que el usuario vea el cambio (opcional)
                 setTimeout(() => {
                    location.reload(); // Recargar para asegurar consistencia total
                 }, 300); // 300ms de pausa

            } else {
                alert('Error al actualizar el estado de la tienda: ' + (data.message || 'Error desconocido desde la API'));
            }
        })
        .catch(error => {
            console.error('Error en la petición para actualizar estado de tienda:', error);
            alert('Hubo un error al intentar actualizar el estado de la tienda. Detalles: ' + error.message);
        });
    }

    // Listener para el botón de confirmación DENTRO del modal global
    const confirmButton = document.getElementById('confirmStoreStatusChangeBtn');
    if (confirmButton) {
        // Necesitamos saber qué acción tomar (abrir/cerrar) cuando se hace clic.
        // Podemos obtener el estado actual del body del modal o pasarlo de alguna manera.
        // Una forma es añadir un atributo de datos al botón cuando se abre el modal.
        // Por ahora, asumimos que el modal se carga con el estado correcto y podemos inferirlo.
        confirmButton.addEventListener('click', function() {
             const modalBodyText = document.querySelector('#storeStatusModal .modal-body p:first-child')?.textContent || '';
             const wantsToOpen = modalBodyText.includes('CERRADA'); // Si actualmente está cerrada, el botón es para abrir.
             updateStoreStatus(wantsToOpen);
             // Opcionalmente, cerrar el modal inmediatamente o esperar la recarga.
             var storeStatusModalElement = document.getElementById('storeStatusModal');
             if (storeStatusModalElement) {
                 var modalInstance = bootstrap.Modal.getInstance(storeStatusModalElement);
                 if (modalInstance) {
                     modalInstance.hide();
                 }
             }
        });
    }

</script>

</body>
</html> 