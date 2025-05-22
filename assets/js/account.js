// Account Management Script
$(document).ready(function() {
    // Check if user is logged in
    function checkLoginStatus() {
        const user = JSON.parse(localStorage.getItem('userData'));
        if (user) {
            $('#notLoggedIn').hide();
            $('#loggedIn').show();
            updateUserInfo(user);
            loadUserData();
        } else {
            $('#notLoggedIn').show();
            $('#loggedIn').hide();
        }
    }

    // Update user info in sidebar
    function updateUserInfo(user) {
        $('#userAvatar').attr('src', user.photo_url || 'assets/img/default-avatar.png');
        $('#userName').text(user.name || user.displayName || '');
        $('#userEmail').text(user.email || '');
    }

    // Load user data from backend
    function loadUserData() {
        console.log('Cargando datos del usuario...');
        if (typeof loadAddresses === 'function') {
            loadAddresses();
        }
        if (typeof loadPhones === 'function') {
            loadPhones();
        }
        if (typeof loadOrders === 'function') {
            loadOrders();
        }
    }

    // Tab Navigation
    $('.account-menu li').click(function() {
        const tab = $(this).data('tab');
        $('.account-menu li').removeClass('active');
        $(this).addClass('active');
        $('.account-tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');
    });

    // Event listeners para botones de ver detalles
    $(document).on('click', '.view-order', function() {
        const orderId = $(this).data('order-id');
        if (typeof showOrderDetails === 'function') {
            showOrderDetails(orderId);
        }
        return false;
    });

    // Event listeners para botones de direcciones
    $(document).on('click', '.favorite-btn[data-address-id]', function() {
        const addressId = $(this).data('address-id');
        if (typeof toggleAddressFavorite === 'function') {
            toggleAddressFavorite(addressId);
        }
        return false;
    });

    $(document).on('click', '.edit-btn[data-address-id]', function() {
        const addressId = $(this).data('address-id');
        if (typeof editAddress === 'function') {
            editAddress(addressId);
        }
        return false;
    });

    $(document).on('click', '.delete-btn[data-address-id]', function() {
        const addressId = $(this).data('address-id');
        if (typeof deleteAddress === 'function') {
            deleteAddress(addressId);
        }
        return false;
    });

    // Event listeners para botones de teléfonos
    $(document).on('click', '.favorite-btn[data-phone-id]', function() {
        const phoneId = $(this).data('phone-id');
        if (typeof togglePhoneFavorite === 'function') {
            togglePhoneFavorite(phoneId);
        }
        return false;
    });

    $(document).on('click', '.edit-btn[data-phone-id]', function() {
        const phoneId = $(this).data('phone-id');
        if (typeof editPhone === 'function') {
            editPhone(phoneId);
        }
        return false;
    });

    $(document).on('click', '.delete-btn[data-phone-id]', function() {
        const phoneId = $(this).data('phone-id');
        if (typeof deletePhone === 'function') {
            deletePhone(phoneId);
        }
        return false;
    });

    // Add New Address
    $(document).on('click', '#addAddressBtn', function(e) {
        e.preventDefault();
        console.log('Botón "Añadir Dirección" (#addAddressBtn) clickeado (detectado por delegación).');
        if (typeof addNewAddress === 'function') {
            console.log('Llamando a addNewAddress desde account.js...');
            addNewAddress();
        } else {
            console.error('La función addNewAddress no está definida o no es accesible.');
        }
    });

    // Add New Phone
    $(document).on('click', '#addPhoneBtn', function(e) {
        e.preventDefault();
        console.log('Botón "Añadir Teléfono" (#addPhoneBtn) clickeado (detectado por delegación).');
        if (typeof addNewPhone === 'function') {
            console.log('Llamando a addNewPhone desde account.js...');
            addNewPhone();
        } else {
            console.error('La función addNewPhone no está definida o no es accesible.');
        }
    });

    // Order Filter Listener (Reescrito con jQuery)
    const orderFilterSelect = $('#orderFilter'); // Seleccionar con jQuery
    if (orderFilterSelect.length) { // Verificar si el elemento existe
        console.log('Order Filter Select found, adding listener (jQuery).');
        orderFilterSelect.on('change', function() { // Usar .on('change', ...) de jQuery
            const selectedStatus = $(this).val(); // Obtener valor con jQuery
            console.log(`Order filter changed to: ${selectedStatus}`);
            if (typeof loadOrders === 'function') {
                // Mostrar indicador de carga si es necesario
                const ordersList = $('#ordersList'); // Seleccionar con jQuery
                if(ordersList.length) ordersList.html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>');
                
                loadOrders(selectedStatus); // Llamar a la función global loadOrders
            } else {
                console.error('loadOrders function is not defined.');
            }
        });
    } else {
        console.warn('Order Filter Select (#orderFilter) not found.');
    }

    // View Order Details Listener (delegado - ya debería estar bien)
    $('#ordersList').on('click', '.view-order', function() {
        const orderId = $(this).data('order-id');
        console.log(`View details clicked for order ID: ${orderId} (jQuery)`);
        if (typeof showOrderDetails === 'function') {
            showOrderDetails(orderId);
        } else {
            console.error('showOrderDetails function is not defined.');
        }
    });

    // Initialize
    checkLoginStatus();
}); 