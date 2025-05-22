<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'adminconfig.php';
requireAuth(); // Asegura que el usuario esté autenticado.

// --- INICIO: Funciones Auxiliares y Lógica de Procesamiento ---

// (Aquí van todas las funciones PHP existentes: loadIndexDOM, saveIndexDOM, findProductNodeById, ...)
// Helper function to load index.html into DOMDocument
function loadIndexDOM(): ?DOMDocument {
    $indexPath = __DIR__ . '/../index.html';
    $indexContent = @file_get_contents($indexPath);
    if ($indexContent === false) {
        error_log("Error Crítico: No se pudo leer el archivo index.html en loadIndexDOM. Verifica la ruta y permisos: " . $indexPath);
        return null;
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $indexContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING)) {
         error_log("Error Crítico: No se pudo parsear el HTML de index.html en loadIndexDOM.");
         libxml_clear_errors();
         return null;
    }
    libxml_clear_errors();
    libxml_use_internal_errors(false);
    $dom->encoding = 'UTF-8';
    return $dom;
}

// Helper function to save DOMDocument back to index.html
function saveIndexDOM(DOMDocument $dom): bool {
    $indexPath = __DIR__ . '/../index.html';
    error_log("[saveIndexDOM] Intentando guardar en: {$indexPath}"); // <-- LOG ANTES
    $html = $dom->saveHTML();
    // Eliminar la declaración XML si saveHTML la añade (común con loadHTML)
    $html = preg_replace('/^<\?xml.*?\?>\s*/i', '', $html);
    $bytesWritten = @file_put_contents($indexPath, $html);
    if ($bytesWritten === false) {
        error_log("[saveIndexDOM] Error Crítico: file_put_contents falló. Verifica permisos de escritura para el usuario del servidor web en {$indexPath}"); // <-- LOG FALLO
        return false;
    }
    error_log("[saveIndexDOM] Éxito: Se escribieron {$bytesWritten} bytes en {$indexPath}"); // <-- LOG ÉXITO
    return true;
}

// Helper function to find a product node by its data-product-id
function findProductNodeById(DOMXPath $xpath, string $productId): ?DOMElement {
    // Actualizado para buscar la nueva estructura de tarjeta unificada
    $query = sprintf("//div[contains(@class, 'dishes-card') and contains(@class, 'product-item') and @data-product-id='%s']", $productId);
    $nodes = $xpath->query($query);
    if ($nodes === false || $nodes->length === 0) {
        error_log("[findProductNodeById] Producto con ID '{$productId}' no encontrado usando query: {$query}");
        return null;
    }
    if ($nodes->length > 1) {
         error_log("[findProductNodeById] ADVERTENCIA: Múltiples productos encontrados para ID '{$productId}'. Devolviendo el primero.");
     }
    return $nodes->item(0);
}

// Helper function to find the container for a specific category's products
function findCategoryContainerNode(DOMXPath $xpath, string $category): ?DOMElement {
    $query = sprintf('//div[contains(@class, "menu-category-%s")]//div[contains(@class, "dishes-card-wrap") and contains(@class, "style2")]', $category);
    $nodes = $xpath->query($query);
    if ($nodes === false) { /* ... error log ... */ return null; }
    if ($nodes->length === 0) { /* ... error log ... */ return null; }
    if ($nodes->length > 1) { /* ... warning log ... */ }
    $node = $nodes->item(0);
    return ($node instanceof DOMElement) ? $node : null;
}

// Helper function to find category container in index.html (similar to above)
function findCategoryContainerNodeForIndex(DOMXPath $xpath, string $category): ?DOMElement {
    // Busca el div.dishes-card-wrap dentro del .category-block apropiado
    $query = sprintf("//div[contains(@class, 'category-block') and contains(@class, 'menu-category-%s')]/div[contains(@class, 'dishes-card-wrap')]", $category);
    $nodes = $xpath->query($query);
    if ($nodes === false || $nodes->length === 0) {
        error_log("[findCategoryContainerNodeForIndex] Contenedor para categoría '$category' no encontrado usando query: $query");
        return null;
    }
     if ($nodes->length > 1) {
         error_log("[findCategoryContainerNodeForIndex] ADVERTENCIA: Múltiples contenedores encontrados para categoría '$category'. Devolviendo el primero.");
     }
    return $nodes->item(0);
}

// Función para guardar el nuevo orden de los productos en index.html
function saveProductOrder(array $orderData): bool {
    $dom = loadIndexDOM();
    if (!$dom) { /* ... error log ... */ return false; }
    $xpath = new DOMXPath($dom);
    $changesMade = false;
    foreach ($orderData as $categoryKey => $productIds) {
        if (empty($productIds) || $categoryKey === 'undefined' || empty($categoryKey)) { /* ... error log ... */ continue; }
        $categoryContainerNode = findCategoryContainerNodeForIndex($xpath, $categoryKey);
        if (!$categoryContainerNode) { /* ... error log ... */ continue; }
        $fragment = $dom->createDocumentFragment();
        $currentProductNodes = [];
        foreach ($productIds as $productId) {
            $productNode = findProductNodeById($xpath, $productId);
            if ($productNode) $currentProductNodes[$productId] = $productNode;
            else { /* ... error log ... */ }
        }
        $foundProductNodesForCategory = 0;
        foreach ($productIds as $productId) {
            if (isset($currentProductNodes[$productId])) {
                $fragment->appendChild($currentProductNodes[$productId]);
                $foundProductNodesForCategory++;
            }
        }
        if ($foundProductNodesForCategory > 0) {
            $existingItems = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " product-item ")]', $categoryContainerNode);
            if ($existingItems) {
                foreach ($existingItems as $item) {
                    if ($item->parentNode === $categoryContainerNode) {
                         $categoryContainerNode->removeChild($item);
                    }
                }
            }
            $categoryContainerNode->appendChild($fragment);
            /* ... success log ... */
            $changesMade = true;
        } else { /* ... error log ... */ }
    }
    if ($changesMade) {
        if (saveIndexDOM($dom)) { /* ... success log ... */ return true; }
        else { /* ... error log ... */ return false; }
    }
    /* ... no changes log ... */
    return true;
}

// Function to update half-and-half selectors
function updateHalfAndHalfSelectors(DOMDocument $dom, DOMXPath $xpath): bool {
    error_log("[updateHalfAndHalfSelectors] Iniciando actualización de selectores Mitad y Mitad.");
    $changesMadeToSelectors = false;

    // 1. Obtener todas las pizzas base (categoría 'pizzas') disponibles
    $availableBasePizzas = [];
    // ACTUALIZADO: Usar la estructura unificada para encontrar pizzas base
    $queryBasePizzas = '//div[contains(@class, "dishes-card") and contains(@class, "product-item") and @data-category="pizzas" and not(contains(@class, "unavailable"))]';
    $basePizzaNodes = $xpath->query($queryBasePizzas);

    if ($basePizzaNodes === false) {
        error_log("[updateHalfAndHalfSelectors] Error ejecutando XPath para obtener pizzas base.");
        return false; 
    }

    if ($basePizzaNodes->length === 0) {
        error_log("[updateHalfAndHalfSelectors] No se encontraron pizzas base disponibles (categoría 'pizzas') para las opciones.");
        // No necesariamente un error fatal, podría no haber pizzas normales
    }

    foreach ($basePizzaNodes as $node) {
        if (!$node instanceof DOMElement) continue;
        $id = $node->getAttribute('data-product-id');
        $nameNode = $xpath->query('.//div[contains(@class, "dishes-content")]//h3', $node)->item(0);
        $name = $nameNode ? trim($nameNode->textContent) : 'Nombre Desconocido';
        $basePrice = $node->getAttribute('data-base-price');
        $category = $node->getAttribute('data-category');
        if (!empty($id) && !empty($name) && !empty($basePrice)) {
            $availableBasePizzas[$id] = [
                'id' => $id,
            'name' => $name,
            'category' => $category,
                'basePrice' => floatval($basePrice)
            ];
             // log_message("[updateHalfAndHalfSelectors] Pizza Base candidata: ID=$id, Name='$name', Category=$category, BasePrice=$basePrice");
        } else {
             error_log("[updateHalfAndHalfSelectors] Pizza base omitida por datos incompletos: ID='$id', Name='$name', BasePrice='$basePrice'");
        }
    }
    error_log("[updateHalfAndHalfSelectors] Encontradas " . count($availableBasePizzas) . " pizzas base disponibles para opciones."); // Corregido

    // 2. Obtener productos de orilla rellena como referencia de precios (si existen)
    $orillaRefProducts = [];
    // ACTUALIZADO: Usar la estructura unificada para encontrar referencias de orilla
    $queryOrillaRef = '//div[contains(@class, "dishes-card") and contains(@class, "product-item") and @data-category="orilla" and not(contains(@class, "unavailable"))]';
    $orillaRefNodes = $xpath->query($queryOrillaRef);

     if ($orillaRefNodes === false) {
         error_log("[updateHalfAndHalfSelectors] Error ejecutando XPath para obtener productos de orilla.");
         // Continuar sin referencias si falla
     } elseif ($orillaRefNodes->length > 0) {
          foreach ($orillaRefNodes as $node) {
             if (!$node instanceof DOMElement) continue;
             $id = $node->getAttribute('data-product-id');
             $nameNode = $xpath->query('.//div[contains(@class, "dishes-content")]//h3', $node)->item(0);
             $name = $nameNode ? trim($nameNode->textContent) : 'Nombre Desconocido';
             $basePrice = $node->getAttribute('data-base-price');
             $category = $node->getAttribute('data-category');
             $basePizzaId = str_replace('-orilla', '', $id); // Inferir ID base

             if (!empty($id) && !empty($name) && !empty($basePrice) && !empty($basePizzaId)) {
                 $orillaRefProducts[$basePizzaId] = [
                     'id' => $id,
                     'name' => $name,
                     'category' => $category,
                     'basePrice' => floatval($basePrice),
                     'basePizzaId' => $basePizzaId
                 ];
                  // log_message("[updateHalfAndHalfSelectors] Orilla Ref: ID=$id, Name='$name', Category=$category, BasePrice=$basePrice");
             } else {
                 error_log("[updateHalfAndHalfSelectors] Producto de orilla omitido por datos incompletos: ID='$id', Name='$name', BasePrice='$basePrice'");
             }
         }
     }
    error_log("[updateHalfAndHalfSelectors] Encontrados " . count($orillaRefProducts) . " productos de orilla para referencia."); // Corregido

    // 3. Encontrar los productos Mitad y Mitad
    // ACTUALIZADO: Usar estructura unificada para encontrar productos M/M
    $queryHalfAndHalf = '//div[contains(@class, "dishes-card") and contains(@class, "product-item") and (@data-product-id="mitadymitad" or @data-product-id="mitadymitad-orilla")]';
    $halfAndHalfNodes = $xpath->query($queryHalfAndHalf);

    if ($halfAndHalfNodes === false) {
        error_log("[updateHalfAndHalfSelectors] Error ejecutando XPath para obtener productos Mitad y Mitad.");
        return false;
    }
     if ($halfAndHalfNodes->length === 0) {
         error_log("[updateHalfAndHalfSelectors] No se encontraron productos Mitad y Mitad (mitadymitad o mitadymitad-orilla). No se requiere actualización.");
         return true; // No es un error si no existen
     }
    error_log("[updateHalfAndHalfSelectors] Encontrados " . $halfAndHalfNodes->length . " nodos para Mitad y Mitad / Orilla."); // Corregido

    // 4. Iterar sobre cada producto M/M y actualizar sus selectores
    foreach ($halfAndHalfNodes as $hhNode) {
        if (!$hhNode instanceof DOMElement) continue;
        
        $hhProductId = $hhNode->getAttribute('data-product-id');
        $isOrillaVersion = ($hhProductId === 'mitadymitad-orilla');
        $hhBasePrice = floatval($hhNode->getAttribute('data-base-price') ?: 0);
        error_log("[updateHalfAndHalfSelectors] Procesando nodo: {$hhProductId}");

        // Encontrar los selectores dentro del producto M/M actual
        $selectNodes = $xpath->query('.//select[contains(@class, "mitad-selector")]', $hhNode);

        if ($selectNodes->length !== 2) {
             error_log("[updateHalfAndHalfSelectors] ADVERTENCIA: Se esperaban 2 selectores en {$hhProductId}, encontrados: {$selectNodes->length}");
             // Intentar continuar si se encuentra al menos uno, o saltar si no
             if ($selectNodes->length === 0) continue;
         }

        foreach ($selectNodes as $select) {
            if (!$select instanceof DOMElement) continue;
            
            // Guardar selección actual si existe
            $currentSelection = $xpath->evaluate('string(.//option[@selected]/@value)', $select);
            
            // Limpiar opciones existentes (excepto la primera "Selecciona...")
            $existingOptions = $xpath->query('./option[position() > 1]', $select);
            foreach ($existingOptions as $option) {
                $select->removeChild($option);
            }

            // Añadir nuevas opciones basadas en las pizzas base disponibles
            foreach ($availableBasePizzas as $pizzaId => $pizzaData) {
                $option = $dom->createElement('option');
                $option->setAttribute('value', $pizzaId);
                
                // Calcular precio diferencial
                $targetPrice = $pizzaData['basePrice'];
                if ($isOrillaVersion) {
                    // Buscar el precio de la versión orilla correspondiente
                    $targetPrice = $orillaRefProducts[$pizzaId]['basePrice'] ?? $targetPrice; // Usa precio orilla si existe, si no, el base
                }
                
                $priceDifference = $targetPrice - $hhBasePrice;
                $priceDiffText = '';
                if ($priceDifference > 0) {
                    $priceDiffText = sprintf(' (+%.0f)', $priceDifference);
                } elseif ($priceDifference < 0) {
                     $priceDiffText = sprintf(' (%.0f)', $priceDifference); // Indicar si es más barato? O no mostrar nada?
                }

                $option->nodeValue = htmlspecialchars($pizzaData['name'] . $priceDiffText);
                 $option->setAttribute('data-base-price', $targetPrice); // Guardar el precio base de la opción

                // Restaurar selección si coincide
                if ($pizzaId === $currentSelection) {
                    $option->setAttribute('selected', 'selected');
                }
                
                $select->appendChild($option);
                $changesMadeToSelectors = true; // Marcar que se modificaron opciones
            }
        }
    }

    error_log("[updateHalfAndHalfSelectors] Finalizado. Cambios realizados en selectores: " . ($changesMadeToSelectors ? 'Sí' : 'No')); // Corregido
    return true; // Devuelve true incluso si no hubo cambios, la función se ejecutó
}


