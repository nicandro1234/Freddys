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

// Variable global para controlar si la configuración está cargada
var configLoaded = false;

// Listener para el evento configLoaded
document.addEventListener('configLoaded', function() {
    console.log("[Main] Evento configLoaded recibido en main.js");
    configLoaded = true;
    
    // Inicializar funciones que dependen de la configuración
    if (typeof initPaymentSDKs === 'function') {
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
        cart = [];
    }

    cart.forEach((item, index) => {
        if (typeof item !== 'object' || item === null || typeof item.price !== 'number' || typeof item.quantity !== 'number' || typeof item.name !== 'string') {
            return;
        }

        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        itemCount += item.quantity;

        let description = `${item.name} (${item.quantity} x $${item.price.toFixed(2)}) = $${itemTotal.toFixed(2)}`;

        const $cartItem = $('<div class="cart-item"></div>')
            .append($('<span>').text(description))
            .append('<button class="remove-item-btn" data-index="' + index + '" title="Eliminar Item"><i class="fas fa-times"></i></button>');
        
        if (index === newItemIndex) {
            $cartItem.addClass('new-item');
            setTimeout(() => {
                $cartItem.removeClass('new-item');
            }, 500);
        }
        
        $cartItemsDiv.append($cartItem);
    });

    $cartCountLink.attr('data-count', itemCount);
    $cartTotal.text('Total: $' + total.toFixed(2));

    try {
        localStorage.setItem('cart', JSON.stringify(cart));
    } catch (e) {
        // Error al guardar carrito
    }

    $(".remove-item-btn").off("click").on("click", function () {
        const index = $(this).data("index");
        if (typeof index === 'number' && index >= 0 && index < cart.length) {
            const $cartItem = $(this).closest('.cart-item');
            $cartItem.addClass('removing-item');
            
            setTimeout(() => {
                cart.splice(index, 1);
                updateCartDisplay();
            }, 500);
        }
    });

    // Verificar si los campos obligatorios están completos
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
    
    if ($cartIcon.length && $cartPanel.length && $cartCloseBtn.length) {
        // Abrir carrito
        $cartIcon.on("click", function(e) {
            e.preventDefault();
            $cartPanel.addClass("open");
        });
        
        // Cerrar carrito
        $cartCloseBtn.on("click", function() {
            $cartPanel.removeClass("open");
        });
        
        // Cerrar al hacer clic fuera
        $(document).on("click", function(e) {
            if (
                !$(e.target).closest($cartPanel).length && 
                !$(e.target).closest($cartIcon).length && 
                $cartPanel.hasClass("open")
            ) {
                $cartPanel.removeClass("open");
            }
        });
    }
    
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
});

// Inicialización MP (la variable mp debe ser accesible donde se usa)
// Es mejor inicializarla dentro de ready() después de que el SDK cargue
// const mp = new MercadoPago('TU_PUBLIC_KEY_AQUI', { locale: 'es-MX' });

function createMercadoPagoPreference() {
   // Validar campos y carrito aquí primero
   const phone = $("#customerPhone").val().trim();
   const address = $("#customerAddress").val().trim();
   const name = $("#customerName").val().trim();

   if (!name || !phone || !address || !Array.isArray(cart) || cart.length === 0 || $("#paymentMethod").val() !== 'mercadopago' ) {
       alert('Completa tu información, asegúrate de tener productos en el carrito y selecciona Mercado Pago.');
       return;
   }

   // Muestra algún feedback al usuario
   $("#wallet_container").html('<div class="mp-button-container"><button id="mp-checkout-btn" class="mp-checkout-btn">Pagar con Mercado Pago</button></div>').show();
   
   // Agregar evento al botón de MP
   $("#mp-checkout-btn").on('click', function() {
       alert("Integración con backend de Mercado Pago pendiente");
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
    const $productItem = $(this).closest('.product-item');
    const productId = $productItem.data('product-id');
    const name = $productItem.find('h3').text();
    const basePrice = parseFloat($productItem.data('base-price'));
    const quantity = parseInt($productItem.find('.qty-input').val());

    if (isNaN(basePrice) || isNaN(quantity) || quantity <= 0) {
        alert("Error al obtener datos del producto.");
        return;
    }

    let finalPrice = basePrice;
    let modifierDescriptions = [];

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

    let newItemIndex;

    if (existingCartItemIndex > -1) {
        cart[existingCartItemIndex].quantity += quantity;
        newItemIndex = existingCartItemIndex;
    } else {
        cart.push({
            originalId: productId,
            name: detailedName,
            price: finalPrice,
            quantity: quantity
        });
        newItemIndex = cart.length - 1;
    }

    const $cartIcon = $("#cart-icon-link");
    if ($cartIcon.length) {
        const animationClass = 'animated tada';
        $cartIcon.addClass(animationClass);
        $cartIcon.one('animationend', function() {
            $(this).removeClass(animationClass);
        });
    }

    updateCartDisplay(newItemIndex); // Pasar el índice del nuevo artículo
    const mobileBreakpoint = 992;
    if ($(window).width() >= mobileBreakpoint) {    
        $("#cartPanel").addClass("open");
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