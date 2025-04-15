/*-----------------------------------------------------------------
Theme Name: Fresheat
Author: Gramentheme
Author URI: https://themeforest.net/user/gramentheme 
Version: 1.0.0 
Description: Fresheat food & Restaurant Html Template  <

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
let cart = []; // Inicializa vacío SÓLO como fallback
try {
    const storedCart = localStorage.getItem('cart');
    if (storedCart) {
        cart = JSON.parse(storedCart);
        if (!Array.isArray(cart)) { // Validar si es un array
             console.error("Error: El carrito recuperado NO es un array. Reseteando.");
             cart = [];
        }
    }
} catch (e) {
    console.error("Error al parsear carrito desde localStorage. Reseteando.", e);
    cart = []; // Resetear en caso de error
}
function updateCartDisplay() {
    const $cartItemsDiv = $("#cartItems");
    const $cartCountLink = $("#cart-icon-link"); // Target link for data-count
    const $cartTotal = $("#cartTotal");
    $cartItemsDiv.empty(); // Limpiar antes de repoblar
    let total = 0;
    let itemCount = 0;

    // Asegurarse que 'cart' sea un array (accede a la variable global)
    if (!Array.isArray(cart)) {
         cart = [];
         console.error("Carrito reseteado porque no era un array.");
    }

    cart.forEach((item, index) => {
         // Validar item básico
         if (typeof item !== 'object' || item === null || typeof item.price !== 'number' || typeof item.quantity !== 'number' || typeof item.name !== 'string') {
             console.error("Item inválido en el carrito detectado:", item);
             // Opcional: remover item inválido
             // cart.splice(index, 1);
             // updateCartDisplay(); // Llamada recursiva podría ser peligrosa, mejor solo loguear y saltar
             return; // Saltar este item
         }

        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        itemCount += item.quantity;

        // Descripción más clara en el carrito
        let description = `${item.name} (${item.quantity} x $${item.price.toFixed(2)}) = $${itemTotal.toFixed(2)}`;

        const $cartItem = $('<div class="cart-item"></div>')
            .append($('<span>').text(description)) // Usar .text() para seguridad
            .append('<button class="remove-item-btn" data-index="' + index + '" title="Eliminar Item"><i class="fas fa-times"></i></button>'); // Icono y tooltip
        $cartItemsDiv.append($cartItem);
    });

    // Actualizar contador en el header
    $cartCountLink.attr('data-count', itemCount);

    // Actualizar total
    $cartTotal.text('Total: $' + total.toFixed(2));

    // Guardar en localStorage
    try {
        localStorage.setItem('cart', JSON.stringify(cart));
    } catch (e) {
        console.error("Error al guardar carrito en localStorage:", e);
    }


    // Re-bind (volver a asignar) evento click para botones de eliminar CADA VEZ que se actualiza
    // Es crucial porque los elementos se recrean
    $(".remove-item-btn").off("click").on("click", function () {
        const index = $(this).data("index");
        // Verificar que el índice es válido antes de eliminar
        if (typeof index === 'number' && index >= 0 && index < cart.length) {
             cart.splice(index, 1); // Eliminar del array 'cart' global
             updateCartDisplay(); // Actualizar la vista
        } else {
             console.error("Índice inválido para eliminar:", index);
        }
    });

    // Actualizar estado de botones/proceso de pago (opcional pero recomendado)
    const customerName = $("#customerName").val().trim();
    const customerAddress = $("#customerAddress").val().trim();
    const customerPhone = $("#customerPhone").val().trim();
    const paymentMethod = $("#paymentMethod").val();
    // Habilita proceder solo si hay items, datos del cliente y método de pago
    const canProceed = cart.length > 0 && customerName && customerAddress && customerPhone && paymentMethod;

    // Ejemplo: Podrías habilitar/deshabilitar un botón general de "Proceder al Pago"
    // $('#genericProceedButton').prop('disabled', !canProceed);

}

(function ($) {
    "use strict";


// Inicialización MP (la variable mp debe ser accesible donde se usa)
// Es mejor inicializarla dentro de ready() después de que el SDK cargue
// const mp = new MercadoPago('TU_PUBLIC_KEY_AQUI', { locale: 'es-MX' });

function createMercadoPagoPreference() {
    console.log("Intentando crear preferencia MP...");
   // Validar campos y carrito aquí primero
   const phone = $("#customerPhone").val().trim();
   const address = $("#customerAddress").val().trim();
   const name = $("#customerName").val().trim();

   if (!name || !phone || !address || !Array.isArray(cart) || cart.length === 0 || $("#paymentMethod").val() !== 'mercadopago' ) {
       alert('Completa tu información, asegúrate de tener productos en el carrito y selecciona Mercado Pago.');
       return;
   }

   // Muestra algún feedback al usuario
   $("#wallet_container").html('<p>Procesando pago con Mercado Pago...</p>').show();

   // >>>>> PARTE QUE REQUIERE TU BACKEND <<<<<
   // Simulación - DEBES REEMPLAZAR ESTO CON TU LLAMADA AJAX REAL
   console.warn("Simulando llamada a backend para crear preferencia MP. Debes implementar '/order/createPreference'.");
   setTimeout(() => { // Simula retraso de red
       // alert("La integración con Mercado Pago requiere un servidor (backend) para crear la preferencia de pago.");
        $("#wallet_container").html('<p style="color: orange;">Integración MP pendiente (backend). No se puede proceder.</p>').show();

        // SI LA LLAMADA AJAX FUNCIONARA:
        /*
        const preferenceIdDelBackend = "ID_RECIBIDO_DEL_BACKEND"; // Ejemplo
        if (preferenceIdDelBackend) {
            $("#wallet_container").empty(); // Limpia el contenedor
            try {
                const mp = new MercadoPago('TEST-6283082187716828-031815-84bd184b6202d8827274cdaf7c90688b-440393429', { locale: 'es-MX' }); // Asegúrate que mp esté inicializado
                mp.bricks().create('wallet', 'wallet_container', {
                    initialization: { preferenceId: preferenceIdDelBackend },
                    // callbacks de MP si necesitas manejar éxito/error aquí
                });
            } catch (e) {
                 console.error("Error al renderizar MP Brick:", e);
                 $("#wallet_container").html('<p style="color: red;">Error al mostrar botón de Mercado Pago.</p>');
            }
        } else {
            alert('Error al crear preferencia de Mercado Pago desde el backend.');
            $("#wallet_container").html('<p style="color: red;">Error al crear preferencia MP.</p>');
        }
        */
   }, 1500);
   // >>>>> FIN PARTE BACKEND <<<<<
}