// Normalizar precios en el DOM existente (puede ser parte de update/add)
function normalizePricesInExistingDOM(DOMDocument $dom, DOMXPath $xpath): bool {
    $query = '//div[contains(concat(" ", normalize-space(@class), " "), " product-item ")]';
    $productNodes = $xpath->query($query);
    if ($productNodes === false) return false;

    $changesMade = false;
    foreach ($productNodes as $node) {
         if (!$node instanceof DOMElement) continue;

         $priceNode = $xpath->query('.//span[contains(@class, "price")]', $node)->item(0);
         $discountPriceNode = $xpath->query('.//span[contains(@class, "discount-price")]', $node)->item(0);
         $basePriceAttr = $node->getAttribute('data-base-price');
         $discountPriceAttr = $node->getAttribute('data-discount-price');

         $basePrice = !empty($basePriceAttr) ? floatval($basePriceAttr) : 0;
         $discountPrice = !empty($discountPriceAttr) ? floatval($discountPriceAttr) : null;

         if ($priceNode) {
             $newPriceHTML = '';
             if ($discountPrice !== null && $discountPrice < $basePrice && $discountPrice > 0) {
                 $newPriceHTML = sprintf('<small>$%s</small> <span class="discount-price">$%s</span>', number_format($basePrice, 2), number_format($discountPrice, 2));
                 $node->setAttribute('data-current-price', $discountPrice);
            } else {
                 $newPriceHTML = sprintf('$%s', number_format($basePrice, 2));
                 $node->setAttribute('data-current-price', $basePrice);
                 // Limpiar el atributo de descuento si no aplica
                 if ($discountPrice !== null) {
                    $node->removeAttribute('data-discount-price');
                 }
             }
             
             // Reemplazar contenido del nodo de precio
             // Primero limpiar hijos existentes
             while ($priceNode->firstChild) {
                $priceNode->removeChild($priceNode->firstChild);
             }
             // Añadir nuevo contenido (parsear fragmento)
             $fragment = $dom->createDocumentFragment();
             if ($fragment->appendXML($newPriceHTML)) {
                $priceNode->appendChild($fragment);
                $changesMade = true;
             }
         }
    }
    return $changesMade;
}


// Obtener menú actual desde el DOM de index.html (simplificado)
function getCurrentMenu(string $filePath = '../index.html'): array {
    error_log('[getCurrentMenu] Iniciando carga y parseo de ' . $filePath);
    if (!file_exists($filePath)) {
        error_log('[getCurrentMenu] Error: El archivo HTML no existe en ' . $filePath);
        return []; // Devolver array vacío en caso de error
    }

    $htmlContent = file_get_contents($filePath);
    if ($htmlContent === false) {
        error_log('[getCurrentMenu] Error: No se pudo leer el archivo HTML en ' . $filePath);
        return []; // Devolver array vacío
    }

    // Normalizar espacios en blanco problemáticos entre atributos y valores
    $htmlContent = preg_replace('#=\\s+"#', '="', $htmlContent); // Cambiado delimitador a #
    $htmlContent = preg_replace('#=\\s+\'#', "='", $htmlContent); // Cambiado delimitador a # y corregido reemplazo para comilla simple

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        error_log('[getCurrentMenu] Error: Falló la carga del HTML.');
        foreach (libxml_get_errors() as $error) {
            error_log('Error LibXML: ' . $error->message);
        }
        libxml_clear_errors();
        return []; // Devolver array vacío
    }
    libxml_clear_errors(); // Limpiar errores aunque cargue bien
    libxml_use_internal_errors(false);

    $xpath = new DOMXPath($dom);
    $menu = [];
    $categoryNames = []; // Para almacenar nombres generados

    // Orden preferido de categorías
    $categoryOrder = ['pizzas', 'orilla', 'complementos']; // Mantener orden deseado

    // 1. Encontrar TODOS los nodos de producto directamente
    $productNodes = $xpath->query('//div[contains(@class, "dishes-card") and contains(@class, "product-item")]');

    if ($productNodes->length === 0) {
        error_log('[getCurrentMenu] No se encontraron nodos de producto (div.dishes-card.product-item).');
        return []; // Devolver array vacío si no hay productos
    }
     error_log('[getCurrentMenu] Encontrados ' . $productNodes->length . ' nodos de producto.');

    // 2. Iterar sobre los productos y extraer datos + categoría
    foreach ($productNodes as $productNode) {
        $productId = $productNode->getAttribute('data-product-id');
        $categoryKey = $productNode->getAttribute('data-category'); // Extraer categoría directamente

        if (empty($productId)) {
            error_log("[getCurrentMenu] Producto saltado: No se encontró data-product-id.");
            continue;
        }
        if (empty($categoryKey)) {
            error_log("[getCurrentMenu] Producto ID '$productId' saltado: No se encontró data-category.");
            continue;
        }

        // Generar nombre de categoría si no existe aún
        if (!isset($categoryNames[$categoryKey])) {
             $categoryNames[$categoryKey] = ucfirst(str_replace('-', ' ', $categoryKey));
        }
        $categoryName = $categoryNames[$categoryKey];

        // Crear entrada de categoría en $menu si no existe
        if (!isset($menu[$categoryKey])) {
            $menu[$categoryKey] = [
                'name' => $categoryName,
                'products' => []
            ];
             error_log("[getCurrentMenu] Categoría '$categoryKey' (\'$categoryName\') inicializada.");
        }

        // Extraer otros datos del producto
        // Nombre (del h3 dentro de dishes-content)
        $name = 'Nombre no encontrado';
        $nameNode = $xpath->query('.//div[contains(@class, "dishes-content")]//h3', $productNode);
        if ($nameNode->length > 0) {
            $name = trim($nameNode->item(0)->textContent);
        }
         if ($name === 'Nombre no encontrado') {
             error_log("[getCurrentMenu] No se pudo encontrar el nombre para el producto ID '$productId' en categoría '$categoryKey'.");
         }


        // Precio base y actual (de atributos data-*)
        $basePrice = $productNode->getAttribute('data-base-price');
        $basePrice = !empty($basePrice) ? floatval($basePrice) : 0.0;
        $currentPrice = $productNode->getAttribute('data-current-price');
        $currentPrice = !empty($currentPrice) ? floatval($currentPrice) : $basePrice;


        // URL de imagen (del src del img dentro de dishes-thumb)
        $imageNode = $xpath->query('.//div[contains(@class, "dishes-thumb")]//img', $productNode);
        $imageUrl = ($imageNode->length > 0) ? $imageNode->item(0)->getAttribute('src') : null;
        $finalImageUrl = null;
        if ($imageUrl) {
             $originalImageUrl = $imageUrl;
             // Convertir a URL absoluta basada en la raíz del sitio si es necesario
             if (!preg_match('/^(https?:)?\\/\\//i', $imageUrl)) {
                 if (substr($imageUrl, 0, 1) === '/') {
                     $finalImageUrl = $imageUrl; // Ya relativa a la raíz
                 } else {
                     // Asumir relativa al directorio base de assets/img/pizzas etc.
                     // Asegurarse de que comience con /
                     $finalImageUrl = '/' . ltrim($imageUrl, '/.');
                 }
             } else {
                 $finalImageUrl = $imageUrl; // Ya absoluta
             }
             // error_log("[getCurrentMenu] Producto ID: $productId, Raw src: \'$originalImageUrl\', Final imageUrl: \'$finalImageUrl\'");
         } else {
             error_log("[getCurrentMenu] No se encontró imagen para el producto ID '$productId' en categoría '$categoryKey'.");
         }

        // Descripción (No presente en la estructura actual, dejar vacío)
        $description = ''; // O buscar en otro atributo si existiera, ej. data-description

        // Disponibilidad: Verificar la clase 'unavailable'
        $classAttr = $productNode->getAttribute('class');
        $isAvailable = strpos($classAttr, 'unavailable') === false; // Es disponible si NO tiene la clase 'unavailable'
        // error_log("[getCurrentMenu Debug] ID: $productId, Class: '$classAttr', isAvailable: " . ($isAvailable ? 'true' : 'false')); // Log de depuración (opcional)

        // Opciones Mitad y Mitad (Lógica existente preservada y ajustada)
        $halfHalfOptions = [];
        if (strpos($productId, 'mitadymitad') !== false) { // Buscar si el ID contiene 'mitadymitad'
             error_log("[getCurrentMenu] Procesando opciones Mitad y Mitad para ID: $productId");
            $selectNodes = $xpath->query('.//select[contains(@class, "mitad-selector")]', $productNode);
             error_log("[getCurrentMenu] Encontrados " . $selectNodes->length . " selectores Mitad y Mitad.");
            foreach ($selectNodes as $selectNode) {
                $options = [];
                $optionNodes = $xpath->query('./option', $selectNode);
                foreach ($optionNodes as $optionNode) {
                    $value = $optionNode->getAttribute('value');
                    $optionName = trim($optionNode->nodeValue); // Nombre de la opción
                    $optionBasePrice = $optionNode->getAttribute('data-base-price'); // Precio base de la opción
                    if (!empty($value)) {
                         $options[] = [
                            'id' => $value, // e.g., 'pepperoni'
                            'name' => $optionName, // e.g., 'Pepperoni' o 'Pepperoni (+10)'
                             'base_price' => $optionBasePrice ? floatval($optionBasePrice) : null // Precio base específico de la opción
                         ];
                    }
                }
                $selectorId = $selectNode->getAttribute('id') ?: $selectNode->getAttribute('name');
                if (strpos($selectorId, 'mitad1') !== false) {
                     $halfHalfOptions['mitad1'] = $options;
                     error_log("[getCurrentMenu] Opciones Mitad1 para $productId: " . count($options));
                 } elseif (strpos($selectorId, 'mitad2') !== false) {
                     $halfHalfOptions['mitad2'] = $options;
                     error_log("[getCurrentMenu] Opciones Mitad2 para $productId: " . count($options));
                 }
            }
             if (empty($halfHalfOptions)) {
                 error_log("[getCurrentMenu] ADVERTENCIA: No se extrajeron opciones para Mitad y Mitad ID: $productId, aunque se esperaba.");
             }
        }

        // Añadir producto a la categoría correspondiente
        $menu[$categoryKey]['products'][$productId] = [
            'id' => $productId,
            'name' => $name,
            'description' => $description,
            'category' => $categoryKey, // Guardar la clave de categoría
            'base_price' => $basePrice,
            'current_price' => $currentPrice,
            'image' => $finalImageUrl,
            'is_available' => $isAvailable,
             'half_and_half_options' => !empty($halfHalfOptions) ? $halfHalfOptions : null // Añadir opciones o null
        ];
    } // Fin foreach $productNodes

     // 3. Reordenar las categorías según $categoryOrder
     $orderedMenu = [];
     foreach ($categoryOrder as $key) {
         if (isset($menu[$key])) {
             $orderedMenu[$key] = $menu[$key];
         }
     }
     // Añadir categorías no especificadas en $categoryOrder al final
     foreach ($menu as $key => $data) {
         if (!isset($orderedMenu[$key])) {
             $orderedMenu[$key] = $data;
         }
     }

     error_log('[getCurrentMenu] Extracción de menú completada. Categorías procesadas: ' . count($orderedMenu));
    // error_log('[getCurrentMenu] Menú extraído: ' . json_encode($orderedMenu)); // Log muy verboso
    return $orderedMenu; // Devolver el menú ordenado
}

// --- FIN Funciones Auxiliares ---

// --- INICIO: Funciones CRUD para index.html (Manipulación DOM) ---

