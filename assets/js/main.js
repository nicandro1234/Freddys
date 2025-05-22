/*-----------------------------------------------------------------
Theme Name: Freddy's Pizza
Author: Freddy's Pizza
Version: 1.0.0 
Description: Freddy's Pizza - La Mejor Pizza Artesanal de León, Guanajuato

-------------------------------------------------------------------
JS TABLE OF CONTENTS
-------------------------------------------------------------------

        01. Mobile Menu 
        02. Sidebar Toggle 
        03. Body Overlay  
        04. Sticky Header   
        05. Counterup 
        06. Wow Animation 
        07. Set Background Image Color & Mask  
        08. Banner Slider
        09. Best food items Slider 
        10. Testimonial Slider 
        11. Blog Slider 
        12. Gallery Slider 
        13. Popular Dishes Slider 
        14. Faq Slider     
        15. Client Slider 
        16. Popular Dishes Tab 
        17. MagnificPopup  view 
        18. Back to top   
        19. Progress Bar Animation 
        20. Mouse Cursor  
        21. Time Countdown  
        22. Range slider 
        23. Select input
        24. Quantity Plus Minus
        25. Search Popup
        26. Preloader   
        27. Mitad y Mitad Price Update
        28. Cart Functionality

------------------------------------------------------------------*/
// Inicialización del carrito
if (typeof cart === 'undefined') {
    var cart = [];
}
let mp; // <--- Declarar mp globalmente

// Variable global para controlar si la configuración está cargada
var configLoaded = false;

// Listener para el evento configLoaded
document.addEventListener('configLoaded', function() {
    console.log("[Main] Evento configLoaded recibido en main.js");
    configLoaded = true;

    // Inicializar mp aquí si el SDK y la config están listos
    if (typeof MercadoPago !== 'undefined' && window.config && window.config.MP_PUBLIC_KEY) {
        try {
            mp = new MercadoPago(window.config.MP_PUBLIC_KEY, { locale: 'es-MX' });
            console.log("[Main] Instancia global de MercadoPago (mp) inicializada.");
        } catch (e) {
            console.error("[Main] Error al inicializar instancia global de MercadoPago:", e);
        }
    } else {
        console.warn("[Main] No se pudo inicializar mp globalmente. SDK o MP_PUBLIC_KEY faltante en configLoaded.");
        // Intentar inicializar más tarde si es necesario, o confiar en la carga dinámica
    }

    // Inicializar funciones que dependen de la configuración
    if (typeof initPaymentSDKs === 'function') {
        // initPaymentSDKs podría intentar inicializar mp de nuevo,
        // pero si ya está inicializado aquí, no debería haber problema.
        initPaymentSDKs();
    }
});

try {
    const storedCart = localStorage.getItem('cart');
    if (storedCart) {
        cart = JSON.parse(storedCart);
        if (!Array.isArray(cart)) {
            console.error("Error: El carrito recuperado NO es un array. Reseteando.");
            cart = [];
        }
    }
} catch (e) {
    console.error("Error al parsear carrito desde localStorage. Reseteando.", e);
    cart = [];
}

function updateCartDisplay(newItemIndex = null) {
    const $cartItemsDiv = $("#cartItems");
    const $cartCountLink = $("#cart-icon-link");
    const $cartTotal = $("#cartTotal");
    $cartItemsDiv.empty();
    let total = 0;
    let itemCount = 0;

    if (!Array.isArray(cart)) {
        cart = []; // Asegurar que el carrito sea un array
    }

    cart.forEach((item, index) => {
        // Validación básica del ítem
        if (typeof item !== 'object' || item === null || typeof item.price !== 'number' || typeof item.quantity !== 'number' || typeof item.name !== 'string') {
            console.warn(`[updateCartDisplay] Ítem inválido en el índice ${index}, saltando:`, item);
            return; // Saltar este ítem si no tiene la estructura esperada
        }

        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        itemCount += item.quantity;

        // Nueva estructura para el ítem del carrito
        const $cartItem = $('<div class="cart-item"></div>');

        const $itemInfo = $('<div class="item-info"></div>');
        
        // Separar nombre base de modificadores
        let baseName = item.name;
        let modifiersText = "";
        const modifierMatch = item.name.match(/^(.*?)\s*\((.*)\)$/); // Intenta extraer "Producto Base (Modificadores)"

        if (modifierMatch && modifierMatch[1] && modifierMatch[2]) {
            baseName = modifierMatch[1].trim();
            modifiersText = modifierMatch[2].trim();
        } else {
            // Si no hay paréntesis, todo es el nombre base (ej. complementos)
            baseName = item.name.trim();
        }
        
        $itemInfo.append($('<span class="item-name"></span>').text(baseName));

        if (modifiersText) {
            const $modifiersDiv = $('<div class="item-modifiers"></div>');
            const individualModifiers = modifiersText.split(',').map(mod => mod.trim());
            individualModifiers.forEach(modText => {
                // Para "Mitad X: Pizza (+$Y)" o solo "Extra Queso"
                if (modText.includes("Mitad 1:") || modText.includes("Mitad 2:")) {
                    const mitadDetailMatch = modText.match(/(Mitad\s\d:)\s*(.*?)(?:\s*\(\+\$?(\d+)\))?$/);
                    if (mitadDetailMatch) {
                        let mitadLabel = mitadDetailMatch[1]; // "Mitad 1:" o "Mitad 2:"
                        let pizzaName = mitadDetailMatch[2].trim(); // "La Mamalona"
                        let mitadExtraCost = mitadDetailMatch[3] ? ` (+$${mitadDetailMatch[3]})` : ""; // " (+20)" o ""
                        $modifiersDiv.append($('<span class="modifier-line"></span>').text(`${mitadLabel} ${pizzaName}${mitadExtraCost}`));
                    } else {
                         $modifiersDiv.append($('<span class="modifier-line"></span>').text(modText)); // Fallback
                    }
                } else if (modText.toLowerCase().includes("extra queso")) {
                    const extraQuesoPriceMatch = item.originalId && document.querySelector(`.product-item[data-product-id="${item.originalId}"] .modifier-checkbox[id^="extra-queso"]`);
                    let extraQuesoText = "Extra Queso";
                    if(extraQuesoPriceMatch && extraQuesoPriceMatch.dataset.priceChange){
                        extraQuesoText += ` (+$${extraQuesoPriceMatch.dataset.priceChange})`;
                    }
                    $modifiersDiv.append($('<span class="modifier-line"></span>').text(extraQuesoText));
                } else {
                    $modifiersDiv.append($('<span class="modifier-line"></span>').text(modText));
                }
            });
            $itemInfo.append($modifiersDiv);
        }
        
        $cartItem.append($itemInfo);

        const $itemControls = $('<div class="item-controls"></div>');
        const $quantityControls = $('<div class="item-quantity-controls"></div>')
            .append(`<button class="qty-btn-cart quantity-minus-cart" data-index="${index}" title="Reducir cantidad"><i class="fas fa-minus"></i></button>`)
            .append(`<span class="item-quantity">${item.quantity}</span>`)
            .append(`<button class="qty-btn-cart quantity-plus-cart" data-index="${index}" title="Aumentar cantidad"><i class="fas fa-plus"></i></button>`);
        
        $itemControls.append($quantityControls);
        $itemControls.append($('<span class="item-total-price"></span>').text(`$${itemTotal}`));
        $itemControls.append(`<button class="remove-item-btn text-btn" data-index="${index}" title="Eliminar Item">Eliminar</button>`);
        
        $cartItem.append($itemControls);

        if (index === newItemIndex) {
            $cartItem.addClass('new-item'); // Para la animación de nuevo ítem
            setTimeout(() => {
                $cartItem.removeClass('new-item');
            }, 500); // Duración de la animación (ej. 0.5s)
        }
        
        $cartItemsDiv.append($cartItem);
    });

    $cartCountLink.attr('data-count', itemCount); // Actualizar contador en el ícono del carrito
    $cartTotal.text('Total: $' + total);

    // Guardar carrito en localStorage
    try {
        localStorage.setItem('cart', JSON.stringify(cart));
    } catch (e) {
        console.error("Error al guardar carrito en localStorage:", e);
    }

    // --- Listeners para botones dentro del carrito ---
    $(".remove-item-btn").off("click").on("click", function () {
        const index = $(this).data("index");
        if (typeof index === 'number' && index >= 0 && index < cart.length) {
            const $cartItemToRemove = $(this).closest('.cart-item');
            $cartItemToRemove.addClass('removing-item'); // Clase para animación de salida
            
            setTimeout(() => { // Esperar que termine la animación
                cart.splice(index, 1);
                updateCartDisplay(); // Re-renderizar el carrito
            }, 300); // Duración de la animación (ej. 0.3s)
        }
    });

    $(".quantity-plus-cart").off("click").on("click", function () {
        const index = $(this).data("index");
        if (typeof index === 'number' && index >= 0 && index < cart.length) {
            cart[index].quantity++;
            updateCartDisplay();
        }
    });

    $(".quantity-minus-cart").off("click").on("click", function () {
        const index = $(this).data("index");
        if (typeof index === 'number' && index >= 0 && index < cart.length) {
            if (cart[index].quantity > 1) {
                cart[index].quantity--;
            } else {
                // Si la cantidad es 1 y se presiona "-", eliminar el ítem
                const $cartItemToRemove = $(this).closest('.cart-item');
                $cartItemToRemove.addClass('removing-item');
                setTimeout(() => {
                    cart.splice(index, 1);
                    updateCartDisplay();
                }, 300);
                return; // Salir para no re-renderizar dos veces si se elimina
            }
            updateCartDisplay();
        }
    });
    // Validar campos de checkout y habilitar/deshabilitar opciones de pago
    validateCheckoutFields();
}

