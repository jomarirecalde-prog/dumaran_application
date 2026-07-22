<?php
/**
 * Interview Scheduler Helper
 * Handles automatic interview scheduling
 */

class InterviewScheduler {
    /**
     * Calculate the next available interview slot
     * Interviews are scheduled Monday-Friday, 8am-12pm or 1pm-5pm
     * 
     * @param PDO $db Database connection
     * @return array ['date' => 'Y-m-d', 'time' => 'H:i:s']
     */
    public static function getNextAvailableSlot($db) {
        // Define time slots
        $morningSlots = ['08:00:00', '09:00:00', '10:00:00', '11:00:00'];
        $afternoonSlots = ['13:00:00', '14:00:00', '15:00:00', '16:00:00'];
        $allSlots = array_merge($morningSlots, $afternoonSlots);
        
        // Set timezone (should match config.php)
        $timezone = date_default_timezone_get() ?: 'Asia/Jakarta';
        
        // Start from tomorrow (to give at least 1 day notice)
        $currentDate = new DateTime('tomorrow', new DateTimeZone($timezone));
        $maxDaysToCheck = 30; // Check up to 30 days ahead
        $daysChecked = 0;
        
        while ($daysChecked < $maxDaysToCheck) {
            $dayOfWeek = (int)$currentDate->format('w'); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
            
            // Skip weekends (0 = Sunday, 6 = Saturday)
            if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                $currentDate->modify('+1 day');
                $daysChecked++;
                continue;
            }
            
            $dateStr = $currentDate->format('Y-m-d');
            
            // Check all time slots for this day
            foreach ($allSlots as $timeSlot) {
                $datetimeStr = $dateStr . ' ' . $timeSlot;
                
                // Check if this slot is already taken
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM applications 
                    WHERE interview_date = ? AND interview_time = ? 
                    AND status = 'approved'
                ");
                $stmt->execute([$dateStr, $timeSlot]);
                $result = $stmt->fetch();
                
                if ($result['count'] == 0) {
                    // Slot is available
                    return [
                        'date' => $dateStr,
                        'time' => $timeSlot
                    ];
                }
            }
            
            // No available slots for this day, try next day
            $currentDate->modify('+1 day');
            $daysChecked++;
        }
        
        // If no slot found in 30 days, return the first Monday from tomorrow
        $fallbackDate = new DateTime('tomorrow', new DateTimeZone($timezone));
        while ((int)$fallbackDate->format('w') != 1) { // 1 = Monday
            $fallbackDate->modify('+1 day');
        }
        
        return [
            'date' => $fallbackDate->format('Y-m-d'),
            'time' => '08:00:00'
        ];
    }
    
    /**
     * Format interview date and time for display
     * 
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i:s format
     * @return string Formatted date and time
     */
    public static function formatInterviewDateTime($date, $time) {
        $timezone = date_default_timezone_get() ?: 'Asia/Jakarta';
        $dateTime = new DateTime($date . ' ' . $time, new DateTimeZone($timezone));
        return $dateTime->format('l, F j, Y \a\t g:i A');
    }
}