function addProduct(array $productData, ?array $fileData = null): bool {
    error_log("[addProduct PHP] Recibido para añadir: " . print_r($productData, true) . " Archivo: " . print_r($fileData, true));
    
    $productId = $productData['id'] ?? 'prod_' . time() . rand(100, 999); 
    $productData['id'] = $productId; 
    // Corregir el nombre de la key para imagen, debe coincidir con createProductNode/getCurrentMenu
    $imageUrl = ''; // Iniciar vacío
    $productData['image'] = ''; // Usar 'image', no 'image_url'

    // 1. Manejar subida de imagen (ahora actualiza $productData['image'])
    if ($fileData && $fileData['error'] === UPLOAD_ERR_OK) {
        $category = $productData['category'] ?? 'general';
        $categorySlug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($category));
        // Usar ruta relativa correcta desde la raíz para guardar y URL
        $baseUploadDir = '../assets/img/pizzas/'; // Asumiendo que es el directorio principal
        // $categoryUploadDir = $baseUploadDir . $categorySlug . '/'; // O quitar subdirectorios si no se usan
        $uploadDir = $baseUploadDir; // Simplificar si todas van a 'pizzas'
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) { 
                error_log("[addProduct PHP] Error: No se pudo crear directorio: {$uploadDir}");
                return false; 
            }
        }
        // Generar nombre único basado en ID
        $imageFileType = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($imageFileType, $allowedTypes)) {
            error_log("[addProduct PHP] Tipo de archivo no permitido: {$imageFileType}");
            return false;
        }
        $newFileName = $productId . '.' . $imageFileType;
        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileData['tmp_name'], $targetPath)) {
             // Construir URL relativa a la raíz para index.html
             $imageUrl = '/assets/img/pizzas/' . $newFileName; // Ruta desde la raíz
             error_log("[addProduct PHP] Imagen subida con éxito. URL relativa: {$imageUrl}");
             $productData['image'] = $imageUrl; // Guardar URL en los datos del producto
        } else {
             error_log("[addProduct PHP] Error al mover archivo subido.");
             // Continuar sin imagen
             $productData['image'] = null;
        }
    } elseif ($fileData && $fileData['error'] !== UPLOAD_ERR_NO_FILE) {
         error_log("[addProduct PHP] Error de subida de archivo: " . $fileData['error']);
         $productData['image'] = null;
    }

    // 2. Cargar DOM 
    $dom = loadIndexDOM();
    if (!$dom) return false;
    $xpath = new DOMXPath($dom);
    $categoryKey = $productData['category'] ?? 'general';
    
    // 3. Crear el nuevo nodo de producto (ya genera la estructura correcta)
    $newProductNode = createProductNode($dom, $productData); 
    if (!$newProductNode) {
         error_log("[addProduct PHP] Error: No se pudo crear el nodo del producto.");
         return false;
    }

    // 4. Determinar dónde insertar el nodo en la lista plana de productos
    $targetCategory = $categoryKey;
    $referenceNode = null; // Nodo después del cual insertar
    $insertionParent = null; // Padre donde insertar
    $insertBehavior = 'after'; // Por defecto insertar después

    // Buscar el último nodo de la MISMA categoría
    $queryLastSameCategory = sprintf('(//div[contains(@class, "dishes-card") and @data-category="%s"])[last()]', $targetCategory);
    $lastProductOfCategory = $xpath->query($queryLastSameCategory);

    if ($lastProductOfCategory->length > 0) {
        $referenceNode = $lastProductOfCategory->item(0);
        $insertionParent = $referenceNode->parentNode;
        error_log("[addProduct] Encontrado último producto de categoría '$targetCategory' (ID: {$referenceNode->getAttribute('data-product-id')}). Insertando después.");
    } else {
        // Si no hay productos en esta categoría, buscar el último producto de la categoría ANTERIOR
        error_log("[addProduct] No hay productos existentes en categoría '$targetCategory'. Buscando categoría anterior.");
        $categoryOrder = ['pizzas', 'orilla', 'complementos']; // Orden estándar
        $targetIndex = array_search($targetCategory, $categoryOrder);
        $previousCategoryKey = null;

        // Buscar hacia atrás en el orden definido
        for ($i = $targetIndex - 1; $i >= 0; $i--) {
            $prevCatKey = $categoryOrder[$i];
            $queryLastPrevCategory = sprintf('(//div[contains(@class, "dishes-card") and @data-category="%s"])[last()]', $prevCatKey);
            $lastProductOfPrevCategory = $xpath->query($queryLastPrevCategory);
            if ($lastProductOfPrevCategory->length > 0) {
                $referenceNode = $lastProductOfPrevCategory->item(0);
                $insertionParent = $referenceNode->parentNode;
                error_log("[addProduct] Encontrado último producto de categoría anterior '$prevCatKey' (ID: {$referenceNode->getAttribute('data-product-id')}). Insertando después.");
                break; // Encontrado, salir del bucle
            }
        }

        // Si NO se encontró ninguna categoría anterior con productos,
        // significa que esta es la PRIMERA categoría (o la primera con productos).
        // Debemos insertar ANTES del primer producto de la SIGUIENTE categoría existente.
        if (!$referenceNode) {
            error_log("[addProduct] No se encontró categoría anterior con productos. Buscando primera categoría SIGUIENTE.");
            $foundNextCategoryNode = false;
            for ($i = $targetIndex + 1; $i < count($categoryOrder); $i++) {
                $nextCatKey = $categoryOrder[$i];
                $queryFirstNextCategory = sprintf('(//div[contains(@class, "dishes-card") and @data-category="%s"])[1]', $nextCatKey);
                $firstProductOfNextCategory = $xpath->query($queryFirstNextCategory);
                 if ($firstProductOfNextCategory->length > 0) {
                    $referenceNode = $firstProductOfNextCategory->item(0);
                    $insertionParent = $referenceNode->parentNode;
                    $insertBehavior = 'before'; // Insertar ANTES del primero de la siguiente categoría
                    error_log("[addProduct] Encontrado primer producto de categoría siguiente '$nextCatKey' (ID: {$referenceNode->getAttribute('data-product-id')}). Insertando ANTES.");
                    $foundNextCategoryNode = true;
                    break;
                 }
            }
            
            // Si TAMPOCO se encontró una categoría siguiente (significa que esta es la última categoría en añadirse)
            // O si es la única categoría existente.
            // Insertar al final del contenedor principal (padre del último producto encontrado de CUALQUIER categoría)
            if (!$foundNextCategoryNode) {
                error_log("[addProduct] No se encontró categoría siguiente o es la última. Buscando último producto global.");
                $queryLastProductOverall = '(//div[contains(@class, "dishes-card") and contains(@class, "product-item")])[last()]';
                $lastProductOverall = $xpath->query($queryLastProductOverall);
                if ($lastProductOverall->length > 0) {
                    $referenceNode = $lastProductOverall->item(0);
                    $insertionParent = $referenceNode->parentNode;
                    $insertBehavior = 'after'; // Insertar después del último global
                    error_log("[addProduct] Encontrado último producto global (ID: {$referenceNode->getAttribute('data-product-id')}). Insertando después.");
                } else {
                    // Si NO HAY NINGÚN PRODUCTO en absoluto, necesitamos un contenedor por defecto.
                    // Intentar encontrar un contenedor principal conocido, p.ej., el padre del primer <section> con id menu?
                    error_log("[addProduct] ¡No hay productos existentes! Intentando encontrar contenedor principal heurísticamente...");
                    // Ejemplo: Buscar el div que contiene el primer H2 o algo así
                    // $mainContainerQuery = '//section[@id="menu"]//div[contains(@class, "row')] | //body/div[contains(@class, "container')]'; // Consulta de ejemplo
                    // $mainContainer = $xpath->query($mainContainerQuery)->item(0);
                    // if ($mainContainer) { 
                    //    $insertionParent = $mainContainer; 
                    //    $referenceNode = null; // Insertar al principio del contenedor
                    //    $insertBehavior = 'append'; // o 'prepend'?
                    //    error_log("[addProduct] Encontrado contenedor principal heurísticamente. Añadiendo producto.");
                    // } else {
                    //    error_log("[addProduct] Error CRÍTICO: No se pudo encontrar un contenedor para insertar el primer producto.");
                    //    return false;
                    // } 
                     error_log("[addProduct] Error CRÍTICO: No se pudo determinar dónde insertar el producto porque no hay productos existentes y no se encontró un contenedor padre.");
                     return false;
                }
            }
        }
    }

    // 5. Insertar el nodo en la posición calculada
    if ($insertionParent && $newProductNode) {
        if ($insertBehavior === 'after' && $referenceNode) {
            if ($referenceNode->nextSibling) {
                $insertionParent->insertBefore($newProductNode, $referenceNode->nextSibling);
            } else {
                $insertionParent->appendChild($newProductNode);
            }
            error_log("[addProduct] Nodo ID {$productId} insertado DESPUÉS de ID {$referenceNode->getAttribute('data-product-id')}.");
        } elseif ($insertBehavior === 'before' && $referenceNode) {
            $insertionParent->insertBefore($newProductNode, $referenceNode);
            error_log("[addProduct] Nodo ID {$productId} insertado ANTES de ID {$referenceNode->getAttribute('data-product-id')}.");
        } elseif ($insertBehavior === 'append') { // Caso sin productos existentes pero con contenedor encontrado
             $insertionParent->appendChild($newProductNode);
             error_log("[addProduct] Nodo ID {$productId} AÑADIDO al contenedor padre (caso sin productos previos).");
        } else {
            // Fallback: añadir al final del padre si algo falló en la lógica anterior
             $insertionParent->appendChild($newProductNode);
            error_log("[addProduct] Nodo ID {$productId} añadido al final del contenedor padre (Fallback).");
        }
    } else {
        error_log('[addProduct] Error: No se pudo determinar el padre o el nodo a insertar.');
        return false;
    }

    // 6. Guardar cambios y actualizar selectores si es pizza
    $changesMade = true; 
    if ($changesMade) {
        $isPizzaCategory = (strpos($categoryKey, 'pizzas') !== false || strpos($categoryKey, 'orilla') !== false);
        if ($isPizzaCategory) {
            error_log("[addProduct] Categoría de pizza detectada ({$categoryKey}), actualizando selectores M/M...");
            // normalizePricesInExistingDOM($dom, $xpath); // Normalizar precios primero? Puede ser innecesario si M/M lo hace
            updateHalfAndHalfSelectors($dom, $xpath);
        } else {
            error_log("[addProduct] Categoría NO es pizza ({$categoryKey}), no se actualizan selectores M/M.");
        }

        if (saveIndexDOM($dom)) {
            error_log("[addProduct PHP] index.html guardado con éxito después de añadir ID {$productId}.");
            return true;
        } else {
            error_log("[addProduct PHP] Error al guardar index.html después de añadir ID {$productId}.");
            return false;
        }
    }
    
    return false; // No debería llegar aquí si la inserción fue exitosa
}