// Validar campos de checkout y habilitar/deshabilitar opciones de pago
function validateCheckoutFields() {
    const customerName = $("#customerName").val().trim();
    const customerAddress = $("#customerAddress").val().trim();
    const customerPhone = $("#customerPhone").val().trim();
    const paymentMethod = $("#paymentMethod");
    
    // Verificar que el carrito tenga productos y los campos estén completos
    const fieldsValid = cart.length > 0 && customerName && customerAddress && customerPhone;
    
    // Habilitar o deshabilitar el selector de método de pago
    paymentMethod.prop('disabled', !fieldsValid);
    
    // Si los campos no son válidos, resetear el selector de pago
    if (!fieldsValid && paymentMethod.val()) {
        paymentMethod.val('');
        $("#paypal-button-container, #wallet_container").hide();
    }
    
    // Mostrar mensaje de validación
    const $paymentMessage = $("#payment-validation-message");
    if (!$paymentMessage.length) {
        const message = $('<div id="payment-validation-message" class="payment-validation-message"></div>');
        paymentMethod.after(message);
    }
    
    if (fieldsValid) {
        $("#payment-validation-message").text('').hide();
    } else {
        $("#payment-validation-message").text('Complete todos los campos para continuar con el pago').show();
    }
}

(function ($) {
    "use strict";

// Evento para cuando el DOM está listo
$(document).ready(function() {
    // Inicializar carrito
    updateCartDisplay();
    
    // Listeners para el carrito
    const $cartIcon = $("#cart-icon-link");
    const $cartPanel = $("#cartPanel");
    const $cartCloseBtn = $("#cartCloseBtn");
    
    // Remover eventos anteriores para evitar duplicados
    $cartIcon.off("click");
    $cartCloseBtn.off("click");
    $(document).off("click.cart");
    
    // Abrir carrito
    $cartIcon.on("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log("Click en ícono del carrito");
        
        // Asegurarse de que el panel existe
        if ($cartPanel.length) {
            console.log("Panel del carrito encontrado");
            $cartPanel.addClass("open");
            
            // Forzar el estilo right: 0 directamente
            $cartPanel.css('right', '0');
        } else {
            console.error("Panel del carrito no encontrado");
        }
    });
    
    // Cerrar carrito
    $cartCloseBtn.on("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log("Click en botón cerrar carrito");
        
        if ($cartPanel.length) {
            $cartPanel.removeClass("open");
            $cartPanel.css('right', '-100%');
        }
    });
    
    // Cerrar al hacer clic fuera
    $(document).on("click.cart", function(e) {
        const $target = $(e.target);
        if (
            !$target.closest($cartPanel).length &&         // No dentro del panel
            !$target.closest($cartIcon).length &&         // No dentro del icono del carrito
            !$target.closest('.add-to-cart-btn').length && // <<< AÑADIDO: No dentro de un botón 'Añadir'
            $cartPanel.hasClass("open")                    // Y el panel está abierto
        ) {
            console.log("Click fuera del carrito (y no en botón Añadir)"); // Mensaje actualizado
            $cartPanel.removeClass("open");
            $cartPanel.css('right', '-100%');
        }
    });
    
    // Prevenir que los clicks dentro del panel cierren el carrito
    $cartPanel.on("click", function(e) {
        e.stopPropagation();
    });
    
    // Listener para cambios en los campos del formulario
    $("#customerName, #customerAddress, #customerPhone").on('input', function() {
        validateCheckoutFields();
    });
    
    // Listener para cambios en el método de pago
    $("#paymentMethod").on('change', function() {
        const method = $(this).val();
        
        // Ocultar ambos contenedores primero
        $("#paypal-button-container, #wallet_container").hide();
        
        if (method === 'paypal') {
            renderPayPalButton();
        } else if (method === 'mercadopago') {
            createMercadoPagoPreference();
        }
    });
    
    // Validar campos inicialmente
    validateCheckoutFields();

    // NO cargar el script aquí, asumimos que index.html lo hace.
    // Simplemente verificar si google está listo para llamar a la inicialización.
    // Si no está listo, initMap (el callback) debería llamarlo después.
    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        console.log("[main.js] Google Maps API ya cargada en ready(), inicializando autocompletado.");
        initGoogleMapsAutocomplete();
    } else {
        console.log("[main.js] Google Maps API aún no cargada en ready(). Esperando a initMap.");
    }

    // Manejar el menú de usuario
    initUserMenu();

    // Configurar listeners para el tipo de entrega
    setupDeliveryTypeListeners();
});

