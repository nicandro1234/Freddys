<?php
class DeliveryZone {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function calculateDeliveryFee($orderAmount, $distanceKm) {
        $query = "SELECT * FROM delivery_zones WHERE id = 1"; // Por ahora solo usamos la zona principal
        $result = $this->conn->query($query);
        
        if ($zone = $result->fetch_assoc()) {
            // Si el pedido es mayor al umbral, envío gratis dentro del radio
            if ($orderAmount >= $zone['free_delivery_threshold'] && $distanceKm <= $zone['radius_km']) {
                return 0;
            }
            
            // Cálculo base del costo de envío
            $deliveryFee = $zone['base_price'];
            
            // Si la distancia es mayor al radio, cobrar extra por km adicional
            if ($distanceKm > $zone['radius_km']) {
                $extraKm = $distanceKm - $zone['radius_km'];
                $deliveryFee += ($extraKm * $zone['extra_km_price']);
            }
            
            return $deliveryFee;
        }
        
        // Si no hay zona configurada, usar valores por defecto
        return 20 + (max(0, $distanceKm - 2) * 5);
    }
    
    public function isWithinDeliveryRadius($distanceKm) {
        $query = "SELECT radius_km FROM delivery_zones WHERE id = 1";
        $result = $this->conn->query($query);
        
        if ($zone = $result->fetch_assoc()) {
            return $distanceKm <= $zone['radius_km'];
        }
        
        return $distanceKm <= 5; // Radio por defecto de 5km
    }
    
    public function getDeliveryZone($id) {
        $query = "SELECT * FROM delivery_zones WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function updateDeliveryZone($id, $name, $basePrice, $freeDeliveryThreshold, $radiusKm, $extraKmPrice) {
        $query = "UPDATE delivery_zones SET 
                 name = ?, 
                 base_price = ?, 
                 free_delivery_threshold = ?, 
                 radius_km = ?, 
                 extra_km_price = ?,
                 updated_at = NOW()
                 WHERE id = ?";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sddddi", $name, $basePrice, $freeDeliveryThreshold, $radiusKm, $extraKmPrice, $id);
        return $stmt->execute();
    }
    
    public function getAllDeliveryZones() {
        $query = "SELECT * FROM delivery_zones";
        $result = $this->conn->query($query);
        
        $zones = [];
        while ($row = $result->fetch_assoc()) {
            $zones[] = $row;
        }
        
        return $zones;
    }
} 