// Helper function to create a product node programmatically FOR index.html
function createProductNode(DOMDocument $dom, array $productData): ?DOMElement {
    error_log('[createProductNode] Creando nodo DOM para index.html. Producto: ' . ($productData['id'] ?? 'SIN ID'));
    try {
        // --- Extraer y sanear datos --- 
        $productId = htmlspecialchars($productData['id'] ?? 'prod_' . time());
        $productName = htmlspecialchars($productData['name'] ?? 'Sin Nombre');
        $categoryKey = htmlspecialchars($productData['category'] ?? 'unknown');
        $basePrice = floatval($productData['base_price'] ?? 0);
        // Usar current_price si existe, si no, base_price
        $currentPrice = floatval($productData['current_price'] ?? $basePrice);
        // Usar 'image' de los datos procesados por getCurrentMenu/updateProduct
        $imageUrl = $productData['image'] ?? null; 
        // Asegurar que la URL sea relativa a la raíz si no es absoluta y no está vacía
        if ($imageUrl && !preg_match('/^(https?:)?\/\//i', $imageUrl) && substr($imageUrl, 0, 1) !== '/') {
            $imageUrl = '/' . ltrim($imageUrl, './');
        }
        // Usar placeholder si la URL está vacía o es nula
        $finalImageUrl = !empty($imageUrl) ? htmlspecialchars($imageUrl) : '/assets/img/placeholder.png';
        // Disponibilidad (asumir true si no se especifica)
        $isAvailable = $productData['is_available'] ?? true;
        $availabilityClass = !$isAvailable ? 'unavailable' : ''; // Clase para ocultar/marcar si no está disponible


        // --- Crear estructura DOM para index.html --- 
        
        // Contenedor principal: <div class="dishes-card style2 product-item" ...>
        $productDiv = $dom->createElement('div');
        // ELIMINAR CLASES DE COLUMNA DE BOOTSTRAP AQUÍ
        $productDiv->setAttribute('class', "dishes-card style2 product-item {$availabilityClass}"); // Clase `style2` y `product-item` son correctas según SCSS
        $productDiv->setAttribute('data-category', $categoryKey);
        $productDiv->setAttribute('data-product-id', $productId);
        $productDiv->setAttribute('data-base-price', (string)$basePrice);
        $productDiv->setAttribute('data-current-price', (string)$currentPrice); // Usar precio actual calculado

        // Contenedor de imagen: <div class="dishes-thumb">
        $thumbDiv = $dom->createElement('div');
        $thumbDiv->setAttribute('class', 'dishes-thumb');
        $img = $dom->createElement('img');
        $img->setAttribute('src', $finalImageUrl); // Usar URL final (con placeholder si es necesario)
        $img->setAttribute('alt', $productName);
        $thumbDiv->appendChild($img);
        // Añadir forma circular (como en index.html)
        $shapeDiv = $dom->createElement('div');
        $shapeDiv->setAttribute('class', 'circle-shape');
        $shapeImg = $dom->createElement('img');
        $shapeImg->setAttribute('class', 'cir36');
        $shapeImg->setAttribute('src', '/assets/img/food-items/circleShape.png'); // Ruta absoluta desde la raíz
        $shapeImg->setAttribute('alt', 'shape');
        $shapeDiv->appendChild($shapeImg);
        $thumbDiv->appendChild($shapeDiv);
        $productDiv->appendChild($thumbDiv);

        // Contenedor de contenido: <div class="dishes-content">
        $contentDiv = $dom->createElement('div');
        $contentDiv->setAttribute('class', 'dishes-content');

        // Nombre: <a href="#"><h3>...</h3></a>
        $link = $dom->createElement('a');
        $link->setAttribute('href', '#'); 
        $h3 = $dom->createElement('h3', $productName); // No necesita htmlspecialchars aquí si ya se hizo arriba
        $link->appendChild($h3);
        $contentDiv->appendChild($link);

        // Modificadores: <div class="product-modifiers ...">
        $modifiersDiv = $dom->createElement('div');
        $modifiersDiv->setAttribute('class', 'product-modifiers mt-2 mb-3 text-start');
        
        // --- Lógica para añadir modificadores específicos --- 
        $modifierAdded = false; // Flag para saber si se añadió algún modificador

        // Modificador: Extra Queso (Ejemplo si aplica a pizzas/orilla)
        if ($categoryKey === 'pizzas' || $categoryKey === 'orilla') {
            $modGroupQueso = $dom->createElement('div');
            $modGroupQueso->setAttribute('class', 'modifier-group mb-2');
            $inputIdQueso = 'extra-queso-' . $productId;
            $checkboxQueso = $dom->createElement('input');
            $checkboxQueso->setAttribute('class', 'modifier-checkbox');
            $checkboxQueso->setAttribute('type', 'checkbox');
            $checkboxQueso->setAttribute('id', $inputIdQueso);
            $checkboxQueso->setAttribute('name', $inputIdQueso); // name puede ser igual al id
            $checkboxQueso->setAttribute('value', '1');
            $checkboxQueso->setAttribute('data-price-change', '25'); // Precio del extra queso
            $labelQueso = $dom->createElement('label', 'Extra Queso (+$25.00)');
            $labelQueso->setAttribute('class', 'modifier-label');
            $labelQueso->setAttribute('for', $inputIdQueso);
            $modGroupQueso->appendChild($checkboxQueso);
            $modGroupQueso->appendChild($labelQueso);
            $modifiersDiv->appendChild($modGroupQueso);
            $modifierAdded = true;
        }

        // Modificador: Cocción (Ejemplo si aplica a pizzas/orilla)
        if ($categoryKey === 'pizzas' || $categoryKey === 'orilla') {
            $modGroupCoccion = $dom->createElement('div');
            $modGroupCoccion->setAttribute('class', 'modifier-group modifier-radio-group' . ($categoryKey === 'mitadymitad' || $categoryKey === 'mitadymitad-orilla' ? ' mb-2' : '')); // Margen extra si hay M/M
            $labelGroupCoccion = $dom->createElement('label', 'Cocción:');
            $labelGroupCoccion->setAttribute('class', 'group-label');
            $radioContainerCoccion = $dom->createElement('div');
            $radioContainerCoccion->setAttribute('class', 'radio-container');
            
            $radioSuaveId = 'coccion-suave-' . $productId;
            $radioSuave = $dom->createElement('input');
            $radioSuave->setAttribute('class', 'modifier-radio');
            $radioSuave->setAttribute('type', 'radio');
            $radioSuave->setAttribute('id', $radioSuaveId);
            $radioSuave->setAttribute('name', 'coccion-' . $productId); // Mismo name para el grupo
            $radioSuave->setAttribute('value', 'suave');
            $labelSuave = $dom->createElement('label', 'Suave');
            $labelSuave->setAttribute('class', 'modifier-label modifier-radio-label');
            $labelSuave->setAttribute('for', $radioSuaveId);
            
            $radioCrujienteId = 'coccion-crujiente-' . $productId;
            $radioCrujiente = $dom->createElement('input');
            $radioCrujiente->setAttribute('class', 'modifier-radio');
            $radioCrujiente->setAttribute('type', 'radio');
            $radioCrujiente->setAttribute('id', $radioCrujienteId);
            $radioCrujiente->setAttribute('name', 'coccion-' . $productId); // Mismo name
            $radioCrujiente->setAttribute('value', 'crujiente');
            $labelCrujiente = $dom->createElement('label', 'Crujiente');
            $labelCrujiente->setAttribute('class', 'modifier-label modifier-radio-label');
            $labelCrujiente->setAttribute('for', $radioCrujienteId);
            
            $radioContainerCoccion->appendChild($radioSuave);
            $radioContainerCoccion->appendChild($labelSuave);
            $radioContainerCoccion->appendChild($radioCrujiente);
            $radioContainerCoccion->appendChild($labelCrujiente);
            $modGroupCoccion->appendChild($labelGroupCoccion);
            $modGroupCoccion->appendChild($radioContainerCoccion);
            $modifiersDiv->appendChild($modGroupCoccion);
            $modifierAdded = true;
        }

        // Modificador: Selectores Mitad y Mitad (Si aplica)
        if ($categoryKey === 'mitadymitad' || $categoryKey === 'mitadymitad-orilla') {
             error_log('[createProductNode] Añadiendo selectores Mitad y Mitad para: ' . $productId);
             // Obtener opciones disponibles (necesitaríamos pasar los datos del menú actual o hacer otra llamada)
             // SOLUCIÓN TEMPORAL: Crear selectores vacíos. Se poblarán con updateHalfAndHalfSelectors después.
             $tempMenuForOptions = getCurrentMenu(); // Ineficiente, pero asegura tener las opciones base
             $basePizzaOptions = [];
              $possibleBaseCategories = ['pizzas', 'orilla'];
              foreach ($possibleBaseCategories as $catKey) {
                  if (isset($tempMenuForOptions[$catKey]['products'])) {
                      foreach ($tempMenuForOptions[$catKey]['products'] as $prodId => $prodData) {
                          if (strpos($prodId, 'mitadymitad') === false) {
                              $basePizzaOptions[$prodId] = $prodData;
                          }
                      }
                  }
              }
             
            for ($i = 1; $i <= 2; $i++) {
                $mitadNum = $i;
                $modGroupId = 'mitad' . $mitadNum . '-' . $productId;
                $modGroup = $dom->createElement('div');
                $modGroup->setAttribute('class', 'modifier-group mt-2');
                $label = $dom->createElement('label', ($mitadNum === 1 ? 'Primera' : 'Segunda') . ' Mitad:');
                $label->setAttribute('for', $modGroupId);
                $label->setAttribute('class', 'group-label');
                $select = $dom->createElement('select');
                $select->setAttribute('name', $modGroupId);
                $select->setAttribute('id', $modGroupId);
                $select->setAttribute('class', 'form-select form-select-sm single-select mitad-selector');
                $select->setAttribute('data-product-id', $productId);
                
                // Opción por defecto
                $optionDefault = $dom->createElement('option', 'Selecciona...');
                $optionDefault->setAttribute('value', '');
                $select->appendChild($optionDefault);
                
                // Añadir opciones base reales
                 foreach ($basePizzaOptions as $pizzaIdOpt => $pizzaDataOpt) {
                    // Calcular precio diferencial (similar a updateHalfAndHalfSelectors)
                    $isOrillaVersion = ($categoryKey === 'mitadymitad-orilla');
                    $hhBasePrice = $basePrice; // Precio base del producto M/M actual
                    $targetPrice = $pizzaDataOpt['base_price'] ?? 0;
                     // Buscar precio de orilla si aplica
                     if ($isOrillaVersion && isset($tempMenuForOptions['orilla']['products'][$pizzaIdOpt . '-orilla'])) {
                         $targetPrice = $tempMenuForOptions['orilla']['products'][$pizzaIdOpt . '-orilla']['base_price'] ?? $targetPrice;
                     }
                     $priceDifference = $targetPrice - $hhBasePrice;
                     $priceSuffix = '';
                     if ($priceDifference > 0) {
                         $priceSuffix = sprintf(' (+%.0f)', $priceDifference);
                     } elseif ($priceDifference < 0) {
                         $priceSuffix = sprintf(' (%.0f)', $priceDifference);
                     }

                    $option = $dom->createElement('option', htmlspecialchars($pizzaDataOpt['name'] . $priceSuffix));
                    $option->setAttribute('value', $pizzaIdOpt);
                    $option->setAttribute('data-base-price', (string)($pizzaDataOpt['base_price'] ?? 0)); // Precio base de la opción
                    $select->appendChild($option);
                }
                
                $modGroup->appendChild($label);
                $modGroup->appendChild($select);
                $modifiersDiv->appendChild($modGroup);
            }
            $modifierAdded = true;
        }
        
        // Añadir el contenedor de modificadores al contentDiv (incluso si está vacío)
        $contentDiv->appendChild($modifiersDiv);

        // Precio: <h6>$<span class="product-price">...</span></h6>
        $h6 = $dom->createElement('h6');
        $h6->appendChild($dom->createTextNode('$')); // $ antes del span
        $spanPrice = $dom->createElement('span', number_format($currentPrice, 2));
        $spanPrice->setAttribute('class', 'product-price');
        $h6->appendChild($spanPrice);
        $contentDiv->appendChild($h6);

        // Selector de cantidad: <div class="quantity-selector ...">
        $quantityDiv = $dom->createElement('div');
        $quantityDiv->setAttribute('class', 'quantity-selector d-flex align-items-center justify-content-center mt-2 mb-3');
        $btnMinus = $dom->createElement('button');
        $btnMinus->setAttribute('type', 'button');
        $btnMinus->setAttribute('class', 'qty-btn quantity-minus');
        $iconMinus = $dom->createElement('i'); $iconMinus->setAttribute('class', 'fa-solid fa-minus'); $btnMinus->appendChild($iconMinus);
        $inputQty = $dom->createElement('input');
        $inputQty->setAttribute('type', 'number');
        $inputQty->setAttribute('class', 'qty-input');
        $inputQty->setAttribute('value', '1'); $inputQty->setAttribute('min', '1'); $inputQty->setAttribute('max', '20');
        $inputQty->setAttribute('name', 'quantity-' . $productId);
        $inputQty->setAttribute('readonly', 'readonly');
        $btnPlus = $dom->createElement('button');
        $btnPlus->setAttribute('type', 'button');
        $btnPlus->setAttribute('class', 'qty-btn quantity-plus');
        $iconPlus = $dom->createElement('i'); $iconPlus->setAttribute('class', 'fa-solid fa-plus'); $btnPlus->appendChild($iconPlus);
        $quantityDiv->appendChild($btnMinus); $quantityDiv->appendChild($inputQty); $quantityDiv->appendChild($btnPlus);
        $contentDiv->appendChild($quantityDiv);

        // Botón Añadir: <button class="theme-btn style6 add-to-cart-btn">
        $btnAdd = $dom->createElement('button');
        $btnAdd->setAttribute('type', 'button');
        $btnAdd->setAttribute('class', 'theme-btn style6 add-to-cart-btn');
        $btnAdd->appendChild($dom->createTextNode(' Añadir ')); // Espacios alrededor
        $iconCart = $dom->createElement('i'); $iconCart->setAttribute('class', 'fa-regular fa-basket-shopping'); $btnAdd->appendChild($iconCart);
        $contentDiv->appendChild($btnAdd);

        // Añadir contentDiv al productDiv
        $productDiv->appendChild($contentDiv);

        error_log('[createProductNode] Nodo DOM para index.html creado con éxito para ID: ' . $productId);
        return $productDiv;

    } catch (Exception $e) {
        error_log("[createProductNode] Excepción al crear nodo para index.html: " . $e->getMessage());
        return null;
    }
}

function updateProduct(string $productId, array $productData, ?array $fileData = null): bool {
    error_log("[updateProduct PHP] Iniciando actualización para ID {$productId}. Datos recibidos: " . print_r($productData, true) . " Archivo: " . print_r($fileData, true));
    
    $dom = loadIndexDOM();
    if (!$dom) return false;
    $xpath = new DOMXPath($dom);
    $changesMade = false;

    // 1. Encontrar el nodo existente en index.html
    $oldProductNode = findProductNodeById($xpath, $productId); 
    if (!$oldProductNode) {
        error_log("[updateProduct PHP] Error CRÍTICO: No se encontró el nodo del producto con ID {$productId} en index.html para actualizar.");
        return false;
    }

    // 2. Obtener datos actuales del nodo (para tener la imagen actual, etc.)
    // Usamos getCurrentMenu para obtener los datos parseados correctamente de ese nodo
    $allCurrentProducts = getCurrentMenu()['products'] ?? [];
    $currentProductData = $allCurrentProducts[$productId] ?? null;
    
    if (!$currentProductData) {
        // Si getCurrentMenu no lo encontró (quizás error de parseo), intentar extraer del nodo directamente (menos fiable)
        error_log("[updateProduct PHP] Advertencia: No se encontraron datos actuales para {$productId} vía getCurrentMenu. Intentando extraer del nodo.");
        $currentProductData = [
            'id' => $productId,
            'name' => $xpath->evaluate('string(.//h3)', $oldProductNode) ?: 'Nombre Desconocido',
            'category' => $oldProductNode->getAttribute('data-category') ?: 'unknown',
            'base_price' => (float)$oldProductNode->getAttribute('data-base-price') ?: 0,
            'current_price' => (float)$oldProductNode->getAttribute('data-current-price') ?: (float)$oldProductNode->getAttribute('data-base-price'),
            'image' => $xpath->evaluate('string(.//div[contains(@class, "dishes-thumb")]/img[not(@class="cir36")]/@src)', $oldProductNode) ?: '',
            'is_available' => strpos($oldProductNode->getAttribute('class'), 'unavailable') === false
        ];
        // Limpiar placeholder de la URL de imagen si se extrajo
        if (strpos($currentProductData['image'], 'placeholder.png') !== false) {
             $currentProductData['image'] = '';
        }
    }
    $oldCategory = $currentProductData['category'] ?? 'unknown';
    $currentImageRelPath = $currentProductData['image'] ?? ''; // URL relativa actual, ej /assets/img/pizzas/prod_123.jpg

    // 3. Fusionar datos actuales con los nuevos datos recibidos
    // Los valores en $productData (del formulario) tienen prioridad
    $mergedProductData = array_merge($currentProductData, $productData);
    // Asegurar que 'id' no se pierda si no viene en $productData
    $mergedProductData['id'] = $productId; 
    // Forzar reevaluación de disponibilidad si viene en $productData
    if (isset($productData['is_available'])) {
        $mergedProductData['is_available'] = filter_var($productData['is_available'], FILTER_VALIDATE_BOOLEAN);
    }
    // Usar 'image' consistentemente
    if (isset($mergedProductData['image_url'])) { // Si viene de un form antiguo
        $mergedProductData['image'] = $mergedProductData['image_url'];
        unset($mergedProductData['image_url']);
    }
    
    // 4. Manejo de Imagen (usando mergedProductData como base)
    $newImageUrl = $currentImageRelPath; // Empezar con la imagen actual

    // Opción A: Eliminar imagen actual marcada desde el formulario
    if (isset($productData['remove_current_image']) && $productData['remove_current_image'] === '1') {
        error_log("[updateProduct PHP] Solicitud para eliminar imagen actual de {$productId}.");
        if (!empty($currentImageRelPath) && strpos($currentImageRelPath, 'placeholder') === false) {
             // Construir ruta de archivo desde la raíz del DOCUMENT_ROOT
             $fullImagePath = $_SERVER['DOCUMENT_ROOT'] . $currentImageRelPath;
            if (file_exists($fullImagePath)) {
                if (unlink($fullImagePath)) {
                    error_log("[updateProduct PHP] Imagen antigua eliminada del servidor: {$fullImagePath}");
                    $newImageUrl = ''; // Vaciar para que se use placeholder
                    $changesMade = true;
                } else {
                    error_log("[updateProduct PHP] Error al eliminar imagen antigua del servidor: {$fullImagePath}");
                }
            } else {
                 error_log("[updateProduct PHP] Imagen antigua no encontrada en el servidor para eliminar: {$fullImagePath}");
            }
        } else {
             error_log("[updateProduct PHP] No había imagen actual o era placeholder, nada que eliminar.");
        }
        $mergedProductData['image'] = $newImageUrl; // Actualizar en datos fusionados
    }

    // Opción B: Subir nueva imagen (tiene prioridad sobre eliminar si ambas vienen)
    if ($fileData && $fileData['error'] === UPLOAD_ERR_OK) {
        error_log("[updateProduct PHP] Procesando nueva imagen subida para {$productId}.");
        // Primero, eliminar la imagen antigua si existe (físicamente)
        if (!empty($currentImageRelPath) && strpos($currentImageRelPath, 'placeholder') === false) {
             $fullImagePathOld = $_SERVER['DOCUMENT_ROOT'] . $currentImageRelPath;
             if (file_exists($fullImagePathOld)) {
                 if (unlink($fullImagePathOld)) {
                    error_log("[updateProduct PHP] Imagen antigua reemplazada eliminada del servidor: {$fullImagePathOld}");
                 } else {
                    error_log("[updateProduct PHP] Error al eliminar imagen antigua (reemplazo): {$fullImagePathOld}");
                 }             
             } else {
                  error_log("[updateProduct PHP] Imagen antigua (reemplazo) no encontrada en el servidor: {$fullImagePathOld}");
             }
        }
        
        // Guardar nueva imagen usando el ID del producto
        $imageFileType = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($imageFileType, $allowedTypes)) {
            error_log("[updateProduct PHP] Tipo de archivo no permitido: {$imageFileType}");
            // Continuar sin actualizar imagen?
        } else {
            $newFileName = $productId . '.' . $imageFileType;
            // Usar la ruta correcta relativa a la raíz del sitio web
            $baseUploadDir = '../assets/img/pizzas/'; // Ruta relativa a este script PHP
            $targetPath = $baseUploadDir . $newFileName;
            // Crear URL relativa a la raíz para guardar en HTML
            $newImageUrlRelative = '/assets/img/pizzas/' . $newFileName;

            // Asegurar que el directorio exista (usar ruta de archivo)
             $targetDirForMkdir = dirname($targetPath);
             if (!is_dir($targetDirForMkdir)) {
                 if (!mkdir($targetDirForMkdir, 0775, true)) { 
                     error_log("[updateProduct PHP] Error: No se pudo crear directorio para imagen: {$targetDirForMkdir}");
                     // Continuar sin imagen?
                 }
             }

            if (is_dir($targetDirForMkdir) && move_uploaded_file($fileData['tmp_name'], $targetPath)) {
                $newImageUrl = $newImageUrlRelative;
                error_log("[updateProduct PHP] Nueva imagen subida y movida a: {$targetPath}. URL relativa: {$newImageUrl}");
                $mergedProductData['image'] = $newImageUrl; // Actualizar en datos fusionados
                $changesMade = true;
            } else {
                error_log("[updateProduct PHP] Error al mover nueva imagen subida a: {$targetPath}");
                // Decidir si fallar o continuar sin la nueva imagen?
                // Por ahora, mantenemos la URL anterior o vacía si se eliminó
                 $mergedProductData['image'] = $currentImageRelPath; // Revertir a la anterior si falla la subida
                 if (isset($productData['remove_current_image']) && $productData['remove_current_image'] === '1') {
                     $mergedProductData['image'] = ''; // Asegurar que quede vacía si se marcó eliminar y falló la subida
                 }
            }
        }
    } elseif ($fileData && $fileData['error'] !== UPLOAD_ERR_NO_FILE) {
         error_log("[updateProduct PHP] Error de subida de archivo: " . $fileData['error']);
         // Mantener imagen actual
         $mergedProductData['image'] = $currentImageRelPath;
    }

    // 5. Crear el nuevo nodo DOM con los datos fusionados
    // Asegurar que el precio base esté presente y sea numérico
    if (!isset($mergedProductData['base_price']) || !is_numeric($mergedProductData['base_price'])) {
        $mergedProductData['base_price'] = $currentProductData['base_price'] ?? 0; // Fallback
         error_log("[updateProduct PHP] Advertencia: Precio base inválido o faltante para {$productId}. Usando anterior o 0.");
    }
    $newProductNode = createProductNode($dom, $mergedProductData);

    if (!$newProductNode) {
        error_log("[updateProduct PHP] Error CRÍTICO: No se pudo crear el nuevo nodo DOM para el producto {$productId}.");
        return false;
    }

    // 6. Reemplazar el nodo antiguo con el nuevo nodo
    try {
        $oldProductNode->parentNode->replaceChild($newProductNode, $oldProductNode);
        error_log("[updateProduct PHP] Nodo antiguo de {$productId} reemplazado con éxito por el nuevo nodo en el DOM.");
        $changesMade = true; // El reemplazo en sí es un cambio
    } catch (DOMException $e) {
         error_log("[updateProduct PHP] Error CRÍTICO al reemplazar el nodo DOM para {$productId}: " . $e->getMessage());
        return false;
    }

    // 7. Actualizar selectores M/M si es necesario y guardar
    if ($changesMade) {
        $newCategory = $mergedProductData['category'] ?? $oldCategory;
        $isPizzaCategory = (strpos($oldCategory, 'pizzas') !== false || strpos($oldCategory, 'orilla') !== false || 
                             strpos($newCategory, 'pizzas') !== false || strpos($newCategory, 'orilla') !== false);
        
        if ($isPizzaCategory) {
            error_log("[updateProduct PHP] Categoría pizza/orilla detectada ({$oldCategory} -> {$newCategory}). Actualizando selectores M/M.");
            // Es importante llamar a esto DESPUÉS de reemplazar el nodo,
            // para que opere sobre el DOM actualizado.
            // normalizePricesInExistingDOM($dom, $xpath); // Normalizar primero?
            updateHalfAndHalfSelectors($dom, $xpath);
        }

        if (saveIndexDOM($dom)) {
            error_log("[updateProduct PHP] Cambios guardados con éxito en index.html para ID {$productId}.");
            return true;
        } else {
            error_log("[updateProduct PHP] Error al guardar index.html después de actualizar ID {$productId}.");
            return false;
        }
    } else {
        error_log("[updateProduct PHP] No se detectaron cambios que requieran guardar para ID {$productId}.");
        return true; // No hubo cambios, pero la operación fue exitosa
    }
}

