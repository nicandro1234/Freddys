// Función para cerrar sesión
function logout() {
    console.log('Iniciando proceso de cierre de sesión...');
    fetch('auth/logout.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('Respuesta del servidor:', data);
        if (data.success) {
            // Limpiar datos locales
            localStorage.clear();
            sessionStorage.clear();
            
            // Actualizar la interfaz
            const googleSigninContainer = document.getElementById('google-signin-container');
            const userWelcomeContainer = document.getElementById('user-welcome-container');
            const notLoggedIn = document.getElementById('notLoggedIn');
            const loggedIn = document.getElementById('loggedIn');
            const userWelcomeText = document.getElementById('user-welcome-text');

            if (googleSigninContainer) {
                googleSigninContainer.style.display = 'block';
                console.log('Mostrando contenedor de inicio de sesión');
            }
            if (userWelcomeContainer) {
                userWelcomeContainer.style.display = 'none';
                console.log('Ocultando contenedor de bienvenida');
            }
            if (notLoggedIn) {
                notLoggedIn.style.display = 'block';
                console.log('Mostrando sección de no autenticado');
            }
            if (loggedIn) {
                loggedIn.style.display = 'none';
                console.log('Ocultando sección de autenticado');
            }
            if (userWelcomeText) {
                userWelcomeText.textContent = '';
                console.log('Limpiando texto de bienvenida');
            }

            // Redirigir al inicio
            window.location.href = '/';
        } else {
            console.error('Error al cerrar sesión:', data.message);
            alert('Error al cerrar sesión. Por favor, intenta nuevamente.');
        }
    })
    .catch(error => {
        console.error('Error al cerrar sesión:', error);
        alert('Error al cerrar sesión. Por favor, intenta nuevamente.');
    });
}

// Inicialización de SDKs de pago
function initPaymentSDKs() {
    try {
        // Verificar que las constantes estén disponibles
        if (!window.config) {
            console.error('[Auth] Configuración no disponible. Verificando si se cargó el evento configLoaded.');
            
            // Verificar si ya estamos escuchando el evento
            if (window.addEventListener && !window._configListenerAttached) {
                console.log('[Auth] Configurando listener para el evento configLoaded');
                window._configListenerAttached = true;
                document.addEventListener('configLoaded', function() {
                    console.log('[Auth] Evento configLoaded recibido, reiniciando initPaymentSDKs');
                    setTimeout(initPaymentSDKs, 100);
                });
            } else {
                console.log('[Auth] Ya existe un listener para configLoaded o no es posible añadirlo');
                setTimeout(initPaymentSDKs, 1000);
            }
            return;
        }

        console.log('[Auth] Inicializando SDKs de pago con la configuración:', window.config);

        // Inicializar Mercado Pago
        if (window.config.MP_PUBLIC_KEY) {
            try {
                if (typeof MercadoPago !== 'undefined') {
                    const mp = new MercadoPago(window.config.MP_PUBLIC_KEY, {
                        locale: 'es-MX'
                    });
                    console.log('[Auth] Mercado Pago SDK inicializado correctamente');
                    window._mercadoPagoInitialized = true;
                } else {
                    console.error('[Auth] MercadoPago SDK no está cargado');
                }
            } catch (mpError) {
                console.error('[Auth] Error al inicializar Mercado Pago:', mpError);
            }
        } else {
            console.warn('[Auth] MP_PUBLIC_KEY no está definido');
        }

        // Inicializar PayPal
        const paypalContainer = document.getElementById('paypal-button-container');
        if (window.config.PAYPAL_CLIENT_ID && paypalContainer) {
            try {
                if (typeof paypal !== 'undefined') {
                    paypal.Buttons({
                        // Configuración básica de PayPal
                        style: {
                            color: 'blue',
                            shape: 'rect',
                            label: 'pay'
                        },
                        createOrder: function(data, actions) {
                            return actions.order.create({
                                purchase_units: [{
                                    amount: {
                                        value: '0.01'
                                    }
                                }]
                            });
                        },
                        onApprove: function(data, actions) {
                            return actions.order.capture().then(function(details) {
                                console.log('Pago completado:', details);
                            });
                        }
                    }).render(paypalContainer);
                    console.log('PayPal SDK inicializado correctamente');
                } else {
                    console.error('PayPal SDK no está cargado');
                }
            } catch (ppError) {
                console.error('Error al inicializar PayPal:', ppError);
            }
        } else {
            console.warn('PAYPAL_CLIENT_ID no está definido o contenedor no encontrado');
        }

        // Inicializar Google Maps
        const mapElement = document.getElementById('map');
        if (window.config.GOOGLE_MAPS_API_KEY && mapElement) {
            try {
                if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                    const map = new google.maps.Map(mapElement, {
                        center: { lat: 21.1213, lng: -101.6741 }, // León, Guanajuato
                        zoom: 12
                    });
                    console.log('Google Maps inicializado correctamente');
                } else {
                    console.warn('Google Maps API no está cargada todavía. Se intentará más tarde.');
                    // En vez de error, podemos intentar nuevamente más tarde
                    setTimeout(() => {
                        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                            const map = new google.maps.Map(mapElement, {
                                center: { lat: 21.1213, lng: -101.6741 },
                                zoom: 12
                            });
                            console.log('Google Maps inicializado correctamente (reintento)');
                        }
                    }, 2000);
                }
            } catch (mapError) {
                console.error('Error al inicializar Google Maps:', mapError);
            }
        } else if (window.config.GOOGLE_MAPS_API_KEY) {
            console.warn('Elemento de mapa no encontrado pero la API key está configurada');
        } else {
            console.warn('GOOGLE_MAPS_API_KEY no está definida');
        }
    } catch (error) {
        console.error('Error general al inicializar SDKs:', error);
    }
}

