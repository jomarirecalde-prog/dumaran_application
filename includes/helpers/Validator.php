<?php
/**
 * Validator Class
 * Handles input validation
 */

class Validator {
    private $errors = [];
    private $data = [];

    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * Validate required field
     */
    public function required($field, $message = null) {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' is required';
        }
        return $this;
    }

    /**
     * Validate email
     */
    public function email($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? 'Invalid email format';
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field][] = $message ?? ucfirst($field) . " must be at least {$length} characters";
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function maxLength($field, $length, $message = null) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field][] = $message ?? ucfirst($field) . " must not exceed {$length} characters";
        }
        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric($field, $message = null) {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' must be a number';
        }
        return $this;
    }

    /**
     * Validate integer value
     */
    public function integer($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' must be an integer';
        }
        return $this;
    }

    /**
     * Validate minimum value
     */
    public function min($field, $min, $message = null) {
        if (isset($this->data[$field]) && $this->data[$field] < $min) {
            $this->errors[$field][] = $message ?? ucfirst($field) . " must be at least {$min}";
        }
        return $this;
    }

    /**
     * Validate maximum value
     */
    public function max($field, $max, $message = null) {
        if (isset($this->data[$field]) && $this->data[$field] > $max) {
            $this->errors[$field][] = $message ?? ucfirst($field) . " must not exceed {$max}";
        }
        return $this;
    }

    /**
     * Validate value in array
     */
    public function in($field, $values, $message = null) {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' has an invalid value';
        }
        return $this;
    }

    /**
     * Validate URL
     */
    public function url($field, $message = null) {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = $message ?? 'Invalid URL format';
        }
        return $this;
    }

    /**
     * Validate date format
     */
    public function date($field, $format = 'Y-m-d', $message = null) {
        if (isset($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field][] = $message ?? 'Invalid date format';
            }
        }
        return $this;
    }

    /**
     * Validate date is in the future
     */
    public function futureDate($field, $message = null) {
        if (isset($this->data[$field])) {
            $date = strtotime($this->data[$field]);
            if ($date === false || $date <= time()) {
                $this->errors[$field][] = $message ?? ucfirst($field) . ' must be a future date';
            }
        }
        return $this;
    }

    /**
     * Validate phone number
     */
    public function phone($field, $message = null) {
        if (isset($this->data[$field])) {
            $phone = preg_replace('/[^0-9]/', '', $this->data[$field]);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $this->errors[$field][] = $message ?? 'Invalid phone number';
            }
        }
        return $this;
    }

    /**
     * Validate field matches another field
     */
    public function matches($field, $matchField, $message = null) {
        if (isset($this->data[$field]) && isset($this->data[$matchField]) && $this->data[$field] !== $this->data[$matchField]) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' must match ' . $matchField;
        }
        return $this;
    }

    /**
     * Custom validation rule
     */
    public function custom($field, $callback, $message = null) {
        if (isset($this->data[$field]) && !$callback($this->data[$field])) {
            $this->errors[$field][] = $message ?? ucfirst($field) . ' is invalid';
        }
        return $this;
    }

    /**
     * Validate file upload
     */
    public function file($field, $allowedTypes = [], $maxSize = null, $message = null) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                $this->errors[$field][] = 'File upload error';
                return $this;
            }

            // Check file type
            if (!empty($allowedTypes)) {
                $fileType = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                if (!in_array($fileType, $allowedTypes)) {
                    $this->errors[$field][] = $message ?? 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
                }
            }

            // Check file size
            if ($maxSize && $_FILES[$field]['size'] > $maxSize) {
                $maxSizeMB = $maxSize / (1024 * 1024);
                $this->errors[$field][] = "File size must not exceed {$maxSizeMB}MB";
            }
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails() {
        return !$this->passes();
    }

    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function getFirstError($field = null) {
        if ($field) {
            return $this->errors[$field][0] ?? null;
        }
        
        // Return first error overall
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }
        
        return null;
    }

    /**
     * Get all error messages as flat array
     */
    public function getAllMessages() {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }
}