// Inicialización MP (la variable mp debe ser accesible donde se usa)
// Es mejor inicializarla dentro de ready() después de que el SDK cargue
// const mp = new MercadoPago('TU_PUBLIC_KEY_AQUI', { locale: 'es-MX' });

function createMercadoPagoPreference() {
    console.log("[createMercadoPagoPreference] Función iniciada.");

    // 1. Verificar si mp está inicializado
    if (typeof mp === 'undefined') {
        console.error("[createMercadoPagoPreference] Error: La instancia 'mp' de MercadoPago no está inicializada.");
        // Intentar inicializarla ahora (como último recurso)
        if (typeof MercadoPago !== 'undefined' && window.config && window.config.MP_PUBLIC_KEY) {
             try {
                mp = new MercadoPago(window.config.MP_PUBLIC_KEY, { locale: 'es-MX' });
                console.warn("[createMercadoPagoPreference] Instancia 'mp' inicializada tardíamente.");
             } catch (e) {
                 console.error("[createMercadoPagoPreference] Error al inicializar 'mp' tardíamente:", e);
                 alert("Error al inicializar Mercado Pago. Refresca la página e intenta de nuevo.");
                 return;
             }
        } else {
            alert("Error: Mercado Pago no está listo. Espera un momento y reintenta.");
            console.error("[createMercadoPagoPreference] No se puede inicializar 'mp', SDK o clave pública faltantes.");
            return;
        }
    }

    // 2. Validar campos y carrito
    const phone = $("#customerPhone").val().trim();
    const address = $("#customerAddress").val().trim();
    const name = $("#customerName").val().trim();

    if (!name || !phone || !address || !Array.isArray(cart) || cart.length === 0) {
        alert('Completa tu información y asegúrate de tener productos en el carrito.');
        console.warn("[createMercadoPagoPreference] Validación fallida: campos o carrito incompletos.");
        // Asegurarse que el contenedor MP esté oculto si falla la validación
        $("#wallet_container").hide().empty();
        return;
    }
    console.log("[createMercadoPagoPreference] Campos y carrito validados.");

    // 3. Mostrar feedback y realizar llamada AJAX
    $("#wallet_container").html('<p>Procesando tu pago con Mercado Pago...</p>').show(); // Mostrar feedback

    $.ajax({
        url: '/order/createPreference', // Asegúrate que esta ruta es correcta
        method: 'POST',
        // Enviar datos como JSON
        contentType: 'application/json; charset=utf-8',
        dataType: 'json', // Esperar una respuesta JSON
        data: JSON.stringify({ // Convertir objeto JS a string JSON
            cart: cart,
            phone: phone,
            address: address,
            name: name
        }),
        success: function (data) {
            console.log("[createMercadoPagoPreference] Respuesta AJAX recibida:", data);
            if (data && data.preferenceId) {
                console.log("[createMercadoPagoPreference] PreferenceId obtenido:", data.preferenceId);
                $("#wallet_container").empty(); // Limpiar contenedor antes de renderizar
                try {
                    // Renderizar el Brick de Wallet usando la instancia global mp
                    mp.bricks().create('wallet', 'wallet_container', {
                        initialization: { preferenceId: data.preferenceId },
                        customization: {
                             texts: {
                                 action: 'Pagar',
                                 valueProp: 'security_details',
                             },
                        }
                    });
                    console.log("[createMercadoPagoPreference] Wallet Brick renderizado.");
                } catch (brickError) {
                    console.error("[createMercadoPagoPreference] Error al renderizar Wallet Brick:", brickError);
                    $("#wallet_container").html('<p style="color:red;">Error al mostrar el botón de pago de Mercado Pago.</p>');
                    alert("Ocurrió un error al mostrar el botón de pago de Mercado Pago.");
                }
            } else {
                console.error('[createMercadoPagoPreference] Error: No se recibió preferenceId en la respuesta.', data);
                 // Mostrar mensaje de error específico si viene del backend
                 const errorMessage = data && data.error ? data.error : 'Error al iniciar el pago con Mercado Pago.';
                 $("#wallet_container").html(`<p style="color:red;">${errorMessage}</p>`);
                 alert(errorMessage);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('[createMercadoPagoPreference] Error AJAX:', textStatus, errorThrown, jqXHR.responseText);
             // Intentar parsear la respuesta de error si es JSON
             let serverError = 'Error de comunicación al intentar pagar con Mercado Pago. Revisa la consola.';
             try {
                 const errorResponse = JSON.parse(jqXHR.responseText);
                 if (errorResponse && errorResponse.error) {
                     serverError = errorResponse.error;
                 }
             } catch(e) { /* No era JSON o estaba mal formado */ }

            $("#wallet_container").html(`<p style="color:red;">${serverError}</p>`);
            alert(serverError);
        }
    });
}

// Función para renderizar botón PayPal
function renderPayPalButton() {
    const containerId = '#paypal-button-container';
    $(containerId).empty().show(); // Limpiar y mostrar contenedor

    // Verificar que los campos obligatorios estén completos
    const phone = $("#customerPhone").val().trim();
    const address = $("#customerAddress").val().trim();
    const name = $("#customerName").val().trim();
    
    if (!name || !phone || !address || !Array.isArray(cart) || cart.length === 0) {
        $(containerId).html('<p>Complete los datos para continuar</p>');
        return;
    }

    // Verificar que el SDK de PayPal esté cargado
    if (typeof paypal === 'undefined' || typeof paypal.Buttons === 'undefined') {
        $(containerId).html('<p>Cargando PayPal...</p>');
        setTimeout(renderPayPalButton, 1000);
        return;
    }

    try {
        paypal.Buttons({
            style: {
                layout: 'horizontal',
                color: 'blue',
                shape: 'rect',
                label: 'pay',
                height: 40
            },
            createOrder: function (data, actions) {
                // Calcular total del carrito
                let total = 0;
                cart.forEach(item => {
                    total += item.price * item.quantity;
                });
                
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: total.toFixed(2)
                        }
                    }]
                });
            },
            onApprove: function (data, actions) {
                return actions.order.capture().then(function (details) {
                    alert('¡Pago completado! ID de transacción: ' + details.id);
                    cart = [];
                    updateCartDisplay();
                    $("#cartPanel").hide();
                });
            }
        }).render(containerId);
    } catch (error) {
        $(containerId).html('<p>Error al cargar PayPal</p>');
    }
}

