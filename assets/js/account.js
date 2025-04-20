// Account Management Script
$(document).ready(function() {
    // Check if user is logged in
    function checkLoginStatus() {
        const user = JSON.parse(localStorage.getItem('user'));
        if (user) {
            $('#notLoggedIn').hide();
            $('#loggedIn').show();
            updateUserInfo(user);
            loadUserData(user.id);
        } else {
            $('#notLoggedIn').show();
            $('#loggedIn').hide();
        }
    }

    // Update user info in sidebar
    function updateUserInfo(user) {
        $('#userAvatar').attr('src', user.photoURL || 'assets/img/default-avatar.png');
        $('#userName').text(user.displayName);
        $('#userEmail').text(user.email);
    }

    // Load user data from backend
    function loadUserData(userId) {
        // Simulated API calls - Replace with actual API endpoints
        loadOrders(userId);
        loadAddresses(userId);
        loadPhones(userId);
        loadSettings(userId);
    }

    // Tab Navigation
    $('.account-menu li').click(function() {
        const tab = $(this).data('tab');
        $('.account-menu li').removeClass('active');
        $(this).addClass('active');
        $('.account-tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');
    });

    // Orders Management
    function loadOrders(userId) {
        // Simulated orders data
        const orders = [
            {
                id: 'ORD-001',
                date: '2024-03-15',
                total: 299.00,
                status: 'Completado',
                items: [
                    { name: 'La Mamalona', quantity: 1, price: 149.00 },
                    { name: 'Dedos de Queso', quantity: 1, price: 59.00 }
                ]
            },
            // Add more orders as needed
        ];

        const ordersList = $('#ordersList');
        ordersList.empty();

        orders.forEach(order => {
            ordersList.append(`
                <tr>
                    <td>${order.id}</td>
                    <td>${order.date}</td>
                    <td>$${order.total.toFixed(2)}</td>
                    <td><span class="badge bg-success">${order.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary view-order" data-order-id="${order.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    // Address Management
    function loadAddresses(userId) {
        // Simulated addresses data
        const addresses = [
            {
                id: 1,
                title: 'Casa',
                address: 'Fray Daniel Mireles 2514',
                city: 'León',
                state: 'Guanajuato',
                zip: '37150',
                isFavorite: true
            },
            // Add more addresses as needed
        ];

        const addressesList = $('#addressesList');
        addressesList.empty();

        addresses.forEach(address => {
            addressesList.append(`
                <div class="address-card ${address.isFavorite ? 'favorite' : ''}">
                    <div class="address-actions">
                        <button class="favorite-btn" data-address-id="${address.id}">
                            <i class="fas fa-star"></i>
                        </button>
                        <button class="edit-btn" data-address-id="${address.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="delete-btn" data-address-id="${address.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <h3>${address.title}</h3>
                    <p>${address.address}</p>
                    <p>${address.city}, ${address.state} ${address.zip}</p>
                </div>
            `);
        });
    }

    // Phone Management
    function loadPhones(userId) {
        // Simulated phones data
        const phones = [
            {
                id: 1,
                title: 'Celular',
                number: '4775780426',
                isFavorite: true
            },
            // Add more phones as needed
        ];

        const phonesList = $('#phonesList');
        phonesList.empty();

        phones.forEach(phone => {
            phonesList.append(`
                <div class="phone-item">
                    <div class="phone-info">
                        <h3>${phone.title}</h3>
                        <p>${phone.number}</p>
                    </div>
                    <div class="phone-actions">
                        <button class="favorite-btn ${phone.isFavorite ? 'active' : ''}" data-phone-id="${phone.id}">
                            <i class="fas fa-star"></i>
                        </button>
                        <button class="edit-btn" data-phone-id="${phone.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="delete-btn" data-phone-id="${phone.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `);
        });
    }

    // Settings Management
    function loadSettings(userId) {
        // Simulated settings data
        const settings = {
            emailNotifications: true,
            smsNotifications: false
        };

        $('#emailNotifications').prop('checked', settings.emailNotifications);
        $('#smsNotifications').prop('checked', settings.smsNotifications);
    }

    // Add New Address
    $('#addAddressBtn').click(function() {
        // Show address form modal
        // Implement address form logic
    });

    // Add New Phone
    $('#addPhoneBtn').click(function() {
        // Show phone form modal
        // Implement phone form logic
    });

    // Save Settings
    $('#saveSettings').click(function() {
        const settings = {
            emailNotifications: $('#emailNotifications').is(':checked'),
            smsNotifications: $('#smsNotifications').is(':checked')
        };
        // Save settings to backend
        // Show success message
    });

    // Initialize
    checkLoginStatus();
}); 