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
    const elements = {
        googleSigninContainer: document.getElementById('google-signin-container'),
        userWelcomeContainer: document.getElementById('user-welcome-container'),
        userWelcomeText: document.getElementById('user-welcome-text'),
        notLoggedIn: document.querySelector('.not-logged-in'),
        loggedIn: document.querySelector('.logged-in'),
        userAddressSelect: document.getElementById('userAddressSelect'),
        customerAddress: document.getElementById('customerAddress'),
        userPhoneSelect: document.getElementById('userPhoneSelect'),
        customerPhone: document.getElementById('customerPhone'),
        customerName: document.getElementById('customerName')
    };

    // Ocultar elementos de no autenticado
    if (elements.googleSigninContainer) {
        elements.googleSigninContainer.style.display = 'none';
    }

    // Mostrar elementos de autenticado
    if (elements.userWelcomeContainer) {
        elements.userWelcomeContainer.style.display = 'block';
    }

    if (elements.userWelcomeText) {
        elements.userWelcomeText.textContent = `Bienvenido, ${user.name}`;
    }

    // Autocompletar nombre del usuario
    if (elements.customerName) {
        elements.customerName.value = user.name;
    }

    // Guardar datos del usuario en localStorage
    localStorage.setItem('userData', JSON.stringify(user));

    // Manejar campos de dirección y teléfono
    if (elements.customerAddress && elements.userAddressSelect) {
        elements.customerAddress.style.display = 'none';
        elements.userAddressSelect.style.display = 'block';
    }
    
    if (elements.customerPhone && elements.userPhoneSelect) {
        elements.customerPhone.style.display = 'none';
        elements.userPhoneSelect.style.display = 'block';
    }

    // Cargar direcciones y teléfonos del usuario
    loadUserAddresses();
    loadUserPhones();
}

// Función para cargar datos del usuario
function loadUserData() {
    console.log('Cargando datos del usuario...');
    // Cargar pedidos
    loadOrders();
    // Cargar direcciones
    loadAddresses();
    // Cargar teléfonos
    loadPhones();
    // Cargar configuración
    loadSettings();
}