$(document).ready(function () {
    updateCartDisplay();
   // ... (otras inicializaciones y listeners) ...

   // Inicializar Mercado Pago (solo la variable, el brick se crea después)
   // Asegúrate que el SDK de MP ya esté cargado
   try {
        if (typeof MercadoPago !== 'undefined') {
           // La variable 'mp' podría definirse globalmente o aquí si solo se usa en 'createMercadoPagoPreference'
           // const mp = new MercadoPago('TEST-6283082187716828-031815-84bd184b6202d8827274cdaf7c90688b-440393429', { locale: 'es-MX' });
           console.log("MP SDK listo.");
        } else {
           console.error("Mercado Pago SDK no cargado.");
        }
   } catch(e) {
       console.error("Error inicializando MP:", e);
   }

   // Inicializar PayPal (el renderizado se hace bajo demanda con renderPayPalButton)
    if (typeof paypal === 'undefined') {
        console.error("PayPal SDK no cargado.");
    } else {
        console.log("PayPal SDK listo.");
    }

    // Llamada inicial para mostrar el carrito guardado

        /*-----------------------------------
          01. Mobile Menu  
        -----------------------------------*/
        $('#mobile-menu').meanmenu({
            meanMenuContainer: '.offcanvas__navigation', // Cambiar a .offcanvas__navigation
            meanScreenWidth: "1199",
            meanExpand: ['<i class="far fa-plus"></i>'],
        });

        /*-----------------------------------
          02. Sidebar Toggle  
        -----------------------------------*/
        $(".offcanvas__close,.offcanvas__overlay").on("click", function () {
            $(".offcanvas__info").removeClass("info-open");
            $(".offcanvas__overlay").removeClass("overlay-open");
        });
        $(".sidebar__toggle").on("click", function () {
            $(".offcanvas__info").addClass("info-open");
            $(".offcanvas__overlay").addClass("overlay-open");
        });

        /*-----------------------------------
          03. Body Overlay 
        -----------------------------------*/
        $(".body-overlay").on("click", function () {
            $(".offcanvas__area").removeClass("offcanvas-opened");
            $(".df-search-area").removeClass("opened");;
            $(".body-overlay").removeClass("opened");
        });

        /*-----------------------------------
          04. Sticky Header 
        -----------------------------------*/
        $(window).scroll(function () {
            if ($(this).scrollTop() > 150) {
                $("#header-sticky").addClass("sticky open");
                
                // Aplicar estilos directamente a los elementos de bienvenida y cerrar sesión
                $("#user-welcome-text").css('color', '#010F1C');
                $(".header-right .user-welcome-container .logout-btn").css('color', '#010F1C');
                $(".header-right .user-welcome-container .logout-btn").css('border-color', 'rgba(0, 0, 0, 0.3)');
            } else {
                $("#header-sticky").removeClass("sticky open");
                
                // Restaurar estilos originales
                $("#user-welcome-text").css('color', '#fff');
                $(".header-right .user-welcome-container .logout-btn").css('color', '#fff');
                $(".header-right .user-welcome-container .logout-btn").css('border-color', 'rgba(255, 255, 255, 0.4)');
            }
        });

        /*-----------------------------------
          05. Counterup 
        -----------------------------------*/
        if ($(".counter-number").length > 0) {
            $(".counter-number").counterUp({
                delay: 10,
                time: 1000,
            });
        }

        /*-----------------------------------
          06. Wow Animation 
        -----------------------------------*/
        new WOW().init();

        /*-----------------------------------
          07. Set Background Image & Mask   
        -----------------------------------*/
        if ($("[data-bg-src]").length > 0) {
            $("[data-bg-src]").each(function () {
                var src = $(this).attr("data-bg-src");
                $(this).css("background-image", "url(" + src + ")");
                $(this).removeAttr("data-bg-src").addClass("background-image");
            });
        }

        if ($('[data-mask-src]').length > 0) {
            $('[data-mask-src]').each(function () {
                var mask = $(this).attr('data-mask-src');
                $(this).css({
                    'mask-image': 'url(' + mask + ')',
                    '-webkit-mask-image': 'url(' + mask + ')'
                });
                $(this).addClass('bg-mask');
                $(this).removeAttr('data-mask-src');
            });
        };

        /*-----------------------------------
           08. Banner Slider
        -----------------------------------*/
        function initializeSlider(sliderClass, nextBtnClass, prevBtnClass, paginationClass) {
            const sliderInit = new Swiper(sliderClass, {
                loop: true,
                slidesPerView: 1,
                effect: "fade",
                speed: 2000,
                autoplay: {
                    delay: 10000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: nextBtnClass,
                    prevEl: prevBtnClass,
                },
                pagination: {
                    el: paginationClass,
                    clickable: true,
                    renderBullet: function (index, className) {
                        return '<span class="' + className + '">' + (index + 1) + '</span>';
                    },
                },
            });

            function animated_swiper(selector, init) {
                const animated = function animated() {
                    $(selector + " [data-animation]").each(function () {
                        let anim = $(this).data("animation");
                        let delay = $(this).data("delay");
                        let duration = $(this).data("duration");
                        $(this)
                            .removeClass("anim" + anim)
                            .addClass(anim + " animated")
                            .css({
                                webkitAnimationDelay: delay,
                                animationDelay: delay,
                                webkitAnimationDuration: duration,
                                animationDuration: duration,
                            })
                            .one("animationend", function () {
                                $(this).removeClass(anim + " animated");
                            });
                    });
                };
                animated();
                init.on("slideChange", function () {
                    $(selector + " [data-animation]").removeClass("animated");
                });
                init.on("slideChange", animated);
            }

            animated_swiper(sliderClass, sliderInit);
        }

        initializeSlider(".banner-slider", ".arrow-next", ".arrow-prev", ".pagination-class");
        initializeSlider(".banner2-slider", ".arrow-next2", ".arrow-prev2", ".pagination-class2");
        initializeSlider(".banner3-slider", ".arrow-next3", ".arrow-prev3", ".pagination-class3");

        /*-----------------------------------
            09. Best food items Slider     
        -----------------------------------*/
        if ($('.bestFoodItems-slider').length > 0) {
            const bestFoodSlider = new Swiper(".bestFoodItems-slider", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1499: { slidesPerView: 4 },
                    1399: { slidesPerView: 4 },
                    1199: { slidesPerView: 4 },
                    991: { slidesPerView: 4 },
                    767: { slidesPerView: 3 },
                    575: { slidesPerView: 1 },
                    0: { slidesPerView: 1 },
                },
                pagination: {
                    el: '.bestFoodItems-pagination',
                    clickable: true,
                    bulletClass: 'swiper-pagination-bullet',
                    bulletActiveClass: 'swiper-pagination-bullet-active',
                },
            });
        }

        /*-----------------------------------
            10. Testimonial Slider     
        -----------------------------------*/
        if ($('.testmonialSliderOne').length > 0) {
            const testmonialSliderOne = new Swiper(".testmonialSliderOne", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: ".arrow-next",
                    prevEl: ".arrow-prev",
                },
                breakpoints: {
                    575: { slidesPerView: 1 },
                    0: { slidesPerView: 1 },
                },
            });
        }

        if ($('.testimonialSliderTwo').length > 0) {
            const testimonialSliderTwo = new Swiper(".testimonialSliderTwo", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: ".arrow-next",
                    prevEl: ".arrow-prev",
                },
                breakpoints: {
                    575: { slidesPerView: 1 },
                    0: { slidesPerView: 1 },
                },
            });
        }

        if ($('.testmonialSliderThree').length > 0) {
            const testmonialSliderThree = new Swiper(".testmonialSliderThree", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: ".arrow-next",
                    prevEl: ".arrow-prev",
                },
                breakpoints: {
                    767: { slidesPerView: 2 },
                    575: { slidesPerView: 1 },
                    0: { slidesPerView: 1 },
                },
            });
        }

        /*-----------------------------------
            11. Blog Slider     
        -----------------------------------*/
        if ($('.blogSliderOne').length > 0) {
            const blogSliderOne = new Swiper(".blogSliderOne", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: ".arrow-next",
                    prevEl: ".arrow-prev",
                },
                breakpoints: {
                    1199: { slidesPerView: 3 },
                    767: { slidesPerView: 2 },
                    575: { slidesPerView: 1 },
                    0: { slidesPerView: 1 },
                },
            });
        }

        /*-----------------------------------
           12. Gallery Slider     
       -----------------------------------*/
        if ($('.gallerySliderOne').length > 0) {
            const gallerySliderOne = new Swiper(".gallerySliderOne", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                centerSlides: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    992: { slidesPerView: 4 },
                    767: { slidesPerView: 3 },
                    575: { slidesPerView: 2 },
                    0: { slidesPerView: 1 },
                },
            });
        }

        /*-----------------------------------
            13. Popular Dishes Slider     
        -----------------------------------*/
        if ($('.popularDishesSliderOne').length > 0) {
            const popularDishesSliderOne = new Swiper(".popularDishesSliderOne", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                centerSlides: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    992: { slidesPerView: 3 },
                    767: { slidesPerView: 3 },
                    575: { slidesPerView: 2 },
                    0: { slidesPerView: 1 },
                },
            });
        }

        /*-----------------------------------
            14. Faq Slider     
        -----------------------------------*/
        if ($('.faq-slider').length > 0) {
            const faqSlider = new Swiper(".faq-slider", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                centerSlides: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: ".arrow-next",
                    prevEl: ".arrow-prev",
                },
            });
        }

        /*-----------------------------------
            15. Client Slider     
        -----------------------------------*/
        if ($('.clientSliderOne').length > 0) {
            const clientSliderOne = new Swiper(".clientSliderOne", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                centerSlides: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    992: { slidesPerView: 6 },
                    767: { slidesPerView: 5 },
                    575: { slidesPerView: 3 },
                    0: { slidesPerView: 1 },
                },
            });
        }

        /*-----------------------------------
            16. Popular Dishes Tab       
        -----------------------------------*/
        function deactivateAllTabs() {
            $('.nav-link').removeClass('active').attr('aria-selected', 'false');
            $('.tab-pane').removeClass('active show');
        }

        $('.nav-link').on('click', function () {
            deactivateAllTabs();
            $(this).addClass('active').attr('aria-selected', 'true');
            const target = $(this).data('bs-target');
            if (target) {
                $(target).addClass('active show');
            }
        });

        /*-----------------------------------
            17. MagnificPopup  view    
        -----------------------------------*/
        $(".popup-video").magnificPopup({
            type: "iframe",
            removalDelay: 260,
            mainClass: 'mfp-zoom-in',
        });

        $(".img-popup").magnificPopup({
            type: "image",
            gallery: { enabled: true },
        });

        /*-----------------------------------
           18. Back to top    
        -----------------------------------*/
        $(window).scroll(function () {
            if ($(this).scrollTop() > 20) {
                $("#back-top").addClass("show");
            } else {
                $("#back-top").removeClass("show");
            }
        });
        $("#back-top").click(function () {
            $("html, body").animate({ scrollTop: 0 }, 800);
            return false;
        });

        /*-----------------------------------
            19. Progress Bar Animation 
        -----------------------------------*/
        $('.progress-bar').each(function () {
            var $this = $(this);
            var progressWidth = $this.attr('style').match(/width:\s*(\d+)%/)[1] + '%';
            $this.waypoint(function () {
                $this.css({
                    '--progress-width': progressWidth,
                    'animation': 'animate-positive 1.8s forwards',
                    'opacity': '1'
                });
            }, { offset: '75%' });
        });

        /*-----------------------------------
            20. Mouse Cursor    
        -----------------------------------*/
        function mousecursor() {
            const e = document.querySelector(".cursor-inner");
            const t = document.querySelector(".cursor-outer");
            if (!e || !t) {
                console.log('Error: .cursor-inner o .cursor-outer no encontrados en el DOM');
                return;
            }
            let n, i = 0, o = !1;
            window.onmousemove = function (s) {
                if (!o) {
                    t.style.transform = "translate(" + s.clientX + "px, " + s.clientY + "px)";
                    e.style.transform = "translate(" + s.clientX + "px, " + s.clientY + "px)";
                    n = s.clientY;
                    i = s.clientX;
                }
            };
            $("body").on("mouseenter", "a, .cursor-pointer", function () {
                e.classList.add("cursor-hover");
                t.classList.add("cursor-hover");
            });
            $("body").on("mouseleave", "a, .cursor-pointer", function () {
                if (!($(this).is("a") && $(this).closest(".cursor-pointer").length)) {
                    e.classList.remove("cursor-hover");
                    t.classList.remove("cursor-hover");
                }
            });
            e.style.visibility = "visible";
            t.style.visibility = "visible";
        }
        mousecursor();

        /*-----------------------------------
            21. Time Countdown  
        -----------------------------------*/
        var countdownDate = new Date("2025-12-31T23:59:59").getTime();
        var countdownFunction = setInterval(function () {
            var now = new Date().getTime();
            var distance = countdownDate - now;
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
            $('#days').text(days < 10 ? '0' + days : days);
            $('#hours').text(hours < 10 ? '0' + hours : hours);
            $('#minutes').text(minutes < 10 ? '0' + minutes : minutes);
            $('#seconds').text(seconds < 10 ? '0' + seconds : seconds);
            if (distance < 0) {
                clearInterval(countdownFunction);
                $(".clock-wrapper").html("EXPIRED");
            }
        }, 1000);

        /*-----------------------------------
            22. Range slider 
        -----------------------------------*/
        function getVals() {
            let parent = this.parentNode;
            let slides = parent.getElementsByTagName("input");
            let slide1 = parseFloat(slides[0].value);
            let slide2 = parseFloat(slides[1].value);
            if (slide1 > slide2) {
                let tmp = slide2;
                slide2 = slide1;
                slide1 = tmp;
            }
            let displayElement = parent.getElementsByClassName("rangeValues")[0];
            displayElement.innerHTML = "$" + slide1 + " - $" + slide2;
        }

        let sliderSections = document.getElementsByClassName("range-slider");
        for (let x = 0; x < sliderSections.length; x++) {
            let sliders = sliderSections[x].getElementsByTagName("input");
            for (let y = 0; y < sliders.length; y++) {
                if (sliders[y].type === "range") {
                    sliders[y].oninput = getVals;
                    sliders[y].oninput();
                }
            }
        }

        /*--------------------------------------------------
          23. Select input
        ---------------------------------------------------*/
        if ($('.single-select').length) {
            $('.single-select').each(function () {
                var $select = $(this);
                $select.niceSelect();
                var $niceSelect = $select.next('.nice-select');
                if ($niceSelect.length) {
                    $niceSelect.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': 1
                    });
                }
            });
        }

        /*--------------------------------------------------
          24. Quantity Plus Minus
        ---------------------------------------------------*/
        $(".quantity-plus").each(function () {
            $(this).on("click", function (e) {
                e.preventDefault();
                var $qty = $(this).siblings(".qty-input");
                var currentVal = parseInt($qty.val());
                if (!isNaN(currentVal)) {
                    $qty.val(currentVal + 1);
                }
            });
        });

        $(".quantity-minus").each(function () {
            $(this).on("click", function (e) {
                e.preventDefault();
                var $qty = $(this).siblings(".qty-input");
                var currentVal = parseInt($qty.val());
                if (!isNaN(currentVal) && currentVal > 1) {
                    $qty.val(currentVal - 1);
                }
            });
        });

        /*--------------------------------------------------
          25. Search Popup
        ---------------------------------------------------*/
        const $searchWrap = $(".search-wrap");
        const $navSearch = $(".nav-search");
        const $searchClose = $("#search-close");

        $(".search-trigger").on("click", function (e) {
            e.preventDefault();
            $searchWrap.animate({ opacity: "toggle" }, 500);
            $navSearch.add($searchClose).addClass("open");
        });

        $(".search-close").on("click", function (e) {
            e.preventDefault();
            $searchWrap.animate({ opacity: "toggle" }, 500);
            $navSearch.add($searchClose).removeClass("open");
        });

        function closeSearch() {
            $searchWrap.fadeOut(200);
            $navSearch.add($searchClose).removeClass("open");
        }

        $(document.body).on("click", function (e) {
            closeSearch();
        });

        $(".search-trigger, .main-search-input").on("click", function (e) {
            e.stopPropagation();
        });
        /*24.
        Cart Panel */