// Función para renderizar botón PayPal
function renderPayPalButton() {
    const containerId = '#paypal-button-container';
    $(containerId).empty().show(); // Limpiar y mostrar contenedor

    // Verificar que el SDK de PayPal esté cargado
    if (typeof paypal === 'undefined' || typeof paypal.Buttons === 'undefined') {
        console.error("PayPal SDK no está listo o no se cargó correctamente.");
        $(containerId).html('<p style="color:red;">Error al cargar PayPal.</p>');
        return;
    }

    try {
        paypal.Buttons({
            createOrder: function (data, actions) {
                console.log("Creando orden PayPal...");
                // Validaciones ANTES de crear la orden
                const phone = $("#customerPhone").val().trim();
                const address = $("#customerAddress").val().trim();
                const name = $("#customerName").val().trim();

                if (!name || !phone || !address || !Array.isArray(cart) || cart.length === 0 || $("#paymentMethod").val() !== 'paypal') {
                    alert('Verifica tu información, carrito y que PayPal esté seleccionado.');
                    // Es crucial rechazar la creación si falla la validación
                    return actions.reject();
                }

                let total = cart.reduce((sum, item) => {
                     // Validación extra del item dentro del reduce
                     if (item && typeof item.price === 'number' && typeof item.quantity === 'number') {
                         return sum + item.price * item.quantity;
                     }
                     return sum;
                }, 0);

                if (total <= 0) {
                    alert('El total del carrito debe ser mayor a cero.');
                    return actions.reject();
                }

                console.log("Total para PayPal:", total.toFixed(2));

                // Crear la orden con los detalles
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: total.toFixed(2),
                            currency_code: 'MXN', // Confirma tu moneda
                            breakdown: { // Detalle es opcional pero recomendado
                                item_total: {
                                    value: total.toFixed(2),
                                    currency_code: 'MXN'
                                }
                                // Podrías añadir shipping, tax, etc. si aplica
                            }
                        },
                        description: 'Tu pedido de Freddy\'s Pizza',
                        items: cart.map(item => ({
                             name: (item.name || 'Producto').substring(0, 127), // Límite PayPal y fallback
                             unit_amount: {
                                  value: (item.price || 0).toFixed(2), // Fallback
                                  currency_code: 'MXN'
                             },
                             quantity: (item.quantity || 1).toString() // Fallback y string
                        })),
                        // Datos de envío (opcional pero recomendado si los tienes)
                        shipping: {
                            name: { full_name: name || 'Cliente Freddy\'s' },
                            address: {
                                address_line_1: address || 'Dirección no proporcionada',
                                // address_line_2: '', // Opcional
                                // admin_area_2: '', // Ciudad
                                // admin_area_1: '', // Estado
                                // postal_code: '',
                                country_code: 'MX'
                            }
                        }
                    }]
                }).catch(err => {
                     console.error("Error en actions.order.create:", err);
                     alert("Hubo un problema al iniciar la orden con PayPal. Intenta de nuevo.");
                     return actions.reject(); // Rechaza si falla la creación
                });
            },
            onApprove: function (data, actions) {
                console.log("PayPal onApprove data:", data);
                return actions.order.capture().then(function (details) {
                    console.log("PayPal Capture Details:", details);
                    let transactionId = details.id;
                    let payerName = details.payer && details.payer.name ? details.payer.name.given_name : 'Cliente';

                    alert(`¡Pago con PayPal exitoso, ${payerName}!\nID Transacción: ${transactionId}`);

                    // >>>>> AQUÍ DEBERÍAS ENVIAR INFO AL BACKEND PARA REGISTRAR EL PEDIDO CONFIRMADO <<<<<
                    // Ejemplo: sendOrderToServer('paypal', details);

                    // Limpiar estado del frontend
                    cart = []; // Limpia array global
                    updateCartDisplay(); // Actualiza vista y localStorage
                    $("#cartPanel").removeClass("open"); // Cierra panel
                    $('#customerName, #customerAddress, #customerPhone, #paymentMethod').val(''); // Limpia formulario
                    $(containerId).hide().empty(); // Oculta botón PayPal

                }).catch(function (err) {
                     console.error('Error en PayPal Capture:', err);
                     alert('Hubo un problema al finalizar tu pago con PayPal. Contacta soporte si el cobro fue realizado.');
                     // Podrías intentar mostrar un mensaje más específico basado en 'err'
                });
            },
            onError: function (err) {
                console.error('Error General Botón PayPal:', err);
                alert('Error al procesar con PayPal. Por favor, verifica tus datos o intenta otro método.');
                // Podrías ofrecer recargar la página o intentar de nuevo
                $(containerId).html('<p style="color:red;">Error PayPal. <a href="#" onclick="renderPayPalButton(); return false;">Reintentar</a></p>');
            },
             onCancel: function (data) {
                 console.log('Pago PayPal cancelado:', data);
                 alert('Has cancelado el pago con PayPal.');
                 // No limpiar el carrito, permitir al usuario reintentar o cambiar método
             }
        }).render(containerId).catch(function (err) { // Captura errores de renderizado
             console.error('Error fatal al renderizar botones de PayPal:', err);
             $(containerId).html('<p style="color:red;">No se pudieron cargar los botones de PayPal.</p>');
        });
    } catch (e) {
        console.error("Excepción al inicializar PayPal Buttons:", e);
        $(containerId).html('<p style="color:red;">Error crítico al cargar PayPal.</p>');
    }
} // Fin renderPayPalButton
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
            meanMenuContainer: '.mobile-menu',
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
            } else {
                $("#header-sticky").removeClass("sticky open");
            }
        });

        /*-----------------------------------
          05. Counterup 
        -----------------------------------*/
        $(".counter-number").counterUp({
            delay: 10,
            time: 1000,
        });

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

        initializeSlider(".banner-slider", ".arrow-prev", ".arrow-next", ".pagination-class");
        initializeSlider(".banner2-slider", ".arrow-prev2", ".arrow-next2", ".pagination-class2");
        initializeSlider(".banner3-slider", ".arrow-prev3", ".arrow-next3", ".pagination-class3");

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
    const $productItem = $(this).closest('.product-item');
    const productId = $productItem.data('product-id');
    const name = $productItem.find('h3').text();
    const basePrice = parseFloat($productItem.data('base-price')); // Leer base price del atributo
    const quantity = parseInt($productItem.find('.qty-input').val());

    // Validaciones básicas
    if (isNaN(basePrice) || isNaN(quantity) || quantity <= 0) {
        alert("Error al obtener datos del producto.");
        return;
    }

    let finalPrice = basePrice; // Precio final a calcular
    let modifierDescriptions = []; // Para construir el nombre detallado

    // --- Modificadores ---
    const $extraCheese = $productItem.find('.modifier-checkbox[id^="extra-queso"]');
    const $cooking = $productItem.find('input[name^="coccion"]:checked');
    const $onion = $productItem.find('input[name^="cebolla"]:checked');
    const $mitad1Select = $productItem.find('select[name^="mitad1"]');
    const $mitad2Select = $productItem.find('select[name^="mitad2"]');

    // 1. Extra Queso
    if ($extraCheese.is(':checked')) {
        const cheesePriceChange = parseFloat($extraCheese.data('price-change')) || 0;
        if (!isNaN(cheesePriceChange)) {
             finalPrice += cheesePriceChange;
             modifierDescriptions.push("Extra Queso");
        }
    }

    // 2. Mitad y Mitad
    if ($mitad1Select.length && $mitad2Select.length) { // Solo si es pizza M/M
        let extraPriceMitades = 0;
        const mitad1Value = $mitad1Select.val();
        const mitad2Value = $mitad2Select.val();

        // Validar que ambas mitades estén seleccionadas
        if (!mitad1Value || !mitad2Value) {
             alert("Por favor, selecciona ambas mitades para la pizza Mitad y Mitad.");
             return; // Detener si no están seleccionadas ambas mitades
        }

        const mitad1OptionPrice = parseFloat($mitad1Select.find('option[value="' + mitad1Value + '"]').data('base-price'));
        const mitad2OptionPrice = parseFloat($mitad2Select.find('option[value="' + mitad2Value + '"]').data('base-price'));

        let desc1 = "Mitad 1: Desconocida";
        let desc2 = "Mitad 2: Desconocida";

        if (!isNaN(mitad1OptionPrice)) {
             extraPriceMitades += Math.max(0, mitad1OptionPrice - basePrice);
             desc1 = `Mitad 1: ${$mitad1Select.find('option:selected').text().replace(/\(\s?\+\d+\s?\)/, '').trim()}`; // Limpia el (+XX)
        }
         if (!isNaN(mitad2OptionPrice)) {
             extraPriceMitades += Math.max(0, mitad2OptionPrice - basePrice);
             desc2 = `Mitad 2: ${$mitad2Select.find('option:selected').text().replace(/\(\s?\+\d+\s?\)/, '').trim()}`; // Limpia el (+XX)
        }
        finalPrice += extraPriceMitades;
        modifierDescriptions.push(desc1); // Añadir descripciones
        modifierDescriptions.push(desc2);
    }

     // 3. Pizza de Chorizo - Validar selección de Cebolla
     const currentProductId = $productItem.data('product-id') || '';
     if (currentProductId === 'chorizo' || currentProductId === 'chorizo-orilla') { // Asegúrate que los product-id sean correctos
         if (!$onion.length || !$onion.val()) { // Si no existe el input o no tiene valor
             alert("Por favor, indica si quieres la pizza de chorizo CON o SIN cebolla.");
             return; // Detener si no se ha seleccionado
         }
     }

    // --- Nombre Detallado ---
    let detailedName = name;
    if (modifierDescriptions.length > 0) {
        detailedName += ` (${modifierDescriptions.join(', ')})`;
    }
    // Añadir otras opciones seleccionadas que no afectan precio pero sí el nombre
    if ($cooking.length && $cooking.val()) {
        detailedName += `, Cocción: ${$cooking.val()}`;
    }
     if ($onion.length && $onion.val()) { // Solo añadir si tiene valor (relevante para chorizo)
        detailedName += `, Cebolla: ${$onion.val()}`;
     }


    // --- Añadir al Carrito (Agrupando items idénticos) ---
    // Busca si ya existe un item con el MISMO nombre detallado exacto
    const existingCartItemIndex = cart.findIndex(item => item.name === detailedName);

    if (existingCartItemIndex > -1) {
        // Item ya existe, solo aumenta la cantidad
        cart[existingCartItemIndex].quantity += quantity;
    } else {
        // Item nuevo: añade al array 'cart' global
        cart.push({
            // id: productId + '-' + Date.now(), // ID único si necesitas diferenciar configuraciones iguales añadidas en momentos distintos
            originalId: productId, // Guardar ID original por si acaso
            name: detailedName,    // Clave principal para agrupar
            price: finalPrice,     // Precio calculado
            quantity: quantity
        });
    }
    const $cartIcon = $("#cart-icon-link");
    if ($cartIcon.length) {
        // Elige una animación de Animate.css, por ejemplo 'animate__tada' o 'animate__shakeX'
        // Asegúrate de incluir 'animate__animated'
        const animationClass = 'animated tada';
    
        // Añade la clase para iniciar la animación
        $cartIcon.addClass(animationClass);
    
        // IMPORTANTE: Elimina la clase cuando la animación termine
        // para que pueda volver a ejecutarse la próxima vez.
        $cartIcon.one('animationend', function() {
            $(this).removeClass(animationClass);
        });
    
        /* Opcional: Añadir una clase de "resaltado" temporalmente */
        /* const highlightClass = 'cart-item-added-highlight'; // Necesitas definir esta clase en tu CSS
           $cartIcon.addClass(highlightClass);
           setTimeout(() => { $cartIcon.removeClass(highlightClass); }, 600); // Quitar después de 600ms
        */
        }
        updateCartDisplay(); // Actualizar vista del carrito y localStorage
        const mobileBreakpoint = 992;
        if ($(window).width() >= mobileBreakpoint) {    
            $("#cartPanel").addClass("open"); // Abrir panel para feedback visual
        }
    });
    function updateProductPrice(card) {
        const basePrice = parseFloat(card.dataset.basePrice) || 0;
        let totalPrice = basePrice;
    
        const mitadSelectors = card.querySelectorAll('.mitad-selector');
        if (mitadSelectors.length > 0) {
            mitadSelectors.forEach(select => {
                if (select.options && select.selectedIndex >= 0) {
                    const selectedOption = select.options[select.selectedIndex];
                    const optionPrice = parseFloat(selectedOption.dataset.basePrice) || basePrice;
                    const priceDiff = optionPrice - basePrice;
                    totalPrice += priceDiff;
                }
            });
        }
    
        const checkboxes = card.querySelectorAll('.modifier-checkbox');
        checkboxes.forEach(checkbox => {
            const priceChange = parseFloat(checkbox.dataset.priceChange) || 0;
            if (checkbox.checked) {
                totalPrice += priceChange;
            }
        });
    
        const priceElement = card.querySelector('.product-price');
        if (priceElement) {
            priceElement.textContent = totalPrice.toFixed(2);
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
            mitadSelectors.forEach(select => {
                select.addEventListener('change', () => {
                    updateProductPrice(card);
                });
    
                const niceSelect = card.querySelector(`.nice-select[name="${select.name}"]`);
                if (niceSelect) {
                    niceSelect.querySelectorAll('.option').forEach(option => {
                        option.addEventListener('click', () => {
                            const value = option.dataset.value;
                            select.value = value;
                            select.dispatchEvent(new Event('change'));
                            updateProductPrice(card);
                            niceSelect.classList.remove('open');
                            niceSelect.querySelector('.list').style.display = 'none';
                        });
                    });
                }
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
    
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('option') && e.target.closest('.nice-select')) {
            const niceSelect = e.target.closest('.nice-select');
            const select = niceSelect.previousElementSibling;
            if (select && select.classList.contains('mitad-selector')) {
                const value = e.target.dataset.value;
                select.value = value;
                select.dispatchEvent(new Event('change'));
                const card = select.closest('.product-item');
                if (card) updateProductPrice(card);
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
    // Inicialización de Mercado Pago
const mp = new MercadoPago('TEST-6283082187716828-031815-84bd184b6202d8827274cdaf7c90688b-440393429', {
        locale: 'es-MX'
    });
    
    // Pago con Mercado Pago
$("#proceedToMercadoPago").on("click", function () {
        const phone = $("#customerPhone").val().trim();
        const address = $("#customerAddress").val().trim();
        const name = $("#customerName").val().trim();
    
        if (!name || !phone || !address || cart.length === 0) {
            alert('Por favor, completa todos los campos y añade productos al carrito.');
            return;
        }
    
        $.ajax({
            url: '/order/createPreference',
            method: 'POST',
            contentType: 'application/x-www-form-urlencoded',
            data: $.param({
                cart: JSON.stringify(cart),
                phone: phone,
                address: address,
                name: name
            }),
            success: function (data) {
                if (data.preferenceId) {
                    $("#wallet_container").empty();
                    mp.bricks().create('wallet', 'wallet_container', {
                        initialization: { preferenceId: data.preferenceId }
                    });
                } else {
                    alert('Error al procesar el pago con Mercado Pago.');
                }
            },
            error: function (error) {
                console.error('Error:', error);
                alert('Error al procesar el pago con Mercado Pago.');
            }
        });
    });
    });
        

function loader() {
        $(window).on('load', function () {
            $(".preloader").addClass('loaded');
            $(".preloader").delay(600).fadeOut();
        });
    }
    loader();

})(jQuery); // End jQuery