// Función para cargar pedidos
function loadOrders() {
    fetch('/api/orders.php')
        .then(response => response.json())
        .then(data => {
            const ordersList = document.getElementById('ordersList');
            if (ordersList && data.orders) {
                ordersList.innerHTML = data.orders.map(order => `
                    <tr>
                        <td>${order.id}</td>
                        <td>${new Date(order.date).toLocaleDateString()}</td>
                        <td>$${order.total.toFixed(2)}</td>
                        <td><span class="status-${order.status.toLowerCase()}">${order.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewOrder(${order.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        })
        .catch(error => console.error('Error al cargar pedidos:', error));
}

// Función para cargar direcciones
function loadAddresses() {
    fetch('/api/addresses.php')
        .then(response => response.json())
        .then(data => {
            const addressesList = document.getElementById('addressesList');
            if (addressesList && data.addresses) {
                addressesList.innerHTML = data.addresses.map(address => `
                    <div class="address-card">
                        <div class="address-content">
                            <h4>${address.alias || 'Dirección'}</h4>
                            <p>${address.street}</p>
                            <p>${address.city}, ${address.state} ${address.zip}</p>
                        </div>
                        <div class="address-actions">
                            <button class="btn btn-sm btn-primary" onclick="editAddress(${address.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteAddress(${address.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        })
        .catch(error => console.error('Error al cargar direcciones:', error));
}

// Función para cargar teléfonos
function loadPhones() {
    fetch('/api/phones.php')
        .then(response => response.json())
        .then(data => {
            const phonesList = document.getElementById('phonesList');
            if (phonesList && data.phones) {
                phonesList.innerHTML = data.phones.map(phone => `
                    <div class="phone-card">
                        <div class="phone-content">
                            <h4>${phone.alias || 'Teléfono'}</h4>
                            <p>${phone.number}</p>
                        </div>
                        <div class="phone-actions">
                            <button class="btn btn-sm btn-primary" onclick="editPhone(${phone.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deletePhone(${phone.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        })
        .catch(error => console.error('Error al cargar teléfonos:', error));
}

// Función para cargar configuración
function loadSettings() {
    fetch('/api/settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.settings) {
                document.getElementById('emailNotifications').checked = data.settings.emailNotifications;
                document.getElementById('smsNotifications').checked = data.settings.smsNotifications;
            }
        })
        .catch(error => console.error('Error al cargar configuración:', error));
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

    // Event listener para guardar configuración
    const saveSettingsBtn = document.getElementById('saveSettings');
    if (saveSettingsBtn) {
        saveSettingsBtn.addEventListener('click', function() {
            const settings = {
                emailNotifications: document.getElementById('emailNotifications').checked,
                smsNotifications: document.getElementById('smsNotifications').checked
            };

            fetch('/api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(settings)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuración guardada correctamente');
                } else {
                    alert('Error al guardar la configuración');
                }
            })
            .catch(error => {
                console.error('Error al guardar configuración:', error);
                alert('Error al guardar la configuración');
            });
        });
    }
});

// Función para cargar direcciones del usuario
function loadUserAddresses() {
    fetch('auth/get_user_addresses.php')
        .then(response => response.json())
        .then(data => {
            const addressSelect = document.getElementById('userAddressSelect');
            const customerAddress = document.getElementById('customerAddress');
            
            if (addressSelect && data.addresses && data.addresses.length > 0) {
                // El usuario tiene direcciones guardadas
                addressSelect.innerHTML = '';
                data.addresses.forEach(address => {
                    const option = document.createElement('option');
                    option.value = address.street;
                    option.textContent = `${address.street}, ${address.city}, ${address.state}`;
                    if (address.is_default) {
                        option.selected = true;
                    }
                    addressSelect.appendChild(option);
                });
                
                // Añadir opción para nueva dirección
                const newOption = document.createElement('option');
                newOption.value = 'new';
                newOption.textContent = '+ Añadir nueva dirección';
                addressSelect.appendChild(newOption);
                
                // Mostrar selector y ocultar input
                addressSelect.style.display = 'block';
                if (customerAddress) {
                    customerAddress.style.display = 'none';
                    // Sincronizar el valor con el input oculto para validación
                    customerAddress.value = addressSelect.value;
                }
                
                // Evento para cambios en el selector
                addressSelect.addEventListener('change', function() {
                    if (this.value === 'new') {
                        // Ocultar el select y mostrar el input
                        this.style.display = 'none';
                        if (customerAddress) {
                            customerAddress.style.display = 'block';
                            customerAddress.value = '';
                            customerAddress.focus();
                        }
                    } else {
                        // Actualizar el valor del input oculto
                        if (customerAddress) {
                            customerAddress.value = this.value;
                        }
                        // Validar campos del checkout
                        if (typeof validateCheckoutFields === 'function') {
                            validateCheckoutFields();
                        }
                    }
                });
                
                // Disparar validación inicial
                if (typeof validateCheckoutFields === 'function') {
                    validateCheckoutFields();
                }
            } else {
                // El usuario no tiene direcciones guardadas
                if (addressSelect) {
                    addressSelect.style.display = 'none';
                }
                if (customerAddress) {
                    customerAddress.style.display = 'block';
                }
            }
        })
        .catch(error => {
            // En caso de error, mostrar el input normal
            const addressSelect = document.getElementById('userAddressSelect');
            const customerAddress = document.getElementById('customerAddress');
            if (addressSelect) {
                addressSelect.style.display = 'none';
            }
            if (customerAddress) {
                customerAddress.style.display = 'block';
            }
        });
}

// Función para cargar teléfonos del usuario
function loadUserPhones() {
    fetch('auth/get_user_phones.php')
        .then(response => response.json())
        .then(data => {
            const phoneSelect = document.getElementById('userPhoneSelect');
            const customerPhone = document.getElementById('customerPhone');
            
            if (phoneSelect && data.phones && data.phones.length > 0) {
                // El usuario tiene teléfonos guardados
                phoneSelect.innerHTML = '';
                data.phones.forEach(phone => {
                    const option = document.createElement('option');
                    option.value = phone.phone_number;
                    option.textContent = phone.phone_number;
                    if (phone.is_default) {
                        option.selected = true;
                    }
                    phoneSelect.appendChild(option);
                });
                
                // Añadir opción para nuevo teléfono
                const newOption = document.createElement('option');
                newOption.value = 'new';
                newOption.textContent = '+ Añadir nuevo teléfono';
                phoneSelect.appendChild(newOption);
                
                // Mostrar selector y ocultar input
                phoneSelect.style.display = 'block';
                if (customerPhone) {
                    customerPhone.style.display = 'none';
                    // Sincronizar el valor con el input oculto para validación
                    customerPhone.value = phoneSelect.value;
                }
                
                // Evento para cambios en el selector
                phoneSelect.addEventListener('change', function() {
                    if (this.value === 'new') {
                        // Ocultar el select y mostrar el input
                        this.style.display = 'none';
                        if (customerPhone) {
                            customerPhone.style.display = 'block';
                            customerPhone.value = '';
                            customerPhone.focus();
                        }
                    } else {
                        // Actualizar el valor del input oculto
                        if (customerPhone) {
                            customerPhone.value = this.value;
                        }
                        // Validar campos del checkout
                        if (typeof validateCheckoutFields === 'function') {
                            validateCheckoutFields();
                        }
                    }
                });
                
                // Disparar validación inicial
                if (typeof validateCheckoutFields === 'function') {
                    validateCheckoutFields();
                }
            } else {
                // El usuario no tiene teléfonos guardados
                if (phoneSelect) {
                    phoneSelect.style.display = 'none';
                }
                if (customerPhone) {
                    customerPhone.style.display = 'block';
                }
            }
        })
        .catch(error => {
            // En caso de error, mostrar el input normal
            const phoneSelect = document.getElementById('userPhoneSelect');
            const customerPhone = document.getElementById('customerPhone');
            if (phoneSelect) {
                phoneSelect.style.display = 'none';
            }
            if (customerPhone) {
                customerPhone.style.display = 'block';
            }
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