// Abrir el panel del carrito
$("#cart-icon-link").on("click", function (e) {
    e.preventDefault();
    $("#cartPanel").toggleClass("open");
});

// Cerrar el panel del carrito
$("#cartCloseBtn").on("click", function () {
    $("#cartPanel").removeClass("open");
});

// Añadir al carrito desde el menú
$(".add-to-cart-btn").on("click", function () {
    console.log("[Add to Cart] Botón clickeado.");
    const $productItem = $(this).closest('.product-item');
    const productId = $productItem.data('product-id');
    const name = $productItem.find('h3').text();
    // finalPrice inicia con el precio base de la tarjeta del producto (ej. la tarjeta de "Mitad y Mitad")
    let finalPrice = parseInt($productItem.data('base-price')); 
    const quantity = parseInt($productItem.find('.qty-input').val());

    console.log(`[Add to Cart] Producto: ${name}, ID: ${productId}, Precio Base Tarjeta: ${finalPrice}, Cantidad: ${quantity}`);

    if (isNaN(finalPrice) || isNaN(quantity) || quantity <= 0) {
        console.error("[Add to Cart] Error al obtener datos del producto (basePrice de tarjeta o quantity).");
        alert("Error al obtener datos del producto.");
        return;
    }

    let modifierDescriptions = [];

    // --- Modificadores ---
    const $extraCheese = $productItem.find('.modifier-checkbox[id^="extra-queso"]');
    const $cooking = $productItem.find('input[name^="coccion"]:checked');
    const $onion = $productItem.find('input[name^="cebolla"]:checked');
    const $mitad1Select = $productItem.find('select[name^="mitad1"]');
    const $mitad2Select = $productItem.find('select[name^="mitad2"]');
    const isMitadYMitad = $mitad1Select.length && $mitad2Select.length;

    // 1. Sumar costos adicionales de las mitades seleccionadas (si es M/M)
    if (isMitadYMitad) {
        const mitad1Value = $mitad1Select.val();
        const mitad2Value = $mitad2Select.val();

        if (!mitad1Value || !mitad2Value) {
             alert("Por favor, selecciona ambas mitades para la pizza Mitad y Mitad.");
             return; 
        }
        
        let desc1 = "Mitad 1: Desconocida";
        let desc2 = "Mitad 2: Desconocida";

        [$mitad1Select, $mitad2Select].forEach(($select, index) => {
            const selectedOptionText = $select.find('option:selected').text();
            const optionValue = $select.val();
            let extraCost = 0;
            const match = selectedOptionText.match(/\(\+\s*(\d+)\s*\)/); 
            if (match && match[1]) {
                extraCost = parseInt(match[1]);
            }
            if (!isNaN(extraCost)) {
                finalPrice += extraCost;
                console.log(`[Add to Cart] Mitad ${index + 1} ('${selectedOptionText}') añadió +${extraCost}. Nuevo finalPrice: ${finalPrice}`);
            }
            if (index === 0) {
                desc1 = `Mitad 1: ${selectedOptionText.replace(/\\(\\s?\\+[\\d\\.]+\\s?\\)/, '').trim()}`;
            } else {
                desc2 = `Mitad 2: ${selectedOptionText.replace(/\\(\\s?\\+[\\d\\.]+\\s?\\)/, '').trim()}`;
            }
        });
        modifierDescriptions.push(desc1); 
        modifierDescriptions.push(desc2);
    }

    // 2. Añadir costo de Extra Queso (al finalPrice ya sea el original o el modificado por M/M)
    if ($extraCheese.is(':checked')) {
        const cheesePriceChange = parseInt($extraCheese.data('price-change')) || 0; 
        if (!isNaN(cheesePriceChange)) {
             finalPrice += cheesePriceChange;
             modifierDescriptions.push("Extra Queso");
             console.log(`[Add to Cart] Extra Queso añadió +${cheesePriceChange}. Nuevo finalPrice: ${finalPrice}`);
        }
    }

    // El resto de la lógica para nombre detallado, añadir al carrito, etc., se mantiene igual,
    // ya que finalPrice ahora será un entero si todas las adiciones son enteras.
    // No se necesita Math.round() si todos los componentes son enteros.

    console.log(`[Add to Cart] Procesado: ${name}, Precio Final para Carrito: ${finalPrice}`);

    let detailedName = name;
    if (modifierDescriptions.length > 0) {
        detailedName += ` (${modifierDescriptions.join(', ')})`;
    }
    if ($cooking.length && $cooking.val()) {
        detailedName += `, Cocción: ${$cooking.val()}`;
    }
    if ($onion.length && $onion.val()) {
        detailedName += `, Cebolla: ${$onion.val()}`;
    }

    const currentProductId = $productItem.data('product-id') || '';
     if (currentProductId === 'chorizo' || currentProductId === 'chorizo-orilla') {
         if (!$onion.length || !$onion.val()) {
             alert("Por favor, indica si quieres la pizza de chorizo CON o SIN cebolla.");
             return; 
         }
     }

    const existingCartItemIndex = cart.findIndex(item => item.name === detailedName);
    let newItemIndex;
    if (existingCartItemIndex > -1) {
        cart[existingCartItemIndex].quantity += quantity;
        newItemIndex = existingCartItemIndex;
    } else {
        cart.push({
            originalId: productId,
            name: detailedName,
            price: finalPrice, // Guardar el precio final (ya debería ser entero)
            quantity: quantity
        });
        newItemIndex = cart.length - 1;
    }

    // Animar el ícono del carrito
    const $cartIcon = $("#cart-icon-link");
    if ($cartIcon.length) {
        const animationClass = 'animated tada'; // Puedes cambiar 'tada' por otra animación si prefieres
        $cartIcon.removeClass(animationClass); // Quitarla por si acaso ya estaba
        // Forzar reflujo para reiniciar animación si se hace clic rápido
        void $cartIcon[0].offsetWidth;
        $cartIcon.addClass(animationClass);
        // Opcional: quitar la clase después de que termine la animación
        $cartIcon.one('animationend', function() {
            $(this).removeClass(animationClass);
        });
        console.log("[Add to Cart] Animación añadida al ícono del carrito.");
    }

    // --- Actualizar y Abrir Carrito ---
    updateCartDisplay(newItemIndex);
    console.log("[Add to Cart] updateCartDisplay llamado.");

    // Abrir automáticamente SOLO en escritorio
    const mobileBreakpoint = 992; // Asegúrate que este valor sea correcto para tu diseño
    if ($(window).width() >= mobileBreakpoint) {
        $("#cartPanel").addClass("open");
        console.log("[Add to Cart] Clase 'open' añadida a #cartPanel (Desktop).");
        
        // Forzar un reflow mínimo
        void document.getElementById('cartPanel').offsetWidth;

        // Aplicar el estilo final después del reflow
        $('#cartPanel').css('right', '0'); 
        console.log("[Add to Cart] Estilo 'right: 0' aplicado a #cartPanel (Desktop).");
    } else {
        console.log("[Add to Cart] No se abre automáticamente en móvil.");
    }
});