// Verificar estado de autenticación
function checkAuthStatus() {
    console.log('Verificando estado de autenticación...');
    
    // Primero verificamos si tenemos datos en localStorage como respaldo
    const userData = localStorage.getItem('userData');
    let fallbackUser = null;
    
    if (userData) {
        try {
            fallbackUser = JSON.parse(userData);
            console.log('Datos de usuario encontrados en localStorage:', fallbackUser);
        } catch (e) {
            console.warn('Error al parsear datos de usuario en localStorage:', e);
        }
    }

    // Intentamos verificar sesión con el servidor
    fetch('auth/check_session.php', {
        method: 'GET',
        credentials: 'include',
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error de red: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Estado de autenticación desde servidor:', data);
        
        if (data.authenticated) {
            console.log('Usuario autenticado:', data.user);
            handleAuthenticatedUser(data.user);
            // Actualizar datos en localStorage
            localStorage.setItem('userData', JSON.stringify(data.user));
        } else {
            // Si el servidor dice que no está autenticado, pero tenemos datos en localStorage,
            // podemos intentar usar esos datos como respaldo para una mejor experiencia offline
            if (fallbackUser) {
                console.warn('Usando datos de respaldo del localStorage');
                handleAuthenticatedUser(fallbackUser);
            } else {
                handleUnauthenticatedUser();
            }
        }
    })
    .catch(error => {
        console.error('Error al verificar autenticación:', error);
        
        // Si hay un error de red, usamos el fallback de localStorage si está disponible
        if (fallbackUser) {
            console.warn('Error de red. Usando datos de respaldo del localStorage');
            handleAuthenticatedUser(fallbackUser);
        } else {
            handleUnauthenticatedUser();
        }
    });
}

// Manejar usuario autenticado
function handleAuthenticatedUser(user) {
    console.log('[Auth] Manejando usuario autenticado. Datos recibidos:', user); // Log inicial
    // Log detallado del objeto user si existe
    if (user) {
        console.log('[Auth HandleUser] User Object Details:', JSON.stringify(user, null, 2));
    } else {
        console.warn('[Auth HandleUser] El objeto user recibido es nulo o indefinido.');
        // Considera llamar a handleUnauthenticatedUser si user es inválido
        handleUnauthenticatedUser();
        return; // Salir si no hay datos de usuario válidos
    }

    // --- ACTUALIZAR CUERPO (INMEDIATO) ---
    const notLoggedInDiv = document.getElementById('notLoggedIn');
    const loggedInDiv = document.getElementById('loggedIn');
    if (notLoggedInDiv) {
        notLoggedInDiv.style.display = 'none';
        console.log('[Auth] Ocultando #notLoggedIn');
    }
    if (loggedInDiv) {
        loggedInDiv.style.display = 'block';
        console.log('[Auth] Mostrando #loggedIn');
        // Cargar datos específicos de la cuenta
        if (typeof loadUserData === 'function') {
            loadUserData();
        }
    }
    
    // --- ACTUALIZAR HEADER (CON RETRASO LEVE) ---
    setTimeout(() => {
        console.log('[Auth HandleUser] Intentando actualizar header (después de 250ms)...');
        const googleSigninContainer = document.getElementById('google-signin-container');
        const userWelcomeContainer = document.getElementById('user-welcome-container');
        const userWelcomeText = document.getElementById('user-welcome-text');
        const cartIconLink = document.getElementById('cart-icon-link'); 
        const userAvatar = document.getElementById('userAvatar');
        const offcanvasLogoutBtn = document.querySelector('.offcanvas-logout-btn'); // Botón logout en offcanvas

        // Loguear si se encontraron los elementos del header
        console.log('[Auth HandleUser] Header Elements Found:', {
            googleSignin: !!googleSigninContainer,
            userWelcome: !!userWelcomeContainer,
            welcomeText: !!userWelcomeText,
            cartIcon: !!cartIconLink,
            userAvatar: !!userAvatar,
            offcanvasLogout: !!offcanvasLogoutBtn
        });

        if (googleSigninContainer) googleSigninContainer.style.display = 'none';
        if (userWelcomeContainer) userWelcomeContainer.style.display = 'flex'; 
        if (offcanvasLogoutBtn) offcanvasLogoutBtn.style.display = 'block'; // Mostrar botón logout en offcanvas móvil

        // Actualizar texto de bienvenida
        if (userWelcomeText) {
            if (user && user.name) {
                const firstName = user.name.split(' ')[0];
                console.log(`[Auth HandleUser] Setting welcome text to: Bienvenido ${firstName}`);
                userWelcomeText.textContent = `Bienvenido ${firstName}`;
            } else {
                console.warn('[Auth HandleUser] user.name no disponible para saludo.');
                userWelcomeText.textContent = 'Bienvenido'; // Fallback
            }
        } else {
            console.warn('[Auth HandleUser] #user-welcome-text element not found.');
        }
        
        if (cartIconLink) cartIconLink.style.display = 'block';

        // Actualizar Avatar (si existe en la página actual)
        if (userAvatar) {
            if (user && user.photo_url) {
                console.log('[Auth HandleUser] Setting avatar src:', user.photo_url);
                userAvatar.src = user.photo_url;
            } else {
                console.log('[Auth HandleUser] No photo_url found, using default avatar.');
                userAvatar.src = 'assets/img/default-avatar.png'; // Ruta a tu avatar por defecto
            }
            userAvatar.style.display = 'block'; // Asegurarse de que sea visible
        } else {
             console.log('[Auth HandleUser] #userAvatar element not found on this page.');
        }

        // Actualizar Sidebar (si existe en la página actual, ej. my-account.html)
        const userNameEl = document.getElementById('userName');
        const userEmailEl = document.getElementById('userEmail');
        console.log('[Auth HandleUser] Sidebar Elements Found:', { userNameEl: !!userNameEl, userEmailEl: !!userEmailEl });

        if (userNameEl) {
            if (user && user.name) {
                console.log('[Auth HandleUser] Setting sidebar user name:', user.name);
                userNameEl.textContent = user.name;
            } else {
                console.warn('[Auth HandleUser] user.name not available for sidebar.');
                userNameEl.textContent = 'Usuario'; // Fallback
            }
        }
        if (userEmailEl) {
            if (user && user.email) {
                console.log('[Auth HandleUser] Setting sidebar user email:', user.email);
                userEmailEl.textContent = user.email;
            } else {
                console.warn('[Auth HandleUser] user.email not available for sidebar.');
                userEmailEl.textContent = 'Email no disponible'; // Fallback
            }
        }

        console.log('[Auth HandleUser] UI update attempt complete.');
    }, 250); 
}