function deleteProduct(string $productId): bool {
    error_log("[deleteProduct PHP] Recibido para eliminar ID {$productId}");
    $dom = loadIndexDOM();
    if (!$dom) return false;
    $xpath = new DOMXPath($dom);

    // Encuentra el nodo del producto
    $productNode = findProductNodeById($xpath, $productId); 
    
    if (!$productNode) {
        error_log("[deleteProduct PHP] Error: No se encontró el nodo del producto con ID {$productId}");
        return false; // El producto ya no existe, considerar éxito? O fallo?
    }

    // Obtener la URL de la imagen ANTES de eliminar el nodo
    $imageSrc = $xpath->evaluate('string(.//div[contains(@class, "dishes-thumb")]/img[not(@class="cir36")]/@src)', $productNode);
    $imagePathToDelete = null;
    if ($imageSrc && !filter_var($imageSrc, FILTER_VALIDATE_URL) && strpos($imageSrc, 'placeholder.png') === false) {
        // Construir ruta física desde DOCUMENT_ROOT
        // Asegurar que $imageSrc empiece con / (la lógica de creación debería garantizarlo)
        $relativePath = ltrim($imageSrc, '/'); 
        $imagePathToDelete = $_SERVER['DOCUMENT_ROOT'] . '/' . $relativePath;
        error_log("[deleteProduct PHP] Imagen encontrada para posible eliminación: {$imagePathToDelete}");
    }

    // Eliminar el nodo del DOM
    if ($productNode->parentNode) {
        $productNode->parentNode->removeChild($productNode);
        error_log("[deleteProduct PHP] Nodo del producto ID {$productId} eliminado del DOM.");
        
        // Intentar eliminar la imagen física si se encontró una ruta válida
        if ($imagePathToDelete) {
            if (file_exists($imagePathToDelete)) {
                if (unlink($imagePathToDelete)) {
                    error_log("[deleteProduct PHP] Imagen asociada eliminada del servidor: {$imagePathToDelete}");
                } else {
                    error_log("[deleteProduct PHP] ADVERTENCIA: No se pudo eliminar la imagen asociada: {$imagePathToDelete}");
                    // No fallar la operación completa solo por esto, pero registrarlo.
                }
            } else {
                error_log("[deleteProduct PHP] ADVERTENCIA: La imagen asociada no se encontró en la ruta esperada para eliminar: {$imagePathToDelete}");
            }
        }
        
        // Guardar el DOM modificado
        if (saveIndexDOM($dom)) {
            error_log("[deleteProduct PHP] index.html guardado con éxito después de eliminar ID {$productId}.");
             // Actualizar selectores M/M si se eliminó una pizza base
             // $productInfo = ??? // Necesitaríamos saber la categoría antes de eliminar
             // updateHalfAndHalfSelectors($dom, $xpath); saveIndexDOM($dom); 
            return true;
        } else {
            error_log("[deleteProduct PHP] Error al guardar index.html después de eliminar ID {$productId}.");
    return false;
        }
    } else {
        error_log("[deleteProduct PHP] Error: El nodo del producto ID {$productId} no tiene padre.");
        return false;
    }
}

// --- FIN: Funciones CRUD para index.html ---

// --- INICIO: Manejo de Peticiones POST/AJAX ---
$isMenuManagerAction = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("[Menu Manager POST] Petición POST recibida. Contenido _POST: " . print_r($_POST, true) . " Contenido _FILES: " . print_r($_FILES, true)); // <-- LOG INICIAL POST
    $action = $_POST['action'] ?? null;
    error_log("[Menu Manager POST] Acción detectada: '{$action}'"); // <-- LOG VALOR DE ACCIÓN

    $response = ['success' => false, 'message' => 'Acción POST no reconocida o fallida.']; 
    $runSelectorsUpdate = false; // Flag para actualizar selectores M/M

    try {
        if ($action === 'add_product' || $action === 'edit_product') {
             $isMenuManagerAction = true;
             error_log("[Menu Manager POST] Iniciando acción: {$action}");
            $productData = [
                'name' => $_POST['name'] ?? null,
                'description' => $_POST['description'] ?? '',
                'category' => $_POST['category'] ?? null,
                'base_price' => $_POST['base_price'] ?? null,
                'discount_price' => $_POST['discount_price'] ?? null,
                'is_available' => isset($_POST['is_available']),
                'tags' => isset($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [],
            ];
            $productId = $_POST['product_id'] ?? null; // Para editar
            $fileData = $_FILES['image'] ?? null;
            
            // Validación básica
            if (empty($productData['name']) || empty($productData['category']) || $productData['base_price'] === null) {
                 throw new Exception("Nombre, categoría y precio base son requeridos.");
            }
             // Limpiar y validar precios
             $productData['base_price'] = filter_var($productData['base_price'], FILTER_VALIDATE_FLOAT);
             if ($productData['base_price'] === false || $productData['base_price'] < 0) {
                 throw new Exception("Precio base inválido.");
             }
             if (!empty($productData['discount_price'])) {
                $productData['discount_price'] = filter_var($productData['discount_price'], FILTER_VALIDATE_FLOAT);
                 if ($productData['discount_price'] === false || $productData['discount_price'] < 0) {
                     throw new Exception("Precio de descuento inválido.");
                 }
             } else {
                 $productData['discount_price'] = null; // Asegurar que sea null si está vacío
             }

            // Asegurarse que las funciones addProduct/updateProduct estén definidas o incluidas
            if (!function_exists('addProduct') || !function_exists('updateProduct')) {
                // Incluir el archivo que las contiene si es necesario, o definirlas aquí/en adminconfig
                // require_once __DIR__ . '/includes/product_functions.php'; // Ejemplo
                error_log("Error Crítico: Funciones addProduct/updateProduct no encontradas.");
                throw new Exception("Funciones de producto no disponibles.");
            }

            $result = false;
            if ($action === 'add_product') {
                error_log("Llamando a addProduct...");
                $result = addProduct($productData, $fileData); // Asumiendo que addProduct está definida
                $response['message'] = $result ? 'Producto añadido con éxito.' : 'Error al añadir el producto (addProduct falló).';
                error_log("Resultado addProduct: " . ($result ? 'Éxito' : 'Fallo'));
            } else { // edit_product
                if (empty($productId)) throw new Exception("ID de producto no proporcionado para editar.");
                error_log("Llamando a updateProduct para ID: {$productId}...");
                $result = updateProduct($productId, $productData, $fileData); // Asumiendo que updateProduct está definida
                $response['message'] = $result ? 'Producto actualizado con éxito.' : 'Error al actualizar el producto (updateProduct falló).';
                error_log("Resultado updateProduct: " . ($result ? 'Éxito' : 'Fallo'));
            }
            
             if ($result) {
                 $response['success'] = true;
                 $runSelectorsUpdate = true; 
             }

        } elseif ($action === 'delete_product') {
            // Ahora esta acción SÍ se maneja aquí
             $isMenuManagerAction = true;
             error_log("[Menu Manager POST] Iniciando acción: delete_product");
             $productId = $_POST['product_id'] ?? null;
             if (empty($productId)) {
                 error_log("Error delete_product: ID no proporcionado.");
                 throw new Exception("ID de producto no proporcionado para eliminar.");
             }
             
             // Asegurarse que la función deleteProduct esté definida
             if (!function_exists('deleteProduct')) {
                 error_log("Error Crítico: Función deleteProduct no encontrada.");
                 throw new Exception("Función de eliminación de producto no disponible.");
             }
             
             error_log("Llamando a deleteProduct para ID: {$productId}...");
             if (deleteProduct($productId)) { // Asumiendo que deleteProduct está definida más abajo
                 $response['success'] = true;
                 $response['message'] = 'Producto eliminado con éxito.';
                 error_log("Resultado deleteProduct: Éxito");
                 $runSelectorsUpdate = true; // Actualizar selectores M/M si se elimina pizza base
             } else {
                 $response['message'] = 'Error al eliminar el producto (deleteProduct falló).';
                 error_log("Resultado deleteProduct: Fallo");
             }

        } elseif ($action === 'toggle_availability') {
             $isMenuManagerAction = true;
             error_log("[Menu Manager POST] Iniciando acción: toggle_availability");
             $productId = $_POST['product_id'] ?? null;
             // OJO: El JS envía 'true'/'false' string, pero el PHP anterior usaba isset($_POST['is_available'])
             // Asegurémonos de leer correctamente el estado deseado
             $isAvailableParam = $_POST['is_available'] ?? null; // Debería ser 'true' o 'false'
             
             if (empty($productId) || $isAvailableParam === null) {
                 error_log("Error toggle_availability: Datos incompletos. ID={$productId}, is_available={$isAvailableParam}");
                 throw new Exception("Datos incompletos para cambiar disponibilidad.");
             }
             $isAvailable = ($isAvailableParam === 'true'); // Convertir string a boolean
             error_log("Toggle para ID: {$productId}, Nuevo estado deseado (is_available): " . ($isAvailable ? 'true' : 'false'));

             // Llamar a updateProduct solo con el cambio de disponibilidad
             // Asegurarse que updateProduct esté definida
             if (!function_exists('updateProduct')) {
                 error_log("Error Crítico: Función updateProduct no encontrada para toggle_availability.");
                 throw new Exception("Función de producto no disponible.");
             }
             error_log("Llamando a updateProduct para toggle...");
             if (updateProduct($productId, ['is_available' => $isAvailable])) {
                 $response['success'] = true;
                 $response['message'] = 'Disponibilidad actualizada.';
                 error_log("Resultado updateProduct (toggle): Éxito");
                 // ... (lógica runSelectorsUpdate)
             } else {
                 $response['message'] = 'Error al actualizar la disponibilidad (updateProduct falló).';
                 error_log("Resultado updateProduct (toggle): Fallo");
             }
        } elseif ($action === 'save_order') {
             $isMenuManagerAction = true; // Marcar que fue una acción AJAX de este gestor
             error_log("[Menu Manager POST] Iniciando acción: save_order");
             
             // Leer el cuerpo JSON de la petición (ya que JS envía Content-Type: application/json)
             // $jsonPayload = file_get_contents('php://input');
             // $requestData = json_decode($jsonPayload, true);
             
             // *** CORRECCIÓN: Leer desde $_POST ya que JS fue ajustado para enviar x-www-form-urlencoded ***
             $orderData = json_decode($_POST['order'] ?? '[]', true); 
             
             if (json_last_error() !== JSON_ERROR_NONE) {
                 error_log("[Menu Manager POST save_order] Error decodificando JSON: " . json_last_error_msg());
                 $response = ['success' => false, 'message' => 'Error en los datos de orden recibidos.'];
             } elseif (empty($orderData)) {
                 error_log("[Menu Manager POST save_order] Datos de orden vacíos recibidos.");
                 $response = ['success' => false, 'message' => 'No se recibieron datos de orden para guardar.'];
             } else {
                 if (saveProductOrder($orderData)) { // saveProductOrder usa el DOM
                     $response = ['success' => true, 'message' => 'Orden de productos guardado con éxito.'];
                     error_log("[Menu Manager POST save_order] Orden guardado con éxito.");
                 } else {
                     $response = ['success' => false, 'message' => 'Error al guardar el orden de los productos.'];
                     error_log("[Menu Manager POST save_order] saveProductOrder falló.");
                 }
             }
         }

        // Después de una acción exitosa que modifique productos (add, edit, delete, toggle)
        // NO después de save_order, ya que saveProductOrder guarda el DOM
        if ($runSelectorsUpdate && $action !== 'save_order') {
            $dom = loadIndexDOM();
            if ($dom) {
                $xpath = new DOMXPath($dom);
                if (updateHalfAndHalfSelectors($dom, $xpath)) {
                    if (saveIndexDOM($dom)) {
                        error_log("Selectores Mitad y Mitad actualizados y DOM guardado después de la acción: " . $action);
                    } else {
                         error_log("ERROR: Fallo al guardar DOM después de actualizar selectores Mitad y Mitad (Acción: {$action}).");
                        // Podríamos añadir esto al mensaje de error de la respuesta principal?
                        $response['message'] .= ' (Advertencia: Error al guardar actualización de opciones Mitad y Mitad)';
                    }
                } else {
                     error_log("Fallo la actualización de selectores Mitad y Mitad después de la acción: " . $action);
                }
            } else {
                 error_log("ERROR: No se pudo cargar DOM para actualizar selectores Mitad y Mitad después de la acción: " . $action);
            }
        }

    } catch (Exception $e) {
        error_log("Error EXCEPCIÓN en acción POST '{$action}': " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString()); // <-- LOG DE EXCEPCIÓN DETALLADO
        http_response_code(500); // Internal server error para excepciones
        $response['success'] = false; // Asegurar que success sea false
        $response['message'] = "Error interno del servidor: " . $e->getMessage();
    }

    // Si fue una acción AJAX de este gestor, enviar respuesta JSON y salir
    if ($isMenuManagerAction) {
        // Si no hubo éxito explícito, asegurar que el código de estado sea apropiado
        if (!$response['success'] && http_response_code() === 200) {
             http_response_code(400); // Bad request como default si falló pero no fue excepción 500
        }
        header('Content-Type: application/json');
        error_log("[Menu Manager POST] Enviando respuesta JSON: " . json_encode($response)); // <-- LOG DE RESPUESTA
        echo json_encode($response);
        exit;
    }
}
// --- FIN: Manejo de Peticiones POST/AJAX ---


