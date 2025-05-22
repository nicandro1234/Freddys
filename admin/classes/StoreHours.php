<?php
class StoreHours {
    private $pdo;
    private $days_of_week_es = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo'
    ];
    
    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }
    
    public function getHours() {
        try {
            $query = "SELECT *, TIME_FORMAT(open_time, '%h:%i %p') as open_time_formatted, TIME_FORMAT(close_time, '%h:%i %p') as close_time_formatted FROM store_hours ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')";
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en StoreHours::getHours(): " . $e->getMessage());
            return [];
        }
    }
    
    public function updateHours($day, $openTime, $closeTime, $isClosed) {
        try {
            // Validar y formatear horas antes de guardar
            $formattedOpenTime = (!empty($openTime) && !$isClosed) ? date('H:i:s', strtotime($openTime)) : null;
            $formattedCloseTime = (!empty($closeTime) && !$isClosed) ? date('H:i:s', strtotime($closeTime)) : null;

            // Si está cerrado, los tiempos deben ser NULL
            if ($isClosed) {
                $formattedOpenTime = null;
                $formattedCloseTime = null;
            }
            
            // La lógica para $day === 'all' parece necesitar revisión o ser eliminada si no se usa.
            // Por ahora, nos enfocamos en la actualización por día individual.
            // if ($day === 'all') { ... } 
            
            $query = "UPDATE store_hours SET open_time = :open_time, close_time = :close_time, is_closed = :is_closed, updated_at = NOW() WHERE day_of_week = :day_of_week";
            $stmt = $this->pdo->prepare($query);
            
            $stmt->bindParam(':open_time', $formattedOpenTime, PDO::PARAM_STR);
            $stmt->bindParam(':close_time', $formattedCloseTime, PDO::PARAM_STR);
            $stmt->bindParam(':is_closed', $isClosed, PDO::PARAM_INT);
            $stmt->bindParam(':day_of_week', $day, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en StoreHours::updateHours() para el día {$day}: " . $e->getMessage());
            return false;
        }
    }
    
    public function getHoursByDay($day) {
        try {
            $query = "SELECT *, TIME_FORMAT(open_time, '%H:%i') as open_time_hm, TIME_FORMAT(close_time, '%H:%i') as close_time_hm FROM store_hours WHERE day_of_week = :day_of_week";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':day_of_week', $day, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en StoreHours::getHoursByDay({$day}): " . $e->getMessage());
            return null;
        }
    }
    
    public function getScheduleForToday() {
        $currentDayKey = strtolower(date('l'));
        return $this->getHoursByDay($currentDayKey);
    }
    
    public function isStoreOpen() {
        $status = $this->getCurrentStatusDetails();
        return $status['is_open'];
    }
    
    public function getCurrentStatusDetails() {
        $currentDayKey = strtolower(date('l'));
        $currentTime = time(); // Timestamp actual
        // $currentTimeFormatted = date('H:i:s', $currentTime); // Para logging o comparación si es necesario

        $default_status = [
            'is_open' => false,
            'day_of_week_name' => $this->days_of_week_es[$currentDayKey] ?? ucfirst($currentDayKey),
            'open_time_today' => null,
            'close_time_today' => null,
            'is_closed_today' => true,
            'message' => 'La tienda está cerrada',
            'next_open_details' => null
        ];

        try {
            $scheduleToday = $this->getHoursByDay($currentDayKey);

            if ($scheduleToday) {
                $default_status['open_time_today'] = $scheduleToday['open_time'];
                $default_status['close_time_today'] = $scheduleToday['close_time'];
                $default_status['is_closed_today'] = (bool)$scheduleToday['is_closed'];

                if ($scheduleToday['is_closed']) {
                    $default_status['message'] = 'Hoy ' . ($this->days_of_week_es[$currentDayKey] ?? ucfirst($currentDayKey)) . ' la tienda permanecerá cerrada.';
                    $default_status['next_open_details'] = $this->getNextOpenTimeDetails($currentDayKey, $scheduleToday['close_time']); // Pasar close_time para el día actual por si acaso
                    return $default_status;
                }

                if (empty($scheduleToday['open_time']) || empty($scheduleToday['close_time'])) {
                    $default_status['message'] = 'Horario no configurado para hoy.';
                     $default_status['is_closed_today'] = true; // Considerar cerrado si no hay horas
                    $default_status['next_open_details'] = $this->getNextOpenTimeDetails($currentDayKey, null);
                    return $default_status;
                }

                $openTimeToday = strtotime($scheduleToday['open_time']);
                $closeTimeToday = strtotime($scheduleToday['close_time']);

                $isOpen = false;
                if ($openTimeToday !== false && $closeTimeToday !== false) {
                    // Caso normal (ej. abre 10:00, cierra 22:00)
                    if ($openTimeToday <= $closeTimeToday) {
                        if ($currentTime >= $openTimeToday && $currentTime <= $closeTimeToday) {
                            $isOpen = true;
                        }
                    } else { // Caso cierre después de medianoche (ej. abre 22:00, cierra 02:00)
                        if ($currentTime >= $openTimeToday || $currentTime <= $closeTimeToday) {
                            $isOpen = true;
                        }
                    }
                }

                $default_status['is_open'] = $isOpen;
                $default_status['message'] = $isOpen ? 'La tienda está abierta' : 'La tienda está cerrada';
                if (!$isOpen) {
                    $default_status['next_open_details'] = $this->getNextOpenTimeDetails($currentDayKey, $scheduleToday['close_time']);
                }
                return $default_status;

            } else {
                // No hay registro para el día actual en store_hours
                $default_status['message'] = 'Horario no disponible para hoy.';
                $default_status['is_closed_today'] = true;
                $default_status['next_open_details'] = $this->getNextOpenTimeDetails($currentDayKey, null); // Buscar desde el inicio del día siguiente
                return $default_status;
            }

        } catch (PDOException $e) {
            error_log("Error PDO en StoreHours::getCurrentStatusDetails(): " . $e->getMessage());
            // Devolver un estado cerrado por defecto en caso de error de BD
            $default_status['message'] = 'Error al obtener el horario. Intente más tarde.';
            $default_status['is_closed_today'] = true; // Asegurar que esté como cerrado
            $default_status['next_open_details'] = null; // No podemos determinar la próxima apertura
            return $default_status;
        }
    }
    
    public function getNextOpenTimeDetails($startDayKey, $startTimeForStartDay = null) {
        $daysOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $currentDayIndex = array_search($startDayKey, $daysOrder);

        // Intentar encontrar apertura más tarde en el mismo día (startDayKey)
        if ($startTimeForStartDay !== null) {
            try {
                $query = "SELECT open_time FROM store_hours 
                          WHERE day_of_week = :day_of_week 
                          AND is_closed = 0 
                          AND open_time IS NOT NULL 
                          AND open_time > :start_time 
                          ORDER BY open_time ASC LIMIT 1";
                $stmt = $this->pdo->prepare($query);
                $stmt->bindParam(':day_of_week', $startDayKey, PDO::PARAM_STR);
                $stmt->bindParam(':start_time', $startTimeForStartDay, PDO::PARAM_STR);
                $stmt->execute();
                $nextSlot = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($nextSlot && !empty($nextSlot['open_time'])) {
                    return [
                        'day_key' => $startDayKey,
                        'day_name' => $this->days_of_week_es[$startDayKey] ?? ucfirst($startDayKey),
                        'time' => $nextSlot['open_time']
                    ];
                }
            } catch (PDOException $e) {
                error_log("Error en StoreHours::getNextOpenTimeDetails (mismo día) para {$startDayKey}: " . $e->getMessage());
            }
        }
        
        // Si no se encontró en el mismo día o no se proveyó startTimeForStartDay, buscar en los siguientes 7 días
        for ($i = 1; $i <= 7; $i++) {
            $nextDayIndex = ($currentDayIndex + $i) % 7;
            $nextDayKey = $daysOrder[$nextDayIndex];
            
            try {
                $query = "SELECT open_time FROM store_hours 
                          WHERE day_of_week = :day_of_week 
                          AND is_closed = 0 
                          AND open_time IS NOT NULL 
                          ORDER BY open_time ASC LIMIT 1"; // Tomar la primera hora de apertura del día
                $stmt = $this->pdo->prepare($query);
                $stmt->bindParam(':day_of_week', $nextDayKey, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && !empty($row['open_time'])) {
                    return [
                        'day_key' => $nextDayKey,
                        'day_name' => $this->days_of_week_es[$nextDayKey] ?? ucfirst($nextDayKey),
                        'time' => $row['open_time']
                    ];
                }
            } catch (PDOException $e) {
                error_log("Error en StoreHours::getNextOpenTimeDetails (día siguiente {$nextDayKey}): " . $e->getMessage());
                // Continuar al siguiente día en caso de error en uno específico
            }
        }
        return null; // No se encontró ninguna próxima apertura
    }
    
    public function isWithinOperatingHours($datetimeString) {
        try {
            $targetTimestamp = strtotime($datetimeString);
            if ($targetTimestamp === false) {
                error_log("Error en StoreHours::isWithinOperatingHours: formato de fecha inválido {$datetimeString}");
                return false; 
            }

            $dayKey = strtolower(date('l', $targetTimestamp));
            $timeToCheck = date('H:i:s', $targetTimestamp);

            $schedule = $this->getHoursByDay($dayKey);

            if (!$schedule || $schedule['is_closed'] || empty($schedule['open_time']) || empty($schedule['close_time'])) {
                return false; // Cerrado ese día o sin horario definido
            }

            $openTime = strtotime($schedule['open_time']);
            $closeTime = strtotime($schedule['close_time']);
            $timeToCompare = strtotime($timeToCheck); // Hora del día a verificar, sin fecha

            // Para la comparación, solo nos importan las horas, minutos, segundos.
            // Normalizamos los timestamps de open y close para que sean relativos al inicio del día 0 (timestamp 0).
            $openTimeNormalized = strtotime(date('Y-m-d', 0) . ' ' . $schedule['open_time']);
            $closeTimeNormalized = strtotime(date('Y-m-d', 0) . ' ' . $schedule['close_time']);
            $timeToCheckNormalized = strtotime(date('Y-m-d', 0) . ' ' . $timeToCheck);


            if ($openTimeNormalized <= $closeTimeNormalized) { // Horario no cruza medianoche
                return $timeToCheckNormalized >= $openTimeNormalized && $timeToCheckNormalized <= $closeTimeNormalized;
            } else { // Horario cruza medianoche
                return $timeToCheckNormalized >= $openTimeNormalized || $timeToCheckNormalized <= $closeTimeNormalized;
            }

        } catch (Exception $e) { // Captura errores de strtotime o PDOException de getHoursByDay
            error_log("Error en StoreHours::isWithinOperatingHours para {$datetimeString}: " . $e->getMessage());
            return false;
        }
    }
} 