// Manejar usuario no autenticado
function handleUnauthenticatedUser() {
    console.log('[Auth] Manejando usuario no autenticado.');
    const elements = {
        googleSigninContainer: document.getElementById('google-signin-container'),
        userWelcomeContainer: document.getElementById('user-welcome-container'),
        userWelcomeText: document.getElementById('user-welcome-text'),
        notLoggedInDiv: document.getElementById('notLoggedIn'),
        loggedInDiv: document.getElementById('loggedIn')
    };

    // Actualizar Header
    if (elements.googleSigninContainer) elements.googleSigninContainer.style.display = 'block';
    if (elements.userWelcomeContainer) elements.userWelcomeContainer.style.display = 'none';
    if (elements.userWelcomeText) elements.userWelcomeText.textContent = ''; // Limpiar saludo

    // Actualizar Cuerpo (my-account)
    if (elements.notLoggedInDiv) {
        elements.notLoggedInDiv.style.display = 'block'; // Mostrar mensaje de inicio de sesión
        console.log('[Auth] Mostrando #notLoggedIn');
    }
    if (elements.loggedInDiv) {
        elements.loggedInDiv.style.display = 'none'; // Ocultar contenido de cuenta
        console.log('[Auth] Ocultando #loggedIn');
    }
    
    // Limpiar datos de localStorage si el servidor confirma no autenticado
    localStorage.removeItem('userData');
}

// Función para cargar datos del usuario
function loadUserData() {
    console.log('[Auth] Iniciando carga de datos del usuario...');
    // Cargar pedidos
    loadOrders();
    // Cargar direcciones
    loadAddresses();
    // Cargar teléfonos
    loadPhones();
}