// --- INICIO: Obtención de Datos para Renderizar la Página ---
$currentMenu = getCurrentMenu();
$page_title = "Gestor de Menú"; // Define el título para el header

// Obtener estado de la tienda para el modal (redundante si ya está en header, pero seguro)
// $storeStatus = getStoreStatus(); 
// --- FIN: Obtención de Datos ---


// --- INICIO: Inclusión de Header y Sidebar ---
include __DIR__ . '/includes/admin_header.php';
include __DIR__ . '/includes/admin_sidebar.php';
// --- FIN: Inclusión de Header y Sidebar ---

?>

<!-- INICIO: Contenido Principal HTML -->
<main class="admin-main-content">
    <div class="container-fluid mt-4">
        <div class="admin-card">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Gestión de Productos</h4>
                 <!-- Botones de Acción Principales -->
                <div>
                    <button class="btn btn-success me-2" type="button" data-bs-toggle="modal" data-bs-target="#productModal" id="openAddProductModalBtn">
                        <i class="bi bi-plus-circle-fill"></i> Añadir Producto
                    </button>
                    <div id="reorder-controls-container" style="display: inline-block;">
                        <button type="button" class="btn btn-info" id="enableReorderBtn"><i class="bi bi-arrow-down-up"></i> Activar Reordenamiento</button>
                        <button type="button" class="btn btn-success me-2" id="saveOrderBtn" style="display: none;"><i class="bi bi-save"></i> Guardar Orden</button>
                        <button type="button" class="btn btn-danger" id="cancelReorderBtn" style="display: none;"><i class="bi bi-x-circle"></i> Cancelar</button>
            </div>
        </div>
        </div>
            <div class="admin-card-body">
                 <!-- Filtros de Categoría -->
                <nav class="menu-filter-nav-admin text-center mb-4">
                    <button class="filter-btn-admin active" data-filter="all">Todas</button>
                    <?php 
                    // Usar $currentMenu directamente para los filtros
                    if (is_array($currentMenu)):
                        foreach ($currentMenu as $key => $categoryData):
                            if (isset($categoryData['name'])):
                    ?>
                        <button class="filter-btn-admin" data-filter="<?php echo htmlspecialchars($key); ?>">
                            <?php echo htmlspecialchars($categoryData['name']); ?>
                        </button>
                    <?php 
                            endif;
                        endforeach;
                    endif;
                    ?>
    </nav>

                <!-- Lista de Productos -->
                 <div class="row product-list-row" id="productListContainer">
                    <?php 
                    // Usar $currentMenu directamente para la lista
                    if (is_array($currentMenu)):
                        foreach ($currentMenu as $categoryKey => $categoryData):
                            if (!isset($categoryData['name']) || !isset($categoryData['products'])) continue; // Saltar si la data no está completa
                            $categoryName = $categoryData['name'];
                            $productsInCategory = $categoryData['products']; // Acceder a los productos de esta categoría
                    ?>
                        <div class="col-12 category-title-container-admin" data-category-key="<?php echo htmlspecialchars($categoryKey); ?>">
                             <h3 class="category-title-admin"><?php echo htmlspecialchars($categoryName); ?></h3>
        </div>
                        <div class="col-12 category-products-container-admin" id="category-<?php echo htmlspecialchars($categoryKey); ?>">
                            <div class="row g-3 product-card-reorder-list-admin"> <!-- Contenedor para SortableJS por categoría -->
                            <?php
                            if (empty($productsInCategory)): ?>
                                <div class="col-12"><p class="text-muted">No hay productos en esta categoría.</p></div>
                            <?php else:
                                foreach ($productsInCategory as $product):
                                    // Extraer datos para la tarjeta de admin
                                    $productId = htmlspecialchars($product['id']);
                                    $productName = htmlspecialchars($product['name']);
                                    $basePrice = number_format($product['base_price'] ?? 0, 2);
                                    $discountPrice = null; // Placeholder
                                    $currentPrice = number_format($product['current_price'] ?? $product['base_price'] ?? 0, 2);
                                    $imageUrl = htmlspecialchars($product['image'] ?? ''); 
                                    $isAvailable = $product['is_available'] ?? true;
                                    // *** CORRECCIÓN AQUÍ: Usar $product['category'] para ambas clases/atributos ***
                                    $actualCategoryKey = htmlspecialchars($product['category'] ?? 'unknown'); 
                                    $categoryClass = 'category-' . $actualCategoryKey;
                                    $productJson = htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8');
                            ?>
                                <!-- Asegurarse que data-category use la categoría real del producto -->
                                <div class="col-lg-3 col-md-4 col-sm-6 product-card-reorder-item-admin" data-product-id="<?php echo $productId; ?>" data-category="<?php echo $actualCategoryKey; ?>">
                                    <div class="card product-card h-100 <?php echo !$isAvailable ? 'unavailable' : ''; ?> <?php echo $categoryClass; ?>">
                                        <div class="product-image-container-admin">
                                            <img src="<?php echo $imageUrl ?: '/assets/img/placeholder.png'; ?>" class="product-image-admin" alt="<?php echo $productName; ?>" loading="lazy" 
                                                 onerror="this.style.display='none'; this.parentElement.querySelector('.placeholder-icon') ? this.parentElement.querySelector('.placeholder-icon').style.display='block' : null;">
                                            <i class="bi bi-image placeholder-icon" style="display: <?php echo empty($imageUrl) ? 'block' : 'none'; ?>; font-size: 3rem; color: #6c757d; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"></i>
                                        </div>
                            <div class="card-body">
                                            <h5 class="product-name-admin"><?php echo $productName; ?></h5>
                                            <p class="product-price-admin">
                                                 <?php if ($discountPrice !== null):
                                                     // ... (lógica de descuento) ...
                                                 ?>
                                                 <span class="base-price">$<?php echo $currentPrice; /* Mostrar precio actual */ ?></span>
                                                 <?php else: ?>
                                                     <span class="base-price">$<?php echo $basePrice; ?></span>
                                                 <?php endif; ?>
                                            </p>
                                            <div class="product-actions-admin">
                                                <button class="btn edit-product" data-bs-toggle="modal" data-bs-target="#productModal" data-product='<?php echo $productJson; ?>'>
                                                    <i class="bi bi-pencil-fill"></i> Editar
                                    </button>
                                                <button class="btn toggle-availability <?php echo $isAvailable ? 'btn-warning' : 'btn-success'; ?>" data-product-id="<?php echo $productId; ?>" data-current-state="<?php echo $isAvailable ? '1' : '0'; ?>">
                                                    <?php if ($isAvailable): ?>
                                                        <i class="bi bi-eye-slash-fill"></i> Ocultar
                                                    <?php else: ?>
                                                        <i class="bi bi-eye-fill"></i> Mostrar
                                                    <?php endif; ?>
                                                </button>
                                                <button class="btn btn-danger delete-product" data-product-id="<?php echo $productId; ?>" data-product-name="<?php echo $productName; ?>">
                                                    <i class="bi bi-trash-fill"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                            <?php
                                endforeach;
                            endif;
                            ?>
                </div>
                        </div><!-- Fin .category-products-container-admin -->
                        <?php // Añadir un divisor visual entre categorías si no es la última
                         // Comprobar si es la última categoría en $currentMenu
                         if ($categoryData !== end($currentMenu)): ?>
                           <div class="col-12"><hr class="category-divider-admin my-4"></div>
                         <?php endif; ?>
                        <?php endforeach; /* fin foreach $currentMenu */ 
                    endif; /* fin if is_array($currentMenu) */
                    ?>
                </div> <!-- Fin .product-list-row -->
            </div> <!-- Fin .admin-card-body -->
        </div> <!-- Fin .admin-card -->
    </div> <!-- Fin .container-fluid -->

    <!-- Modal para Añadir/Editar Producto -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content admin-card">
                <form id="productForm" enctype="multipart/form-data">
                    <div class="modal-header admin-card-header">
                        <h5 class="modal-title" id="productModalLabel">Añadir/Editar Producto</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
                    <div class="modal-body admin-card-body">
                        <input type="hidden" id="productId" name="product_id">
                        <input type="hidden" id="formAction" name="action" value="add_product">

                        <div class="row">
                            <div class="col-md-8">
                                <div class="admin-form-group mb-3">
                                    <label for="productName" class="form-label">Nombre del Producto <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="productName" name="name" required>
                </div>
                                <div class="admin-form-group mb-3">
                                    <label for="productDescription" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                </div>
                                <div class="row">
                                    <div class="col-md-6 admin-form-group mb-3">
                                        <label for="productCategory" class="form-label">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-select" id="productCategory" name="category" required>
                                            <option value="">Selecciona...</option>
                                             <?php 
                                             // Usar $currentMenu también para el select del modal
                                             if (is_array($currentMenu)):
                                                 foreach ($currentMenu as $key => $categoryData):
                                                     if (isset($categoryData['name'])):
                                             ?>
                                                <option value="<?php echo htmlspecialchars($key); ?>">
                                                    <?php echo htmlspecialchars($categoryData['name']); ?>
                                                </option>
                                            <?php 
                                                     endif;
                                                 endforeach;
                                             endif;
                                             ?>
                                            <option value="new_category">-- Nueva Categoría --</option>
                                        </select>
                </div>
                                     <div class="col-md-6 admin-form-group mb-3" id="newCategoryInputGroup" style="display: none;">
                                        <label for="newCategoryName" class="form-label">Nombre Nueva Categoría <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="newCategoryName" name="new_category_name">
            </div>
        </div>
                                <div class="row">
                                     <div class="col-md-12 admin-form-group mb-3"> <!-- Ocupar todo el ancho para precio base -->
                                        <label for="productBasePrice" class="form-label">Precio Base <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="productBasePrice" name="base_price" step="0.01" min="0" required>
    </div>
                </div>
                                <div class="admin-form-group form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="productIsAvailable" name="is_available" value="1" checked>
                                    <label class="form-check-label" for="productIsAvailable">Disponible para la venta</label>
                        </div>
                        </div>
                            <div class="col-md-4">
                                <div class="admin-form-group mb-3 text-center">
                                     <label for="productImage" class="form-label">Imagen del Producto</label>
                                     <div class="image-preview mx-auto border rounded d-flex justify-content-center align-items-center mb-2" style="width: 150px; height: 150px; background-color: #333;">
                                         <img id="imagePreview" src="" alt="Vista previa" style="max-width: 100%; max-height: 100%; object-fit: contain; display: none;"> <!-- Iniciar oculta -->
                                         <i id="imagePreviewIcon" class="bi bi-image text-secondary" style="font-size: 4rem; display: block;"></i> <!-- Iniciar visible -->
                        </div>
                                     <input type="file" class="form-control form-control-sm" id="productImage" name="image" accept="image/png, image/jpeg, image/webp">
                                     <small id="image_feedback" class="form-text text-muted"></small>
                                      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="removeImageBtn" style="display:none;">Quitar Imagen</button>
                                     <input type="hidden" id="removeCurrentImage" name="remove_current_image" value="0">
                            </div>
                        </div>
                </div>
                            </div>
                    <div class="modal-footer admin-card-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="saveProductBtn">Guardar Producto</button>
                </div>
                    </form>
            </div>
        </div>
    </div>
