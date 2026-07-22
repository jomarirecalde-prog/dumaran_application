<?php
/**
 * Requirement Validator Service
 * Validates if an applicant meets all requirements for a position
 */

require_once __DIR__ . '/../models.php';

class RequirementValidator {
    private $userModel;
    private $positionModel;
    
    public function __construct() {
        $this->userModel = new User();
        $this->positionModel = new Position();
    }
    
    /**
     * Validate all requirements for a position
     * 
     * @param int $userId The applicant's user ID
     * @param int $positionId The position ID
     * @return array Validation result with 'meets_requirements' (bool) and 'details' (array)
     */
    public function validateRequirements($userId, $positionId) {
        $position = $this->positionModel->findById($positionId);
        $user = $this->userModel->findById($userId);
        
        if (!$position) {
            throw new Exception('Position not found');
        }
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $validation = [
            'meets_requirements' => true,
            'details' => []
        ];
        
        // 1. Check experience requirement
        $totalExperience = $this->userModel->getTotalExperience($userId);
        $minExperience = $position['min_experience_years'] ?? 0;
        $meetsExperience = $totalExperience >= $minExperience;
        
        $validation['details']['experience'] = [
            'required' => $minExperience,
            'has' => $totalExperience,
            'meets' => $meetsExperience,
            'message' => $meetsExperience 
                ? "You have {$totalExperience} years of experience (Required: {$minExperience})"
                : "You have {$totalExperience} years of experience (Required: {$minExperience})"
        ];
        
        if (!$meetsExperience) {
            $validation['meets_requirements'] = false;
        }
        
        // 2. Check career service eligibilities
        if (!empty($position['career_service_eligibilities'])) {
            $requiredEligibilities = $this->parseEligibilities($position['career_service_eligibilities']);
            $applicantEligibilities = $this->parseEligibilities($user['career_service_eligibilities'] ?? '');
            
            if (!empty($requiredEligibilities)) {
                // Check if applicant has at least one of the required eligibilities
                $hasRequiredEligibility = false;
                $matchingEligibilities = [];
                
                foreach ($requiredEligibilities as $required) {
                    if (in_array($required, $applicantEligibilities)) {
                        $hasRequiredEligibility = true;
                        $matchingEligibilities[] = $required;
                    }
                }
                
                $validation['details']['career_service_eligibilities'] = [
                    'required' => $requiredEligibilities,
                    'has' => $applicantEligibilities,
                    'matching' => $matchingEligibilities,
                    'meets' => $hasRequiredEligibility,
                    'message' => $hasRequiredEligibility
                        ? "You have the required eligibility: " . implode(', ', $matchingEligibilities)
                        : "Required eligibilities: " . implode(', ', $requiredEligibilities) . 
                          (empty($applicantEligibilities) 
                            ? ". You have not specified any eligibilities in your profile."
                            : ". You have: " . implode(', ', $applicantEligibilities))
                ];
                
                if (!$hasRequiredEligibility) {
                    $validation['meets_requirements'] = false;
                }
            }
        }
        
        // 3. Check training hours requirement
        if (!empty($position['training_hours']) && $position['training_hours'] > 0) {
            $requiredTrainingHours = (int)$position['training_hours'];
            $applicantTrainingPrograms = $user['training_programs'] ?? '';
            
            // Extract hours from training_programs if possible (simple heuristic)
            // This assumes training_programs might contain hours information
            // In a more sophisticated system, you might parse structured data
            $hasTrainingHours = !empty($applicantTrainingPrograms);
            
            // For now, we just check if they have training_programs documented
            // In a real system, you might want to parse hours from the training_programs text
            // or have a separate training_hours field
            $validation['details']['training_hours'] = [
                'required' => $requiredTrainingHours,
                'has_training' => $hasTrainingHours,
                'meets' => $hasTrainingHours, // Simplified check
                'message' => $hasTrainingHours
                    ? "You have documented training programs"
                    : "Required training hours: {$requiredTrainingHours}. Please document your training programs in your profile."
            ];
            
            // Note: This is a simplified check. You may want to enhance this to actually parse
            // and sum training hours from the training_programs field or a separate table
            if (!$hasTrainingHours) {
                $validation['meets_requirements'] = false;
            }
        }
        
        // 4. Check salary grade requirement (if applicable)
        // This is typically not a requirement that applicants need to "meet"
        // but we can include it for informational purposes
        if (!empty($position['salary_grade'])) {
            $validation['details']['salary_grade'] = [
                'position_grade' => $position['salary_grade'],
                'info' => "This position is classified as {$position['salary_grade']}"
            ];
        }
        
        // Generate summary message
        $validation['summary'] = $this->generateSummary($validation);
        
        return $validation;
    }
    
    /**
     * Parse eligibilities from comma-separated string or array
     * 
     * @param string|array $eligibilities
     * @return array Array of eligibility strings
     */
    private function parseEligibilities($eligibilities) {
        if (empty($eligibilities)) {
            return [];
        }
        
        if (is_array($eligibilities)) {
            return $eligibilities;
        }
        
        // Handle comma-separated string
        $parsed = array_map('trim', explode(',', $eligibilities));
        return array_filter($parsed, function($item) {
            return !empty($item);
        });
    }
    
    /**
     * Generate a human-readable summary of validation results
     * 
     * @param array $validation Validation result
     * @return string Summary message
     */
    private function generateSummary($validation) {
        $meetsAll = $validation['meets_requirements'];
        $details = $validation['details'];
        
        if ($meetsAll) {
            $messages = ["✓ You meet all requirements for this position:"];
        } else {
            $messages = ["⚠ You do not meet all requirements for this position:"];
        }
        
        foreach ($details as $key => $detail) {
            if (isset($detail['meets'])) {
                $icon = $detail['meets'] ? '✓' : '✗';
                $messages[] = "  {$icon} " . ucfirst(str_replace('_', ' ', $key)) . ": " . $detail['message'];
            }
        }
        
        return implode("\n", $messages);
    }
    
    /**
     * Get requirement validation details in a structured format for display
     * 
     * @param int $userId
     * @param int $positionId
     * @return array Structured validation result
     */
    public function getValidationDetails($userId, $positionId) {
        return $this->validateRequirements($userId, $positionId);
    }
}