function updateProductPrice(card) {
    let totalPrice = parseInt(card.dataset.basePrice) || 0;
    console.log(`[updateProductPrice] Iniciando. Precio base de tarjeta: ${totalPrice}`);
    const isMitadYMitad = card.querySelectorAll('.mitad-selector').length > 0;

    if (isMitadYMitad) {
        console.log("[updateProductPrice] Es Mitad y Mitad. Calculando precio de mitades...");
        const mitadSelectors = card.querySelectorAll('.mitad-selector');
        mitadSelectors.forEach((select, index) => {
            if (select.options && select.selectedIndex > 0 && select.value !== "") { 
                const selectedOptionText = select.options[select.selectedIndex].text;
                console.log(`[updateProductPrice] Mitad ${index + 1} seleccionada: ${selectedOptionText} (valor: ${select.value})`);
                let extraCost = 0;
                const match = selectedOptionText.match(/\(\+\s*(\d+)\s*\)/); 
                if (match && match[1]) {
                    extraCost = parseInt(match[1]);
                    console.log(`[updateProductPrice] Costo extra extraído de texto: ${extraCost} para ${selectedOptionText}`);
                } else {
                    console.log(`[updateProductPrice] No se encontró costo extra en texto: ${selectedOptionText}`);
                }
                if (!isNaN(extraCost)) {
                    totalPrice += extraCost;
                }
            } else {
                 console.log(`[updateProductPrice] Selector de mitad ${index + 1} no tiene una opción válida seleccionada o es placeholder.`);
            }
        });
        console.log(`[updateProductPrice] Precio después de calcular mitades: ${totalPrice}`);
    }

    const checkboxes = card.querySelectorAll('.modifier-checkbox');
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            const priceChange = parseInt(checkbox.dataset.priceChange) || 0;
            if (!isNaN(priceChange)) {
                totalPrice += priceChange;
                console.log(`[updateProductPrice] Checkbox ${checkbox.id} añadió: ${priceChange}. Nuevo total: ${totalPrice}`);
            }
        }
    });

    const priceElement = card.querySelector('.product-price');
    if (priceElement) {
        priceElement.textContent = totalPrice; 
        console.log(`[updateProductPrice] Precio visual en tarjeta actualizado a: ${totalPrice}`);
    } else {
        console.log("[updateProductPrice] No se encontró .product-price para actualizar en la tarjeta.");
    }
}