</main>
<!-- FIN: Contenido Principal HTML -->

<?php
// --- INICIO: Inclusión de Footer ---
include __DIR__ . '/includes/admin_footer.php';
// --- FIN: Inclusión de Footer ---
?>

<!-- INICIO: Bloque JavaScript Específico de la Página -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    console.log('[Menu Manager JS] DOMContentLoaded disparado.'); // <-- LOG MUY TEMPRANO
    
    // --- Variables y Selectores ---
    const productListContainer = document.getElementById('productListContainer');
    const filterButtons = document.querySelectorAll('.filter-btn-admin');
    const productModal = new bootstrap.Modal(document.getElementById('productModal'));
    const productForm = document.getElementById('productForm');
    const modalTitle = document.getElementById('productModalLabel');
    const productIdInput = document.getElementById('productId');
    const formActionInput = document.getElementById('formAction');
    const productNameInput = document.getElementById('productName');
    const productDescriptionInput = document.getElementById('productDescription');
    const productCategorySelect = document.getElementById('productCategory');
    const newCategoryInputGroup = document.getElementById('newCategoryInputGroup');
    const newCategoryNameInput = document.getElementById('newCategoryName');
    const productBasePriceInput = document.getElementById('productBasePrice');
    const productIsAvailableCheckbox = document.getElementById('productIsAvailable');
    const productImageInput = document.getElementById('productImage');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewIcon = document.getElementById('imagePreviewIcon');
    const imageFeedback = document.getElementById('image_feedback');
    const removeImageBtn = document.getElementById('removeImageBtn');
    const removeCurrentImageInput = document.getElementById('removeCurrentImage');
    const saveProductBtn = document.getElementById('saveProductBtn');
    const openAddProductModalBtn = document.getElementById('openAddProductModalBtn');

    const enableReorderBtn = document.getElementById('enableReorderBtn');
    const saveOrderBtn = document.getElementById('saveOrderBtn');
    const cancelReorderBtn = document.getElementById('cancelReorderBtn');
    const reorderControlsContainer = document.getElementById('reorder-controls-container');
    
    let sortableInstances = []; // Array para guardar instancias de SortableJS

    if (!productForm) {
        console.error('[Menu Manager JS] Error crítico: No se encontró el formulario #productForm.');
        return; // Detener si el form no existe
    }

    // --- Funciones ---

    // Filtro de Productos
    function filterProducts(filter) {
        const allProductCards = productListContainer.querySelectorAll('.product-card-reorder-item-admin');
        const allCategoryTitles = productListContainer.querySelectorAll('.category-title-container-admin');
        const allCategoryDividers = productListContainer.querySelectorAll('.category-divider-admin');
        const allCategoryContainers = productListContainer.querySelectorAll('.category-products-container-admin');

        allCategoryTitles.forEach(title => title.style.display = 'none');
        allProductCards.forEach(card => card.style.display = 'none');
        allCategoryDividers.forEach(divider => divider.style.display = 'none');

        if (filter === 'all') {
            allCategoryTitles.forEach(title => title.style.display = 'block');
            allProductCards.forEach(card => card.style.display = 'block');
            allCategoryDividers.forEach(divider => divider.style.display = 'block');
        } else {
            const categoryTitleToShow = productListContainer.querySelector(`.category-title-container-admin[data-category-key="${filter}"]`);
            if (categoryTitleToShow) categoryTitleToShow.style.display = 'block';

            const productsToShow = productListContainer.querySelectorAll(`.product-card-reorder-item-admin[data-category="${filter}"]`);
            productsToShow.forEach(card => card.style.display = 'block');
            
             // Ocultar el contenedor de productos si la categoría filtrada no tiene productos
             allCategoryContainers.forEach(container => {
                 if (container.id === `category-${filter}`) {
                     const productsInContainer = container.querySelectorAll('.product-card-reorder-item-admin[style*="display: block"]');
                      const noProductsMessage = container.querySelector('.text-muted'); // Busca el mensaje "No hay productos"
                     if (productsInContainer.length === 0) {
                          if (noProductsMessage) noProductsMessage.style.display = 'block'; // Mostrar si no hay productos
                     } else {
                          if (noProductsMessage) noProductsMessage.style.display = 'none'; // Ocultar si hay productos
                     }
                 }
             });
        }
         // Quitar separador si está justo antes del final (visible)
        const visibleDividers = Array.from(allCategoryDividers).filter(d => d.style.display !== 'none');
        if(visibleDividers.length > 0) {
            let lastVisibleElement = null;
             const children = Array.from(productListContainer.children);
             for (let i = children.length - 1; i >= 0; i--) {
                 if (children[i].nodeType === 1 && window.getComputedStyle(children[i]).display !== 'none') {
                     lastVisibleElement = children[i];
                     break;
                 }
             }
             if(lastVisibleElement && lastVisibleElement.classList.contains('category-divider-admin')) {
                lastVisibleElement.style.display = 'none';
             }
        }
    }

    // Resetear Formulario del Modal
    function resetProductForm() {
        productForm.reset();
        productIdInput.value = '';
        formActionInput.value = 'add_product';
        modalTitle.textContent = 'Añadir Producto';
        saveProductBtn.textContent = 'Añadir Producto';
        imagePreview.src = ''; // Limpiar src
        imagePreview.style.display = 'none'; // Ocultar img
        imagePreviewIcon.style.display = 'block'; // Mostrar icono
        imageFeedback.textContent = '';
        removeImageBtn.style.display = 'none';
        removeCurrentImageInput.value = '0';
        newCategoryInputGroup.style.display = 'none';
         newCategoryNameInput.removeAttribute('required');
         productCategorySelect.setAttribute('required', 'true'); 
         productIsAvailableCheckbox.checked = true;
    }

    // Preparar Modal para Añadir
    function prepareAddModal() {
        resetProductForm();
    }

    // Preparar Modal para Editar
    function prepareEditModal(productData) {
        resetProductForm();
        modalTitle.textContent = 'Editar Producto';
        saveProductBtn.textContent = 'Guardar Cambios';
        formActionInput.value = 'edit_product';

        productIdInput.value = productData.id || '';
        productNameInput.value = productData.name || '';
        productDescriptionInput.value = productData.description || '';
        productCategorySelect.value = productData.category || '';
        productBasePriceInput.value = productData.base_price || '';
        productIsAvailableCheckbox.checked = productData.is_available !== undefined ? productData.is_available : true; // Default a true si no está definido

        let displayImageUrl = productData.image || ''; // Usar URL del producto o vacío

        if (displayImageUrl) {
            imagePreview.src = displayImageUrl;
            imagePreview.style.display = 'block';
            imagePreviewIcon.style.display = 'none';
            removeImageBtn.style.display = 'inline-block';
        } else {
            imagePreview.src = ''; // Limpiar src
            imagePreview.style.display = 'none'; // Ocultar img
            imagePreviewIcon.style.display = 'block'; // Mostrar icono
            removeImageBtn.style.display = 'none';
        }
        removeCurrentImageInput.value = '0'; // Resetear flag de eliminación
    }

    // Mostrar Feedback de Imagen
    function showImageFeedback(message, isError = false) {
        imageFeedback.textContent = message;
        imageFeedback.className = isError ? 'form-text text-danger' : 'form-text text-success';
    }

    // Manejar Cambio de Imagen
    function handleImageChange(event) {
        const file = event.target.files[0];
        if (!file) {
            showImageFeedback(''); // Limpiar feedback si no hay archivo
            // No resetear la preview si el usuario cancela la selección
            return;
        }

        // Validar tipo y tamaño
        const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
        const maxSize = 2 * 1024 * 1024; // 2MB

        if (!allowedTypes.includes(file.type)) {
            showImageFeedback('Error: Solo se permiten imágenes PNG, JPG o WEBP.', true);
            event.target.value = ''; // Resetear input
            return;
        }
        if (file.size > maxSize) {
            showImageFeedback('Error: La imagen no debe exceder los 2MB.', true);
            event.target.value = ''; // Resetear input
            return;
        }

        // Mostrar vista previa
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block'; // Mostrar img
            imagePreviewIcon.style.display = 'none'; // Ocultar icono
            removeImageBtn.style.display = 'inline-block';
            removeCurrentImageInput.value = '0'; // Si sube una nueva, no queremos remover la actual (que será reemplazada)
            showImageFeedback(`Archivo seleccionado: ${file.name}`, false);
        }
        reader.onerror = function() {
             showImageFeedback('Error al leer la imagen.', true);
        }
        reader.readAsDataURL(file);
    }
    
     // Manejar cambio de categoría
    function handleCategoryChange() {
        if (productCategorySelect.value === 'new_category') {
            newCategoryInputGroup.style.display = 'block';
            newCategoryNameInput.setAttribute('required', 'true');
            // Opcional: quitar 'required' del select si se va a añadir nueva
             // productCategorySelect.removeAttribute('required'); 
        } else {
            newCategoryInputGroup.style.display = 'none';
            newCategoryNameInput.removeAttribute('required');
            newCategoryNameInput.value = ''; // Limpiar por si acaso
            productCategorySelect.setAttribute('required', 'true'); 
        }
    }

    // Función para manejar clics en botones de acción de producto (Editar, Ocultar, Eliminar)
    function handleProductActions(event) {
        const target = event.target;
        const productItem = target.closest('.product-card-reorder-item-admin');
        if (!productItem) return;

        const productId = productItem.dataset.productId;
        console.log('Product button clicked. Product ID:', productId, 'Target element:', target); // <-- DEBUG LOG

        // Botón Editar
        if (target.classList.contains('edit-product') || target.closest('.edit-product')) {
            console.log('Edit button clicked for product:', productId); // <-- DEBUG LOG
            try {
                const productData = JSON.parse(productItem.querySelector('.edit-product').dataset.product || '{}'); // Obtener datos del botón
                 prepareEditModal(productData);
            } catch (e) {
                 console.error('Error parsing product data for edit:', e);
                 alert('No se pudieron cargar los datos para editar este producto.');
            }
        }
        // Botón Ocultar/Mostrar
        else if (target.classList.contains('toggle-availability') || target.closest('.toggle-availability')) {
             console.log('Toggle availability button clicked for product:', productId); // <-- DEBUG LOG
             const button = target.closest('.toggle-availability');
             if (button) {
                toggleAvailability(productId, button.dataset.currentState);
             }
        }
        // Botón Eliminar
        else if (target.classList.contains('delete-product') || target.closest('.delete-product')) {
             console.log('Delete button clicked for product:', productId); // <-- DEBUG LOG
             const button = target.closest('.delete-product');
              if (button && confirm(`¿Estás seguro de que quieres eliminar el producto "${button.dataset.productName || 'este producto'}"?`)) {
                 deleteProductAction(productId, button.dataset.productName);
             }
        }
    }

    // Manejar Envío del Formulario (SOLO para el modal)
    async function handleProductFormSubmit(event) {
        console.log('[handleProductFormSubmit] La función se inició.'); // <-- LOG AL INICIO DE LA FUNCIÓN
        event.preventDefault(); 
        saveProductBtn.disabled = true;
        saveProductBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        console.log('[handleProductFormSubmit] Iniciando envío...'); // <-- LOG JS
        const formData = new FormData(productForm);
        const currentProductId = formData.get('product_id');
        const action = currentProductId ? 'edit_product' : 'add_product';
        formData.set('action', action);

        // Eliminar campos no necesarios 
        // formData.delete('discount_price'); // Ya no existen en el form
        // formData.delete('tags');

        // Manejar nueva categoría (lógica existente)
         if (productCategorySelect.value === 'new_category' && newCategoryNameInput.value.trim() !== '') {
            // ... (lógica existente) ...
         }

        // Log FormData entries for debugging
        console.log('[handleProductFormSubmit] FormData a enviar:'); // <-- LOG JS
        for (let [key, value] of formData.entries()) {
            // Para el archivo, solo loguear el nombre si existe
            if (value instanceof File) {
                console.log(key, value.name, `(size: ${value.size}, type: ${value.type})`);
            } else {
                console.log(key, value);
            }
        }

        try { // <-- Añadir try...catch para fetch
            const response = await fetch('menu_manager.php', {
                        method: 'POST',
                body: formData
                // No establecer Content-Type manualmente cuando se usa FormData;
                // el navegador lo hace automáticamente con el boundary correcto.
            });

            console.log(`[handleProductFormSubmit] Respuesta recibida - Status: ${response.status}`); // <-- LOG JS

            if (!response.ok) {
                 const errorText = await response.text();
                 console.error('[handleProductFormSubmit] Error en respuesta:', errorText); // <-- LOG JS
                 throw new Error(`Error HTTP: ${response.status} ${response.statusText} - ${errorText}`); 
             }

            const data = await response.json();
            console.log("[handleProductFormSubmit] Respuesta JSON parseada:", data); // <-- LOG JS

                        if (data.success) {
                alert(data.message || 'Operación completada con éxito.');
                productModal.hide();
                location.reload(); // Recargar para ver los cambios
                            } else {
                alert('Error: ' + (data.message || 'No se pudo completar la operación.'));
            }
        } catch (error) {
            console.error('[handleProductFormSubmit] Error durante fetch o procesamiento:', error); // <-- LOG JS
            alert('Error de conexión o del servidor al guardar el producto. Revisa la consola para detalles. Detalles: ' + error.message);
        } finally {
            saveProductBtn.disabled = false;
            saveProductBtn.innerHTML = action === 'add_product' ? 'Añadir Producto' : 'Guardar Cambios';
        }
    }

    // Cambiar Disponibilidad
    async function toggleAvailability(productId, currentState) {
        const newState = currentState === '1' ? '0' : '1'; // Invertir estado
        const confirmationMessage = newState === '1' 
            ? '¿Estás seguro de que quieres hacer este producto VISIBLE de nuevo?' 
            : '¿Estás seguro de que quieres OCULTAR este producto? No estará disponible para la venta.';
            
        if (!confirm(confirmationMessage)) return;

        const formData = new FormData();
        formData.append('action', 'toggle_availability');
        formData.append('product_id', productId);
        formData.append('is_available', newState === '1');

        try {
            const response = await fetch('menu_manager.php', {
                method: 'POST',
                body: formData
            });
             if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                // Actualizar UI (clase, texto del botón, etc.)
                const card = document.querySelector(`.product-card-reorder-item-admin[data-product-id="${productId}"] .product-card`);
                const button = document.querySelector(`.toggle-availability[data-product-id="${productId}"]`);
                if (card && button) {
                    button.dataset.currentState = newState;
                    if (newState === '1') {
                        card.classList.remove('unavailable');
                        button.classList.remove('btn-success');
                        button.classList.add('btn-warning');
                        button.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Ocultar';
                    } else {
                        card.classList.add('unavailable');
                        button.classList.remove('btn-warning');
                        button.classList.add('btn-success');
                         button.innerHTML = '<i class="bi bi-eye-fill"></i> Mostrar';
                    }
                    alert('Disponibilidad actualizada.'); // O usar un toast
                } else {
                    location.reload(); // Recargar si no se encuentra el elemento para actualizar
                }
            } else {
                alert('Error al actualizar disponibilidad: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error al cambiar disponibilidad:', error);
            alert('Error de conexión o del servidor.');
        }
    }

    // Eliminar Producto (JS)
    async function deleteProductAction(productId, productName) {
         if (!confirm(`¿Estás seguro de que quieres eliminar el producto "${productName}"? Esta acción no se puede deshacer.`)) return;

         try {
             // const response = await fetch('/admin/api/delete_menu_item.php', { // <-- Ruta API incorrecta para este sistema
             const response = await fetch('menu_manager.php', { // <-- CORREGIDO: Apuntar a la propia página
                    method: 'POST',
                    headers: {
                     // 'Content-Type': 'application/json', // No enviar JSON, sino FormData
                     'Content-Type': 'application/x-www-form-urlencoded', // O usar FormData directamente
                 },
                 // body: JSON.stringify({ id: productId }) 
                 body: new URLSearchParams({ // Enviar como datos de formulario
                    'action': 'delete_product', 
                    'product_id': productId
                 })
             });
              // Manejar posible respuesta no-JSON de error 400 o 500
              if (!response.ok) {
                  let errorBody = await response.text(); // Leer como texto
                  try {
                      // Intentar parsear como JSON si es posible (puede contener un mensaje de error)
                      const errorJson = JSON.parse(errorBody);
                      throw new Error(`HTTP error! status: ${response.status} - ${errorJson.message || errorBody}`);
                  } catch(e) {
                      // Si no es JSON, usar el texto directamente
                      throw new Error(`HTTP error! status: ${response.status} - ${errorBody}`);
                  }
              }
             
             const result = await response.json(); // Ahora debería ser JSON si la respuesta es OK

             if (result.success) {
                 alert('Producto eliminado con éxito.'); // Mensaje simplificado
                 const cardToRemove = document.querySelector(`.product-card-reorder-item-admin[data-product-id="${productId}"]`);
                 if (cardToRemove) cardToRemove.remove();
                    } else {
                 alert('Error al eliminar: ' + (result.message || 'Error desconocido desde la API'));
             }
         } catch (error) {
             console.error('Error al eliminar producto:', error);
             alert('Error de conexión o del servidor al eliminar producto. Detalles: ' + error.message);
         }
    }
    
     // --- Reordenamiento (SortableJS) ---
     function initSortable() {
         // Destruir instancias anteriores si existen
         destroySortable();
         
         const categoryContainers = productListContainer.querySelectorAll('.product-card-reorder-list-admin');
         categoryContainers.forEach(container => {
             const sortable = new Sortable(container, {
                 group: 'shared-products', // Permite arrastrar entre categorías si se desea (cambiar si no)
                 animation: 150,
                 ghostClass: 'sortable-ghost-admin', // Clase para el placeholder
                 chosenClass: 'sortable-chosen-admin', // Clase para el item elegido
                 dragClass: 'sortable-drag-admin', // Clase para el item siendo arrastrado
                 filter: '.btn, .product-actions-admin', // No iniciar drag desde botones
                 preventOnFilter: true, // Prevenir drag en elementos filtrados
                 onEnd: function (evt) {
                    // Opcional: Lógica si se necesita hacer algo inmediatamente después de soltar
                    // Por ejemplo, actualizar el atributo data-category si se mueve entre contenedores
                     const itemEl = evt.item; // elemento DOM que fue movido
                     const newContainer = evt.to; // contenedor donde se soltó
                     const categoryKey = newContainer.closest('.category-products-container-admin')?.id.replace('category-', '');
                     if (categoryKey) {
                         itemEl.dataset.category = categoryKey;
                         console.log(`Producto ${itemEl.dataset.productId} movido a categoría ${categoryKey}`);
                     }
                 }
             });
             sortableInstances.push(sortable);
         });
         document.querySelector('.admin-main-content').classList.add('reorder-mode-active');
         console.log(`Sortable iniciado para ${sortableInstances.length} contenedores.`);
     }

     function destroySortable() {
         sortableInstances.forEach(instance => instance.destroy());
         sortableInstances = [];
          document.querySelector('.admin-main-content').classList.remove('reorder-mode-active');
          console.log('Sortable destruido.');
     }

     async function saveOrderAction() {
         // Obtener orden directamente del DOM, más robusto que depender de sortableInstances
         const orderData = {};
         document.querySelectorAll('.category-products-container-admin').forEach(container => {
             const categoryKey = container.id.replace('category-', '');
             if (categoryKey) {
                 const productIds = Array.from(container.querySelectorAll('.product-card-reorder-item-admin[data-product-id]'))
                                           .map(el => el.dataset.productId);
                 orderData[categoryKey] = productIds;
             } else {
                 console.warn("Se encontró un contenedor de categoría sin ID válido:", container);
             }
          });
          
          console.log('Datos de orden a guardar:', orderData);
 
          saveOrderBtn.disabled = true;
          saveOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
 
          // Enviar usando FormData (resulta en x-www-form-urlencoded o multipart)
          const formData = new FormData();
          formData.append('action', 'save_order');
          formData.append('order', JSON.stringify(orderData));
          
          try {
              const response = await fetch('menu_manager.php', {
                method: 'POST',
                  body: formData // Enviar FormData directamente
              });
  
              // ... (resto de la función saveOrderAction) ...
              // Manejo de errores mejorado para capturar respuestas no-JSON
              if (!response.ok) {
                  let errorBody = await response.text();
                  try {
                      const errorJson = JSON.parse(errorBody);
                      throw new Error(`HTTP error ${response.status}: ${errorJson.message || errorBody}`);
                  } catch (e) {
                      throw new Error(`HTTP error ${response.status}: ${errorBody}`);
                  }
              }
              
              const result = await response.json(); // Esperar JSON en caso de éxito
              console.log('[saveOrderAction] Respuesta JSON parseada:', result);

              if (result.success) {
                  alert(result.message || 'Orden guardado con éxito.');
                  // Salir del modo reordenamiento
                  enableReorderBtn.style.display = 'inline-block';
                  saveOrderBtn.style.display = 'none';
                  cancelReorderBtn.style.display = 'none';
                  destroySortable();
                  // location.reload(); // Considera recargar si la actualización visual no es perfecta
                } else {
                      alert('Error al guardar el orden: ' + (result.message || 'Error desconocido desde el servidor'));
                  }
              } catch (error) {
                  console.error('Error en saveOrderAction:', error);
                  alert('Error de conexión o del servidor al guardar el orden: ' + error.message);
              } finally {
                  saveOrderBtn.disabled = false;
                  saveOrderBtn.innerHTML = '<i class="bi bi-save"></i> Guardar Orden';
              }
      }

      function cancelReorderAction() {
          if (confirm('¿Descartar cambios en el orden?')) {
             enableReorderBtn.style.display = 'inline-block';
             saveOrderBtn.style.display = 'none';
             cancelReorderBtn.style.display = 'none';
             destroySortable();
             // Recargar la página para restaurar el orden original visualmente
             location.reload();
         }
     }


    // --- Event Listeners ---
    console.log('[Menu Manager JS] Añadiendo listeners...'); // <-- LOG ANTES DE LISTENERS

    // Filtros de categoría
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            filterProducts(this.dataset.filter);
        });
    });

    // Abrir modal para añadir
    if(openAddProductModalBtn) {
        openAddProductModalBtn.addEventListener('click', prepareAddModal);
    }
    
    // Abrir modal para editar (delegación de eventos)
    productListContainer.addEventListener('click', handleProductActions);

    // Cambio de imagen
    productImageInput.addEventListener('change', handleImageChange);

    // Quitar imagen
     removeImageBtn.addEventListener('click', function() {
        productImageInput.value = ''; // Limpiar el input de archivo
        imagePreview.src = ''; // Limpiar src
        imagePreview.style.display = 'none'; // Ocultar img
        imagePreviewIcon.style.display = 'block'; // Mostrar icono
        showImageFeedback('Imagen actual será eliminada al guardar.');
        removeImageBtn.style.display = 'none';
        removeCurrentImageInput.value = '1'; // Marcar para eliminar en el backend
    });

    // Cambio de categoría (para mostrar/ocultar input de nueva categoría)
    productCategorySelect.addEventListener('change', handleCategoryChange);

    // Botones de Reordenamiento
    enableReorderBtn.addEventListener('click', function() {
        enableReorderBtn.style.display = 'none';
        saveOrderBtn.style.display = 'inline-block';
        cancelReorderBtn.style.display = 'inline-block';
        initSortable();
    });
    
    saveOrderBtn.addEventListener('click', saveOrderAction);
    cancelReorderBtn.addEventListener('click', cancelReorderAction);

    // Listener para el envío del formulario
    productForm.addEventListener('submit', handleProductFormSubmit);
    console.log('[Menu Manager JS] Listener de SUBMIT añadido a #productForm.'); // <-- LOG DESPUÉS DE AÑADIR LISTENER

        });
    </script>