// Función para cargar pedidos
function loadOrders(statusFilter = 'all') {
    console.log(`Iniciando carga de pedidos... Filtro: ${statusFilter}`);
            const ordersList = document.getElementById('ordersList');
    
    if (!ordersList) {
        console.error('No se encontró el elemento ordersList');
        return;
    }

    // Verificar si el usuario está autenticado
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    if (!userData || !userData.id) {
        console.error('Usuario no autenticado');
        ordersList.innerHTML = '<tr><td colspan="5" class="text-center">Debes iniciar sesión para ver tus pedidos</td></tr>';
        return;
    }

    console.log('Usuario autenticado, haciendo petición a get_user_orders.php...');
    // Construir la URL con el filtro
    let apiUrl = 'auth/get_user_orders.php';
    if (statusFilter && statusFilter !== 'all') {
        apiUrl += `?status_filter=${encodeURIComponent(statusFilter)}`;
    }

    fetch(apiUrl, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => {
        console.log('Respuesta recibida:', response);
        if (!response.ok) {
            throw new Error('Error al cargar pedidos');
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos de pedidos recibidos:', data);
        if (!data.success) {
            throw new Error(data.message || 'Error al cargar pedidos');
        }

        if (!data.orders || data.orders.length === 0) {
            ordersList.innerHTML = '<tr><td colspan="5" class="text-center">No tienes pedidos aún</td></tr>';
            return;
        }

        console.log('Procesando', data.orders.length, 'pedidos...');
        ordersList.innerHTML = data.orders.map(order => {
            // Determinar la clase del badge según el estado
            let badgeClass = '';
            switch(order.status.toLowerCase()) {
                case 'pendiente':
                    badgeClass = 'bg-warning';
                    break;
                case 'enviado':
                    badgeClass = 'bg-info';
                    break;
                case 'entregado':
                    badgeClass = 'bg-success';
                    break;
                default:
                    badgeClass = 'bg-secondary';
            }

            return `
                <tr>
                    <td data-label="ID Pedido"><span class="value-container">#${order.id}</span></td>
                    <td data-label="Fecha"><span class="value-container">${order.order_date}</span></td>
                    <td data-label="Total"><span class="value-container">$${order.total_amount}</span></td>
                    <td data-label="Estado"><span class="value-container"><span class="badge ${badgeClass}">${order.status}</span></span></td>
                    <td data-label="Acciones">
                        <span class="value-container">
                            <button class="btn btn-sm btn-primary view-order" data-order-id="${order.id}"> 
                                Ver Detalles
                            </button>
                        </span>
                    </td>
                </tr>
            `;
        }).join('');

        // Guardar los detalles de los pedidos en localStorage para acceso rápido
        localStorage.setItem('orderDetails', JSON.stringify(data.orders));
    })
    .catch(error => {
        console.error('Error al cargar pedidos:', error);
        ordersList.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Error al cargar los pedidos. Por favor, intenta nuevamente.</td></tr>';
    });
}

// Función para mostrar los detalles de una orden
function showOrderDetails(orderId) {
    console.log('Mostrando detalles del pedido:', orderId);
    
    // Verificar si el usuario está autenticado
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    if (!userData || !userData.id) {
        console.error('Usuario no autenticado');
        alert('Debes iniciar sesión para ver los detalles del pedido');
        return;
    }

    // Mostrar el contenedor de detalles y ocultar la tabla de órdenes
    const orderDetails = document.getElementById('orderDetails');
    const ordersTable = document.querySelector('.orders-table');
    
    if (!orderDetails) {
        console.error('No se encontró el elemento orderDetails');
        return;
    }

    if (ordersTable) {
        ordersTable.style.display = 'none';
    }
    
    // Mostrar indicador de carga
    orderDetails.style.display = 'block';
    orderDetails.innerHTML = '<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Cargando detalles...</p></div>';

    // Obtener los detalles del pedido
    fetch(`auth/get_order_details.php?id=${orderId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos de detalles recibidos:', data);
        
        if (!data.success) {
            throw new Error(data.message || 'Error al cargar los detalles del pedido');
        }

        const order = data.order;
        console.log('>>> showOrderDetails: Objeto Order recibido:', JSON.stringify(order, null, 2));
        
        // Construir la cadena de dirección de envío
        let shippingAddressString = 'No disponible';
        if (order.shipping_address_details && order.shipping_address_details.address_line) {
            shippingAddressString = order.shipping_address_details.address_line;
        } else if (typeof order.shipping_address === 'string' && order.shipping_address.trim() !== '') {
            // Fallback al string original si details.address_line no está pero el campo original sí
            shippingAddressString = order.shipping_address;
        }

        if (order.shipping_phone) {
            shippingAddressString += `, Tel: ${order.shipping_phone}`;
        }

        // Reconstruir el HTML del contenedor de detalles
        orderDetails.innerHTML = `
            <div class="order-details-header">
                <h3>Detalles del Pedido #${order.id || 'N/A'}</h3>
                <button class="order-details-close" onclick="hideOrderDetails()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="order-info">
                <p><strong>Fecha:</strong> ${order.order_date_formatted || order.order_date_raw || 'N/A'}</p>
                <p><strong>Estado:</strong> ${order.status_translated || order.status_original || 'N/A'}</p>
                <p><strong>Dirección de entrega:</strong> ${shippingAddressString}</p>
            </div>
            <div class="order-items">
                ${order.items && order.items.length > 0 ? 
                    order.items.map(item => {
                        const itemName = item.name || 'Producto Desconocido';
                        const itemQuantity = parseInt(item.quantity) || 0;
                        const itemPrice = parseFloat(item.price) || 0;
                        const itemTotal = (itemQuantity * itemPrice).toFixed(2);
                        return `
                            <div class="order-item">
                                <div class="d-flex justify-content-between">
                                    <div><strong>${itemName}</strong> x ${itemQuantity}</div>
                                    <div>$${itemTotal}</div>
                                </div>
                            </div>
                        `;
                    }).join('') : 
                    '<p>No hay productos en este pedido.</p>'
                }
            </div>
            <div class="order-total">
                <strong>Total:</strong> $${order.total_amount_formatted || order.total_amount || '0.00'}
            </div>
        `;
        
        console.log('<<< showOrderDetails: Actualización del DOM completada.');
    })
    .catch(error => {
        console.error('Error al cargar los detalles del pedido:', error);
        orderDetails.innerHTML = `
            <div class="alert alert-danger">
                <h3>Error</h3>
                <p>${error.message || 'No se pudo cargar la información del pedido'}</p>
                <button class="btn btn-secondary" onclick="hideOrderDetails()">Volver</button>
            </div>
        `;
    });
}

// Función para ocultar los detalles de la orden
function hideOrderDetails() {
    const orderDetails = document.getElementById('orderDetails');
    const ordersTable = document.querySelector('.orders-table');
    
    if (orderDetails) {
        orderDetails.style.display = 'none';
    }
    
    if (ordersTable) {
        ordersTable.style.display = 'block';
    }
}

// Función auxiliar para obtener la clase del badge según el estado
function getStatusBadgeClass(status) {
    switch (status.toLowerCase()) {
        case 'completado':
            return 'success';
        case 'pendiente':
            return 'warning';
        case 'cancelado':
            return 'danger';
        default:
            return 'secondary';
    }
}

// Función para cargar direcciones
function loadAddresses() {
    console.log('Cargando direcciones...');
            const addressesList = document.getElementById('addressesList');
    
    if (!addressesList) {
        console.error('No se encontró el elemento addressesList');
        return;
    }

    // Verificar si el usuario está autenticado
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    if (!userData || !userData.id) {
        console.error('Usuario no autenticado');
        addressesList.innerHTML = '<div class="col-12 text-center">Debes iniciar sesión para ver tus direcciones</div>';
        return;
    }

    fetch('auth/get_user_addresses.php', {
        method: 'GET',
        credentials: 'include',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error en la respuesta del servidor: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos de direcciones recibidos:', data);
        if (!data.success) {
            throw new Error(data.message || 'Error al cargar direcciones');
        }

        if (!data.addresses || data.addresses.length === 0) {
            addressesList.innerHTML = '<div class="col-12 text-center">No tienes direcciones guardadas</div>';
            return;
        }

                addressesList.innerHTML = data.addresses.map(address => `
            <div class="address-card ${address.is_favorite ? 'favorite' : ''}">
                        <div class="address-actions">
                        <button class="favorite-btn" onclick="toggleAddressFavorite(${address.id})">
                            <i class="fas fa-star"></i>
                        </button>
                        <button onclick="editAddress(${address.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        <button onclick="deleteAddress(${address.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                    </div>
                    <h3>${address.address_line || 'Dirección'}</h3>
                    <div class="address-details">
                        ${address.is_favorite ? '<p class="favorite-indicator">Dirección Favorita</p>' : ''}
                        <p>${address.street || ''} ${address.number_int_ext || ''}</p>
                        <p>${address.colonia || ''}</p>
                        <p>${address.city}, ${address.state}</p>
                        <p>${address.zip_code}, ${address.country}</p>
                    </div>
                        </div>
                    </div>
                `).join('');
    })
    .catch(error => {
        console.error('Error al cargar direcciones:', error);
        addressesList.innerHTML = `<div class="col-12 text-danger text-center">Error al cargar las direcciones: ${error.message}</div>`;
    });
}

// Reemplazar la función toggleAddressFavorite existente con esta nueva versión async/await
async function toggleAddressFavorite(addressId) {
    console.log(`Intentando marcar como favorita la dirección ID: ${addressId}`);
    try {
        // 1. Obtener los datos actuales de la dirección
        const getAddressResponse = await fetch(`auth/get_address.php?id=${addressId}`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });

        if (!getAddressResponse.ok) {
            throw new Error(`Error al obtener la dirección: ${getAddressResponse.status}`);
        }
        const addressDataResult = await getAddressResponse.json();

        if (!addressDataResult.success || !addressDataResult.address) {
            throw new Error(addressDataResult.message || 'No se pudo obtener la información de la dirección.');
        }

        const currentAddress = addressDataResult.address;

        // 2. Preparar el payload para la actualización
        // El backend (update_address.php) espera todos los campos de la dirección
        // y 'is_default' para la lógica de favorito.
        const updatePayload = {
            address_line: currentAddress.address_line,
            city: currentAddress.city,
            state: currentAddress.state,
            zip_code: currentAddress.zip_code,
            country: currentAddress.country,
            is_default: true // Marcar esta como la favorita
        };

        // 3. Hacer la petición POST a update_address.php
        const updateResponse = await fetch(`auth/update_address.php?id=${addressId}`, {
            method: 'POST', // Nuestros scripts update_*.php usan POST con cuerpo JSON
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(updatePayload)
        });

        if (!updateResponse.ok) {
            // Intentar leer el mensaje de error del backend si está disponible
            let errorMsg = `Error en la respuesta del servidor: ${updateResponse.status}`;
            try {
                const errorData = await updateResponse.json();
                if (errorData && errorData.message) {
                    errorMsg = errorData.message;
                }
            } catch (e) { /* No hacer nada si el cuerpo del error no es JSON */ }
            throw new Error(errorMsg);
        }

        const updateResult = await updateResponse.json();

        if (!updateResult.success) {
            throw new Error(updateResult.message || 'Error al actualizar la dirección como favorita.');
        }

        console.log('Dirección actualizada como favorita exitosamente.');
        alert('Dirección marcada como favorita.'); // O una notificación más sutil
        loadAddresses(); // Recargar la lista de direcciones para reflejar el cambio

    } catch (error) {
        console.error('Error en toggleAddressFavorite:', error);
        alert(`Error: ${error.message}`);
    }
}

function addNewAddress() {
    console.log('>>> Ejecutando addNewAddress...');
    const formContainer = document.getElementById('address-form-container');
    const addressesList = document.getElementById('addressesList');
    // Seleccionar el botón "Añadir Dirección" específico dentro del header de la pestaña de direcciones
    const addAddressBtnHeader = document.querySelector('#addresses-tab > .account-tab-header #addAddressBtn');

    if (!formContainer) {
        console.error('<<< ERROR en addNewAddress: No se encontró #address-form-container.');
        return;
    }

    // Construir el HTML del formulario
    const formHTML = `
        <div class="form-header">
            <h2>Nueva Dirección</h2>
            <button class="btn btn-secondary" onclick="hideAddressForm()">
                <i class="fas fa-arrow-left"></i> Volver
            </button>
        </div>
        <form id="addressForm" onsubmit="saveNewAddress(event)">
            <div class="mb-3">
                <label for="address_line" class="form-label">Línea de Dirección (Autocompletar)</label>
                <input type="text" 
                       class="form-control" 
                       id="address_line" 
                       name="address_line" 
                       required 
                       placeholder="Comience a escribir su dirección...">
            </div>
            <div class="mb-3">
                <label for="street" class="form-label">Calle</label>
                <input type="text" class="form-control" id="street" name="street" placeholder="Calle (autollenado)">
            </div>
            <div class="mb-3">
                <label for="number_int_ext" class="form-label">Número (Int/Ext)</label>
                <input type="text" class="form-control" id="number_int_ext" name="number_int_ext" placeholder="Número (autollenado)">
            </div>
            <div class="mb-3">
                <label for="colonia" class="form-label">Colonia o Fraccionamiento</label>
                <input type="text" class="form-control" id="colonia" name="colonia" placeholder="Colonia (autollenado)">
            </div>
            <div class="mb-3">
                <label for="city" class="form-label">Ciudad</label>
                <input type="text" class="form-control" id="city" name="city" required placeholder="Ciudad (autollenado)">
            </div>
            <div class="mb-3">
                <label for="state" class="form-label">Estado</label>
                <input type="text" class="form-control" id="state" name="state" required placeholder="Estado (autollenado)">
            </div>
            <div class="mb-3">
                <label for="zip_code" class="form-label">Código Postal</label>
                <input type="text" class="form-control" id="zip_code" name="zip_code" required placeholder="CP (autollenado)">
            </div>
            <div class="mb-3">
                <label for="country" class="form-label">País</label>
                <input type="text" class="form-control" id="country" name="country" required placeholder="País (autollenado)">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                <label class="form-check-label" for="is_default">Dirección favorita</label>
            </div>
            <button type="submit" class="btn btn-primary">Guardar Dirección</button>
        </form>
    `;

    // Actualizar el contenedor
    formContainer.innerHTML = formHTML;
    formContainer.style.display = 'block';
    formContainer.classList.add('active');

    // Ocultar la lista de direcciones y el botón de añadir del header
    if (addressesList) {
        addressesList.style.display = 'none';
    }
    if (addAddressBtnHeader) {
        addAddressBtnHeader.style.display = 'none';
    }

    // Inicializar el autocompletado de Google Maps si está disponible
    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        const addressInput = document.getElementById('address_line');
        if (addressInput) {
            const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                types: ['address'],
                componentRestrictions: { country: 'mx' }
            });

            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (!place.geometry || !place.address_components) {
                    console.warn("Autocomplete: No se encontró geometría o componentes para el lugar:", place);
                    // Considera limpiar los campos si no hay datos válidos
                    document.getElementById('street').value = '';
                    document.getElementById('number_int_ext').value = '';
                    document.getElementById('colonia').value = '';
                    document.getElementById('city').value = '';
                    document.getElementById('state').value = '';
                    document.getElementById('zip_code').value = '';
                    document.getElementById('country').value = '';
                    return;
                }

                // Extraer componentes de la dirección
                let route = '';
                let street_number = '';
                let colonia = '';
                let city = '';
                let state = '';
                let postal_code = '';
                let country = '';

                for (const component of place.address_components) {
                    const types = component.types;
                    if (types.includes('route')) {
                        route = component.long_name;
                    }
                    if (types.includes('street_number')) {
                        street_number = component.long_name;
                    }
                    if (types.includes('neighborhood') || types.includes('sublocality') || types.includes('sublocality_level_1')) {
                        colonia = component.long_name;
                    }
                    if (types.includes('locality')) {
                        city = component.long_name;
                    }
                    if (types.includes('administrative_area_level_1')) {
                        state = component.long_name;
                    }
                    if (types.includes('postal_code')) {
                        postal_code = component.long_name;
                    }
                    if (types.includes('country')) {
                        country = component.long_name;
                    }
                }

                // Actualizar campos del formulario
                // Combinar calle y número si ambos existen, o usar solo la calle si no hay número detallado.
                document.getElementById('street').value = route || ''; 
                document.getElementById('number_int_ext').value = street_number || ''; 
                document.getElementById('colonia').value = colonia || '';
                document.getElementById('city').value = city || '';
                document.getElementById('state').value = state || '';
                document.getElementById('zip_code').value = postal_code || '';
                document.getElementById('country').value = country || '';

                // Mantener la dirección completa formateada en el campo principal si es útil
                if (place.formatted_address) {
                    addressInput.value = place.formatted_address;
                    console.log("Autocomplete: Campo 'address_line' actualizado con:", place.formatted_address);
                } else {
                    console.warn("Autocomplete: No se pudo obtener place.formatted_address.");
                }
            });
        } else {
            console.warn('El campo de input #address_line no fue encontrado para el autocompletado.');
        }
    } else {
        console.warn('Google Maps API no está disponible para inicializar Autocomplete.');
    }
}

function saveNewAddress(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        address_line: formData.get('address_line'), // La dirección completa autocompletada
        street: formData.get('street'),
        number_int_ext: formData.get('number_int_ext'),
        colonia: formData.get('colonia'),
        city: formData.get('city'),
        state: formData.get('state'),
        zip_code: formData.get('zip_code'),
        country: formData.get('country'),
        is_default: formData.get('is_default') === 'on'
    };

    fetch('auth/save_address.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Error al agregar la dirección');
        }
        alert('Dirección agregada correctamente');
        hideAddressForm();
        loadAddresses();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al agregar la dirección');
    });
}

// Función para ocultar el formulario de dirección
function hideAddressForm() {
    console.log('>>> Ejecutando hideAddressForm...');
    const formContainer = document.getElementById('address-form-container');
    const addressesList = document.getElementById('addressesList');
    const addAddressBtnHeader = document.querySelector('#addresses-tab > .account-tab-header #addAddressBtn');

    if (formContainer) {
        formContainer.style.display = 'none';
        formContainer.classList.remove('active');
        formContainer.innerHTML = ''; // Limpiar el contenido del formulario
    }

    // Mostrar la lista de direcciones y el botón de añadir del header
    if (addressesList) {
        addressesList.style.display = 'grid'; // addresses-grid tiene display: grid
    }
    if (addAddressBtnHeader) {
        addAddressBtnHeader.style.display = 'inline-block'; // O el display original del botón
    }
    console.log('<<< hideAddressForm: Formulario de dirección oculto y lista mostrada.');
}

// Función para cargar teléfonos
function loadPhones() {
    console.log('Iniciando carga de teléfonos...');
            const phonesList = document.getElementById('phonesList');
    
    if (!phonesList) {
        console.error('No se encontró el elemento phonesList');
        return;
    }

    // Verificar si el usuario está autenticado
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    if (!userData || !userData.id) {
        console.error('Usuario no autenticado');
        phonesList.innerHTML = '<div class="col-12 text-center">Debes iniciar sesión para ver tus teléfonos</div>';
        return;
    }

    fetch('auth/get_user_phones.php', {
        method: 'GET',
        credentials: 'include',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error en la respuesta del servidor: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos de teléfonos recibidos:', data);
        if (!data.success) {
            throw new Error(data.message || 'Error al cargar teléfonos');
        }

        if (!data.phones || data.phones.length === 0) {
            phonesList.innerHTML = '<div class="col-12 text-center">No tienes teléfonos guardados</div>';
            return;
        }

                phonesList.innerHTML = data.phones.map(phone => `
            <div class="phone-item ${phone.is_favorite ? 'favorite' : ''}">
                    <div class="phone-info">
                        <h3>${phone.phone_number}</h3>
                        ${phone.is_favorite ? '<p class="favorite-indicator">Teléfono Favorito</p>' : ''}
                        </div>
                        <div class="phone-actions">
                        <button class="favorite-btn" onclick="togglePhoneFavorite(${phone.id})">
                            <i class="fas fa-star"></i>
                        </button>
                        <button onclick="editPhone(${phone.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        <button onclick="deletePhone(${phone.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                    </div>
                        </div>
                    </div>
                `).join('');
    })
    .catch(error => {
        console.error('Error al cargar teléfonos:', error);
        phonesList.innerHTML = `<div class="col-12 text-danger text-center">Error al cargar los teléfonos: ${error.message}</div>`;
    });
}

// Reemplazar la función togglePhoneFavorite existente con esta nueva versión async/await
async function togglePhoneFavorite(phoneId) {
    console.log(`Intentando marcar como favorito el teléfono ID: ${phoneId}`);
    try {
        // 1. Obtener los datos actuales del teléfono
        const getPhoneResponse = await fetch(`auth/get_phone.php?id=${phoneId}`, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });

        if (!getPhoneResponse.ok) {
            throw new Error(`Error al obtener el teléfono: ${getPhoneResponse.status}`);
        }
        const phoneDataResult = await getPhoneResponse.json();

        if (!phoneDataResult.success || !phoneDataResult.phone) {
            throw new Error(phoneDataResult.message || 'No se pudo obtener la información del teléfono.');
        }

        const currentPhone = phoneDataResult.phone;

        // 2. Preparar el payload para la actualización
        const updatePayload = {
            phone_number: currentPhone.phone_number,
            is_default: true // Marcar este como el favorito
        };

        // 3. Hacer la petición POST a update_phone.php
        const updateResponse = await fetch(`auth/update_phone.php?id=${phoneId}`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(updatePayload)
        });

        if (!updateResponse.ok) {
            let errorMsg = `Error en la respuesta del servidor: ${updateResponse.status}`;
            try {
                const errorData = await updateResponse.json();
                if (errorData && errorData.message) {
                    errorMsg = errorData.message;
                }
            } catch (e) { /* No hacer nada */ }
            throw new Error(errorMsg);
        }

        const updateResult = await updateResponse.json();

        if (!updateResult.success) {
            throw new Error(updateResult.message || 'Error desconocido al actualizar el teléfono como favorito.');
        }

        console.log('Teléfono actualizado como favorito exitosamente.');
        alert('Teléfono marcado como favorito.');
        loadPhones(); // Recargar la lista

    } catch (error) {
        console.error('Error en togglePhoneFavorite:', error);
        alert(`Error al actualizar el teléfono: ${error.message}`);
    }
}

// Modificar saveNewPhone para que llame al script PHP correcto
function saveNewPhone(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        phone_number: formData.get('phone_number'),
        is_default: formData.get('is_default') === 'on'
    };

    fetch('auth/save_phone.php', { // Cambiado de add_phone.php a save_phone.php
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Error al agregar el teléfono');
        }
        alert('Teléfono agregado correctamente');
        hidePhoneForm();
        loadPhones();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al agregar el teléfono: ' + error.message);
    });
}

// Función para ocultar el formulario de teléfono
function hidePhoneForm() {
    console.log('>>> Ejecutando hidePhoneForm...');
    const formContainer = document.getElementById('phone-form-container');
    const phonesList = document.getElementById('phonesList');
    const addPhoneBtnHeader = document.querySelector('#phones-tab > .account-tab-header #addPhoneBtn');

    if (formContainer) {
        formContainer.style.display = 'none';
        formContainer.classList.remove('active');
        formContainer.innerHTML = ''; // Limpiar el contenido del formulario
    }

    // Mostrar la lista de teléfonos y el botón de añadir del header
    if (phonesList) {
        phonesList.style.display = 'flex'; // .phones-list.row es display: flex
    }
    if (addPhoneBtnHeader) {
        addPhoneBtnHeader.style.display = 'inline-block'; // O el display original del botón
    }
    console.log('<<< hidePhoneForm: Formulario de teléfono oculto y lista mostrada.');
}

// Verificar estado de autenticación al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página cargada, verificando estado de autenticación...');
    checkAuthStatus();
    initPaymentSDKs();
    
    // Verificar cada 5 minutos
    setInterval(checkAuthStatus, 300000);

    // Event listeners para la navegación de pestañas
    const tabLinks = document.querySelectorAll('.account-menu li');
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            
            // Remover clase active de todos los links y contenidos
            tabLinks.forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.account-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Añadir clase active al link y contenido seleccionado
            this.classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
});

// Función para editar una dirección
function editAddress(addressId) {
    console.log('Editando dirección:', addressId);
    const formContainer = document.getElementById('address-form-container');
    const addressesList = document.getElementById('addressesList');
    const addAddressBtnHeader = document.querySelector('#addresses-tab > .account-tab-header #addAddressBtn');

    if (!formContainer) {
        console.error('Error: No se encontró #address-form-container para editar.');
        return;
    }

    // Ocultar lista y botón de añadir del header
    if (addressesList) addressesList.style.display = 'none';
    if (addAddressBtnHeader) addAddressBtnHeader.style.display = 'none';

    fetch(`auth/get_address.php?id=${addressId}`, {
        method: 'GET',
        credentials: 'include',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            // Intentar leer el mensaje de error del cuerpo si es JSON
            return response.json().then(errData => {
                throw new Error(errData.message || `Error en la respuesta del servidor: ${response.status}`);
            }).catch(() => {
                // Si el cuerpo no es JSON o hay otro error al parsear, lanzar error genérico
                throw new Error(`Error en la respuesta del servidor: ${response.status}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos de dirección recibidos:', data);
        if (!data.success || !data.address) { // Asegurarse que data.address exista
            throw new Error(data.message || 'Error al cargar la dirección para editar');
        }

        const address = data.address;
        // Opción A: Simplificar formulario, no usar street, number_int_ext, colonia individualmente
        formContainer.innerHTML = `
            <div class="form-header">
                <h2>Editar Dirección</h2>
                <button class="btn btn-secondary" onclick="hideAddressForm()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </div>
            <form id="addressForm" onsubmit="saveAddress(event, ${addressId})">
                <div class="mb-3">
                    <label for="address_line" class="form-label">Línea de Dirección Completa</label>
                    <input type="text" class="form-control" id="address_line" name="address_line" value="${address.address_line || ''}" required placeholder="Ej: Calle Falsa 123, Colonia...">
                </div>
                // Los campos street, number_int_ext, colonia se omiten ya que no existen en la BD
                <div class="mb-3">
                    <label for="city" class="form-label">Ciudad</label>
                    <input type="text" class="form-control" id="city" name="city" value="${address.city || ''}" required placeholder="Ciudad">
                </div>
                <div class="mb-3">
                    <label for="state" class="form-label">Estado</label>
                    <input type="text" class="form-control" id="state" name="state" value="${address.state || ''}" required placeholder="Estado">
                </div>
                <div class="mb-3">
                    <label for="zip_code" class="form-label">Código Postal</label>
                    <input type="text" class="form-control" id="zip_code" name="zip_code" value="${address.zip_code || ''}" required placeholder="CP">
                </div>
                <div class="mb-3">
                    <label for="country" class="form-label">País</label>
                    <input type="text" class="form-control" id="country" name="country" value="${address.country || ''}" required placeholder="País">
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_default" name="is_default" ${address.is_favorite ? 'checked' : ''}>
                    <label class="form-check-label" for="is_default">Dirección favorita</label>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </form>
        `;
        formContainer.style.display = 'block'; // Asegurarse de que el contenedor del formulario sea visible
        formContainer.classList.add('active');

        // Si se usa Google Autocomplete para editar, se podría inicializar aquí también
        // pero para address_line que es un campo de texto libre editado, quizás no sea necesario
        // al menos que quieras permitir re-autocompletar toda la dirección al editar.
    })
    .catch(error => {
        console.error('Error en editAddress:', error);
        alert('Error al cargar la dirección para editar: ' + error.message);
        hideAddressForm(); // Opcional: ocultar el formulario si la carga falla
    });
}

// Función para guardar una dirección (actualización)
function saveAddress(event, addressId) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    // Opción A: No incluir street, number_int_ext, colonia
    const data = {
        address_line: formData.get('address_line'),
        // street: formData.get('street'), // Omitido
        // number_int_ext: formData.get('number_int_ext'), // Omitido
        // colonia: formData.get('colonia'), // Omitido
        city: formData.get('city'),
        state: formData.get('state'),
        zip_code: formData.get('zip_code'),
        country: formData.get('country'),
        is_default: formData.get('is_default') === 'on'
    };

    console.log("[saveAddress] Enviando datos para actualizar:", JSON.stringify(data));

    fetch(`auth/update_address.php?id=${addressId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
        credentials: 'include'
    })
    .then(response => {
        // Primero, verifica si la respuesta es ok, luego intenta parsear como JSON
        if (!response.ok) {
            // Si no es ok, intenta obtener el mensaje de error del cuerpo JSON
            return response.json().then(errData => { 
                throw new Error(errData.message || `Error del servidor: ${response.status}`); 
            }).catch(() => {
                // Si el cuerpo no es JSON o hay otro error, lanzar un error genérico
                throw new Error(`Error del servidor: ${response.status}`);
            });
        }
        return response.json(); // Si es ok, parsea como JSON
    })
    .then(resultData => { // Cambiado de 'data' a 'resultData' para evitar confusión de scope
        if (!resultData.success) {
            throw new Error(resultData.message || 'Error al actualizar la dirección');
        }
        alert('Dirección actualizada correctamente');
        hideAddressForm();
        loadAddresses();
    })
    .catch(error => {
        console.error('Error en saveAddress:', error);
        alert('Error al actualizar la dirección: ' + error.message);
    });
}

// Animaciones del carrito
function addToCartAnimation(item) {
    const cartIcon = document.getElementById('cart-icon-link');
    cartIcon.classList.add('cart-bounce');
    setTimeout(() => {
        cartIcon.classList.remove('cart-bounce');
    }, 1000);
}

function removeFromCartAnimation(item) {
    const cartItem = document.getElementById(`cart-item-${item.id}`);
    cartItem.classList.add('cart-item-remove');
    setTimeout(() => {
        cartItem.remove();
    }, 500);
}

// Llamar a la inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initPaymentSDKs();
}); 

// Función para mostrar el formulario de edición de teléfono
function editPhone(phoneId) {
    console.log('Editando teléfono:', phoneId);
    const formContainer = document.getElementById('phone-form-container');
    const phonesList = document.getElementById('phonesList');
    const addPhoneBtnHeader = document.querySelector('#phones-tab > .account-tab-header #addPhoneBtn');

    if (!formContainer) {
        console.error('Error: No se encontró #phone-form-container para editar.');
        return;
    }

    // Ocultar lista y botón de añadir del header
    if (phonesList) phonesList.style.display = 'none';
    if (addPhoneBtnHeader) addPhoneBtnHeader.style.display = 'none';

    fetch(`auth/get_phone.php?id=${phoneId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos de teléfono recibidos:', data);
        if (!data.success) {
            throw new Error(data.message || 'Error al cargar el teléfono');
        }

        const phone = data.phone;
        formContainer.innerHTML = `
            <div class="form-header">
                <h2>Editar Teléfono</h2>
                <button class="btn btn-secondary" onclick="hidePhoneForm()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </div>
            <form id="phoneForm" onsubmit="savePhone(event, ${phoneId})">
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Número de Teléfono</label>
                    <input type="tel" class="form-control" id="phone_number" name="phone_number" value="${phone.phone_number}" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_default" name="is_default" ${phone.is_favorite ? 'checked' : ''}>
                    <label class="form-check-label" for="is_default">Teléfono favorito</label>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </form>
        `;
        formContainer.classList.add('active');
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cargar el teléfono: ' + error.message);
    });
}

// Función para guardar los cambios de un teléfono
function savePhone(event, phoneId) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        phone_number: formData.get('phone_number'),
        is_default: formData.get('is_default') === 'on'
    };

    fetch(`auth/update_phone.php?id=${phoneId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Error al actualizar el teléfono');
        }
        alert('Teléfono actualizado correctamente');
        hidePhoneForm();
        loadPhones();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar el teléfono');
    });
}

// Función para eliminar un teléfono
function deletePhone(phoneId) {
    if (!confirm('¿Estás seguro de que deseas eliminar este teléfono?')) {
        return;
    }

    fetch(`auth/delete_phone.php?id=${phoneId}`, {
        method: 'DELETE',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Error al eliminar el teléfono');
        }
        alert('Teléfono eliminado correctamente');
        loadPhones();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar el teléfono');
    });
}

// AÑADIR ESTA FUNCIÓN
function addNewPhone() {
    console.log('>>> Ejecutando addNewPhone...');
    const formContainer = document.getElementById('phone-form-container');
    const phonesList = document.getElementById('phonesList');
    // Seleccionar el botón "Añadir Teléfono" específico dentro del header de la pestaña de teléfonos
    const addPhoneBtnHeader = document.querySelector('#phones-tab > .account-tab-header #addPhoneBtn');

    if (!formContainer) {
        console.error('<<< ERROR en addNewPhone: No se encontró #phone-form-container.');
        return;
    }

    // Construir el HTML del formulario
    const formHTML = `
        <div class="form-header">
            <h2>Nuevo Teléfono</h2>
            <button class="btn btn-secondary" onclick="hidePhoneForm()">
                <i class="fas fa-arrow-left"></i> Volver
            </button>
        </div>
        <form id="phoneForm" onsubmit="saveNewPhone(event)">
            <div class="mb-3">
                <label for="phone_number" class="form-label">Número de Teléfono</label>
                <input type="tel" 
                       class="form-control" 
                       id="phone_number" 
                       name="phone_number" 
                       required 
                       pattern="[0-9]{10}"
                       placeholder="10 dígitos"
                       maxlength="10">
                <div class="form-text">Ingrese un número de 10 dígitos sin espacios ni guiones.</div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                <label class="form-check-label" for="is_default">Teléfono principal</label>
            </div>
            <button type="submit" class="btn btn-primary">Guardar Teléfono</button>
        </form>
    `;

    // Actualizar el contenedor
    formContainer.innerHTML = formHTML;
    formContainer.style.display = 'block';
    formContainer.classList.add('active');

    // Ocultar la lista de teléfonos y el botón de añadir del header
    if (phonesList) {
        phonesList.style.display = 'none';
    }
    if (addPhoneBtnHeader) {
        addPhoneBtnHeader.style.display = 'none';
    }

    // Agregar el evento para formatear el número de teléfono
    const phoneInput = document.getElementById('phone_number');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            // Remover cualquier carácter que no sea número
            let value = e.target.value.replace(/\D/g, '');
            
            // Limitar a 10 dígitos
            value = value.substring(0, 10);
            
            // Actualizar el valor del input
            e.target.value = value;
        });
    }
} 