function setupProductEvents() {
    document.querySelectorAll('.product-item').forEach(card => {
        const checkboxesAndRadios = card.querySelectorAll('.modifier-checkbox, .modifier-radio');
        checkboxesAndRadios.forEach(modifier => {
            modifier.addEventListener('change', () => {
                updateProductPrice(card);
            });
        });

        const mitadSelectors = card.querySelectorAll('.mitad-selector');
        mitadSelectors.forEach(originalSelect => {
            originalSelect.addEventListener('change', () => {
                console.log(`[Mitad Change Original Event] <select> id: ${originalSelect.id}, valor: ${originalSelect.value} cambió. Llamando a updateProductPrice.`);
                updateProductPrice(card);
            });
        });
    });
}

document.addEventListener('change', (e) => {
    if (e.target.classList.contains('modifier-checkbox')) {
        const card = e.target.closest('.product-item');
        if (card) {
            updateProductPrice(card);
        }
    }
});

// Listener global para clics en opciones de NiceSelect
document.addEventListener('click', (e) => {
    const targetOption = e.target.closest('.option'); 
    if (targetOption && targetOption.closest('.nice-select')) {
        const niceSelect = targetOption.closest('.nice-select');
        let originalSelect = niceSelect.previousElementSibling;
        if (originalSelect && originalSelect.tagName !== 'SELECT') {
             originalSelect = $(niceSelect).prevAll('select').first()[0]; 
        }

        if (originalSelect && originalSelect.classList.contains('mitad-selector')) {
            const value = targetOption.dataset.value;
            const oldValue = originalSelect.value; // Guardar valor anterior para log
            
            originalSelect.value = value; // Actualizar el valor del select original

            console.log(`[NiceSelect Global Click] Opción '${value}' clickeada para <select> id: ${originalSelect.id}. (Valor anterior: '${oldValue}').`);

            // Encontrar la tarjeta (product-item) contenedora y llamar a updateProductPrice directamente
            const card = originalSelect.closest('.product-item');
            if (card) {
                console.log(`[NiceSelect Global Click] Tarjeta encontrada. Llamando a updateProductPrice directamente para card con ID: ${card.dataset.productId}`);
                updateProductPrice(card);
            } else {
                console.error(`[NiceSelect Global Click] No se pudo encontrar .product-item para el select ${originalSelect.id}`);
            }
            // Ya no se dispara el evento 'change' desde aquí para este propósito, se llama a la función directamente.
        }
    }
});

document.querySelectorAll('.filter-btn').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');

        const filter = button.dataset.filter;
        const filterMap = {
            'pizzas': 'menu-category-pizzas',
            'orilla': 'menu-category-orilla',
            'complementos': 'menu-category-complementos'
        };
        const categoryClass = filterMap[filter] || `menu-category-${filter}`;

        document.querySelectorAll('.category-block').forEach(block => {
            if (block.classList.contains(categoryClass)) {
                block.style.display = 'block';
                block.classList.remove('hidden');
                setTimeout(() => {
                    block.classList.add('visible');
                    adjustContainerHeight();
                }, 10);
            } else {
                block.classList.remove('visible');
                setTimeout(() => {
                    block.classList.add('hidden');
                    block.style.display = 'none';
                }, 500);
            }
        });
    });
});

function adjustContainerHeight() {
    const container = document.querySelector('.menu-items-container');
    const visibleBlock = document.querySelector('.category-block.visible');
    if (container && visibleBlock) {
        const height = visibleBlock.offsetHeight;
        container.style.height = `${height}px`;
        
        // Asegurarse de que todos los elementos sean visibles
        const items = visibleBlock.querySelectorAll('.product-item');
        items.forEach(item => {
            item.style.display = 'block';
            item.style.opacity = '1';
        });
    }
}