<!-- FIN: Bloque JavaScript Específico de la Página -->

<?php
// ... existing code ...

function getProductDataById(string $productId): ?array {
    $dom = loadIndexDOM();
    if (!$dom) return null;
    $xpath = new DOMXPath($dom);
    $productNode = findProductNodeById($xpath, $productId);

    if ($productNode) {
        // Extraer datos del nodo DOM (similar a cómo se hace en getCurrentMenu pero para un solo producto)
        $name = $xpath->query('.//h5[contains(@class, \'product-name-admin\')]', $productNode)->item(0)->nodeValue ?? 'Error al leer nombre';
        $basePrice = $productNode->getAttribute('data-base-price') ?: 0;
        $currentPrice = $productNode->getAttribute('data-current-price') ?: $basePrice;
        $category = $productNode->getAttribute('data-category') ?? 'unknown';
        $imageUrlNode = $xpath->query('.//img[contains(@class, \'product-image-admin\')]', $productNode)->item(0);
        $imageUrl = $imageUrlNode ? $imageUrlNode->getAttribute('src') : '';
        $isAvailableNode = $xpath->query('.//button[contains(@class, \'toggle-availability\')]', $productNode)->item(0);
        $isAvailable = $isAvailableNode ? ($isAvailableNode->getAttribute('data-current-state') === '1') : true;
        // La descripción generalmente no está en la tarjeta de lista, sino en el atributo data-product del botón de edición
        $editBtn = $xpath->query('.//button[contains(@class, \'edit-product\')]', $productNode)->item(0);
        $description = '';
        if ($editBtn && $editBtn->hasAttribute('data-product')) {
            $dataProductJson = $editBtn->getAttribute('data-product');
            $dataProduct = json_decode(htmlspecialchars_decode($dataProductJson), true);
            $description = $dataProduct['description'] ?? '';
        }
        
        // Obtener la ruta absoluta de la imagen si es relativa
        if ($imageUrl && !preg_match('/^https?:\/\//', $imageUrl) && $imageUrl[0] === '/') {
            $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $imageUrl = $scheme . '://' . $host . $imageUrl;
        } elseif ($imageUrl && $imageUrl[0] !== '/' && !preg_match('/^https?:\/\//', $imageUrl)){
            // Asumir que es relativa a /images/menu/categoria/ si no es absoluta ni empieza con /
            // Esto puede necesitar ajuste basado en cómo se guardan las URLs realmente
            $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // La URL puede ser /images/menu/categoria/imagen.jpg o images/menu/categoria/imagen.jpg
            // si no empieza con /, asumimos que es relativa desde la raíz del sitio.
            // Si $imageUrl ya es 'images/menu/...' entonces $imageUrl = $scheme . '://' . $host . '/' . $imageUrl;
            // Pero si es solo 'imagen.jpg', necesitamos construir la ruta completa.
            // Por ahora, si no es absoluta y no empieza con /, la dejamos como está, el JS del modal debería resolverla
            // o la lógica de createProductNode/updateProduct debería haberla guardado correctamente.
        }

        return [
            'id' => $productId,
            'name' => trim($name),
            'description' => $description,
            'category' => $category,
            'base_price' => floatval($basePrice),
            'current_price' => floatval($currentPrice),
            'image_url' => $imageUrl,
            'is_available' => $isAvailable
            // Añadir más campos si son necesarios para la respuesta JSON (ej. tags, half_half_options)
        ];
    }
    return null;
}

// ... existing code ...
?>