function initializeFilters() {
    document.querySelectorAll('.category-block').forEach(block => {
        block.classList.add('hidden');
        block.style.display = 'none';
    });

    const defaultFilterBtn = document.querySelector('.filter-btn[data-filter="pizzas"]');
    if (defaultFilterBtn) {
        defaultFilterBtn.classList.add('active');
        const pizzaBlock = document.querySelector('.menu-category-pizzas');
        if (pizzaBlock) {
            pizzaBlock.style.display = 'block';
            pizzaBlock.classList.remove('hidden');
            setTimeout(() => {
                pizzaBlock.classList.add('visible');
                adjustContainerHeight();
            }, 10);
        }
    }

    // Ajustar altura del contenedor después de que todo esté cargado
    window.addEventListener('load', function() {
        adjustContainerHeight();
    });

    // Ajustar altura cuando cambie el tamaño de la ventana
    window.addEventListener('resize', function() {
        adjustContainerHeight();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.product-item').forEach(card => updateProductPrice(card));
    $('.single-select').niceSelect();
    setupProductEvents();
    initializeFilters();
});

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initializeFilters();
}

// Listeners para formularios y pago (añadir o reemplazar si ya tenías algo similar)

// Mostrar/ocultar botones de pago según selección
$("#paymentMethod").on('change', function() {
   const selectedMethod = $(this).val();
   // Oculta y limpia todos los contenedores de pago primero
   $("#paypal-button-container").hide().empty();
   $("#wallet_container").hide().empty();
   // $("#cashPaymentBtn").hide(); // Si tienes botón de efectivo

   if (selectedMethod === 'paypal') {
       $("#paypal-button-container").show();
       if (typeof renderPayPalButton === "function") {
            renderPayPalButton(); // Llama a la función que renderiza el botón PP
       } else { console.error("Función renderPayPalButton no definida");}
   } else if (selectedMethod === 'mercadopago') {
        $("#wallet_container").show();
         // IMPORTANTE: El renderizado del botón de MP se hará DESPUÉS de crear
         // la preferencia en el backend, usualmente al hacer clic en un botón
         // intermedio como "Pagar con Mercado Pago".
         // Por ahora, solo mostramos el contenedor. Podrías añadir un botón aquí.
         if (typeof createMercadoPagoPreference !== "function") {
            console.error("Función createMercadoPagoPreference no definida");
            $("#wallet_container").html('<p style="color:red;">Error: falta función MP</p>');
         } else {
            // Podrías añadir un botón que llame a createMercadoPagoPreference()
            // Ejemplo: $('<button class="theme-btn">Pagar con Mercado Pago</button>').appendTo('#wallet_container').on('click', createMercadoPagoPreference);
            // O simplemente mostrar un texto indicativo
            $("#wallet_container").html('<p>Haz clic en "Pagar con MP" (botón no implementado) para continuar.</p>'); // Placeholder
         }

   } else if (selectedMethod === 'cash') {
        // $("#cashPaymentBtn").show(); // Muestra botón de efectivo si existe
   }
   if (typeof updateCartDisplay === "function") updateCartDisplay(); // Re-evaluar estado del pago
});

// Validar campos del cliente al cambiar
$('#customerName, #customerAddress, #customerPhone').on('input', function() {
    if (typeof updateCartDisplay === "function") updateCartDisplay(); // Re-evaluar estado del pago
});

// Asegurarse de que los listeners y la UI se actualicen cuando se muestra el panel del carrito
const cartPanelObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === "style" && $("#cartPanel").is(":visible")) {
            console.log("Cart panel visible, re-validando y configurando listeners de entrega.");
            validateCheckoutFields();
            setupDeliveryTypeListeners();
        }
    });
});
if ($("#cartPanel").length) {
    cartPanelObserver.observe(document.getElementById('cartPanel'), { attributes: true });
}

}); // Cierre del $(document).ready() que faltaba o estaba mal colocado

// ... (resto del archivo main.js si existe después del $(document).ready() original) ...


    function loader() {
            $(window).on('load', function () {
                $(".preloader").addClass('loaded');
                $(".preloader").delay(600).fadeOut();
            });
        }
        loader();

    })(jQuery); // End jQuery

    // Inicializar autocompletado de Google Maps
    function initGoogleMapsAutocomplete() {
        if (typeof google === 'undefined') {
            console.error('[Autocomplete] Google Maps API no está cargada');
            return;
        }
        const addressInput = document.getElementById('customerAddress');
        if (!addressInput) {
            console.error('[Autocomplete] No se encontró el campo #customerAddress');
            return;
        }
        // Verificar la clave API desde window.config
        if (typeof window.config === 'undefined' || typeof window.config.GOOGLE_MAPS_API_KEY === 'undefined' || window.config.GOOGLE_MAPS_API_KEY === '') {
            console.error('[Autocomplete] window.config.GOOGLE_MAPS_API_KEY no está definida o vacía.');
            // No continuar si no hay clave
            // return; // Opcional: podrías permitir que falle si la clave se carga tarde
        }

        console.log("[Autocomplete] Intentando inicializar para:", addressInput);
        try {
            const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                types: ['address'],
                componentRestrictions: { country: 'MX' }
            });
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                console.log("[Autocomplete] Lugar seleccionado:", place);
                if (place.geometry) {
                    addressInput.value = place.formatted_address;
                    if (typeof validateCheckoutFields === 'function') {
                        validateCheckoutFields();
                    }
                    const latInput = document.getElementById('latitude');
                    const lngInput = document.getElementById('longitude');
                    if (latInput && lngInput) {
                        latInput.value = place.geometry.location.lat();
                        lngInput.value = place.geometry.location.lng();
                    }
                } else {
                    console.log("[Autocomplete] No se seleccionó una dirección válida.");
                }
            });
            console.log("[Autocomplete] Listener place_changed añadido.");
        } catch (error) {
            console.error('[Autocomplete] Error al inicializar:', error);
        }
    }

    // Manejar el menú de usuario
    function initUserMenu() {
        const userWelcome = document.querySelector('.user-welcome');
        const userDropdown = document.querySelector('.user-dropdown');

        // Verificar si los elementos existen antes de continuar
        if (!userWelcome || !userDropdown) {
            console.warn('[initUserMenu] Elemento .user-welcome o .user-dropdown no encontrado. Omitiendo inicialización del menú de usuario.');
            return; // Salir si no se encuentran los elementos
        }

        // El resto de la lógica solo se ejecuta si los elementos existen
        const userName = userWelcome.textContent.trim();
        const firstName = userName.split(' ')[0];
        userWelcome.textContent = `Bienvenido ${firstName}`;

        if (window.innerWidth >= 992) {
            // ... (lógica escritorio)
             userWelcome.addEventListener('mouseenter', () => {
                userDropdown.classList.add('active');
            });
            
            userWelcome.addEventListener('mouseleave', () => {
                userDropdown.classList.remove('active');
            });
        } else {
            // ... (lógica móvil)
            userWelcome.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });
            
            document.addEventListener('click', (e) => {
                if (!userWelcome.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('active');
                }
            });
        }
    }
