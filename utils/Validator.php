<?php

/**
 * ============================================================================
 * VALIDATOR HELPER CLASS
 * ============================================================================
 * 
 * This class provides a comprehensive set of validation methods for
 * validating user input, form data, and API requests. It helps ensure
 * data integrity and security by catching invalid data early.
 * 
 * Features:
 * - String validation (email, URL, alpha, numeric, etc.)
 * - Length and range validation
 * - File validation
 * - Custom validation rules
 * - Error message collection
 * 
 * Usage:
 * $validator = new Validator();
 * $validator->validate($_POST, [
 *     'email' => 'required|email',
 *     'password' => 'required|min:6',
 *     'age' => 'integer|min:18|max:100'
 * ]);
 * 
 * @package EcommerceElectronics\Utils
 * @author School Project
 * @version 1.0.0
 * ============================================================================
 */

class Validator {
    
    /**
     * Array to store validation error messages
     * Key is the field name, value is array of error messages
     * 
     * @var array
     */
    private $errors = [];
    
    /**
     * Data being validated
     * 
     * @var array
     */
    private $data = [];
    
    /**
     * Validation rules
     * 
     * @var array
     */
    private $rules = [];
    
    /**
     * Custom error messages
     * Allows overriding default error messages
     * 
     * @var array
     */
    private $messages = [];
    
    
    /**
     * Constructor
     * Initialize validator with data and optional rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules (optional)
     */
    public function __construct($data = [], $rules = []) {
        $this->data = $data;
        $this->rules = $rules;
    }
    
    
    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate (overrides constructor data)
     * @param array $rules Rules to apply (overrides constructor rules)
     * @return bool True if all validations pass, false if any fail
     */
    public function validate($data = null, $rules = null) {
        if ($data !== null) {
            $this->data = $data;
        }
        if ($rules !== null) {
            $this->rules = $rules;
        }
        
        $this->errors = [];
        
        // Validate each field according to its rules
        foreach ($this->rules as $field => $ruleString) {
            $fieldValue = $this->data[$field] ?? null;
            $fieldRules = explode('|', $ruleString);
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $fieldValue, trim($rule));
            }
        }
        
        return empty($this->errors);
    }
    
    
    /**
     * Apply a single validation rule to a field
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule The rule string (e.g., "required", "email", "min:5")
     * @return void
     */
    private function applyRule($field, $value, $rule) {
        // Skip empty validation rules
        if (empty($rule)) {
            return;
        }
        
        // Parse rule name and parameters
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
        
        // Call the appropriate validation method
        $methodName = 'validate' . ucfirst($ruleName);
        
        if (method_exists($this, $methodName)) {
            $this->$methodName($field, $value, ...$ruleParams);
        }
    }
    
    
    // ========================================================================
    // VALIDATION RULES
    // ========================================================================
    
    /**
     * Validate field is required (not empty)
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateRequired($field, $value) {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->addError($field, "$field is required");
        }
    }
    
    
    /**
     * Validate field is a valid email address
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateEmail($field, $value) {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "$field must be a valid email address");
        }
    }
    
    
    /**
     * Validate field is a valid URL
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateUrl($field, $value) {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "$field must be a valid URL");
        }
    }
    
    
    /**
     * Validate field contains only alphabetic characters
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateAlpha($field, $value) {
        if ($value !== null && !ctype_alpha($value)) {
            $this->addError($field, "$field must contain only alphabetic characters");
        }
    }
    
    
    /**
     * Validate field contains only alphanumeric characters
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateAlphaNum($field, $value) {
        if ($value !== null && !ctype_alnum($value)) {
            $this->addError($field, "$field must contain only letters and numbers");
        }
    }
    
    
    /**
     * Validate field contains only numeric characters
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateNumeric($field, $value) {
        if ($value !== null && !is_numeric($value)) {
            $this->addError($field, "$field must be numeric");
        }
    }
    
    
    /**
     * Validate field is an integer
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateInteger($field, $value) {
        if ($value !== null && !is_int($value) && !ctype_digit((string)$value)) {
            $this->addError($field, "$field must be an integer");
        }
    }
    
    
    /**
     * Validate field is a boolean value
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validateBoolean($field, $value) {
        if ($value !== null && !in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
            $this->addError($field, "$field must be a boolean value");
        }
    }
    
    
    /**
     * Validate field is a valid date
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $format Date format (default: Y-m-d)
     * @return void
     */
    private function validateDate($field, $value, $format = 'Y-m-d') {
        if ($value !== null) {
            $date = DateTime::createFromFormat($format, $value);
            if (!$date || $date->format($format) !== $value) {
                $this->addError($field, "$field must be a valid date in format $format");
            }
        }
    }
    
    
    /**
     * Validate field length is minimum
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param int $min Minimum length
     * @return void
     */
    private function validateMin($field, $value, $min) {
        if ($value !== null) {
            $length = is_array($value) ? count($value) : strlen($value);
            if ($length < $min) {
                $this->addError($field, "$field must be at least $min characters");
            }
        }
    }
    
    
    /**
     * Validate field length is maximum
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param int $max Maximum length
     * @return void
     */
    private function validateMax($field, $value, $max) {
        if ($value !== null) {
            $length = is_array($value) ? count($value) : strlen($value);
            if ($length > $max) {
                $this->addError($field, "$field must not exceed $max characters");
            }
        }
    }
    
    
    /**
     * Validate field is exactly a specific length
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param int $length Exact length
     * @return void
     */
    private function validateLength($field, $value, $length) {
        if ($value !== null) {
            $actualLength = strlen($value);
            if ($actualLength !== (int)$length) {
                $this->addError($field, "$field must be exactly $length characters");
            }
        }
    }
    
    
    /**
     * Validate field value is within a range
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return void
     */
    private function validateBetween($field, $value, $min, $max) {
        if ($value !== null && (is_numeric($value) && ($value < $min || $value > $max))) {
            $this->addError($field, "$field must be between $min and $max");
        }
    }
    
    
    /**
     * Validate field value matches one of allowed values
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $allowedValues Comma-separated allowed values
     * @return void
     */
    private function validateIn($field, $value, $allowedValues) {
        if ($value !== null) {
            $allowed = explode(',', $allowedValues);
            if (!in_array($value, $allowed)) {
                $this->addError($field, "$field must be one of: " . $allowedValues);
            }
        }
    }
    
    
    /**
     * Validate field value does not match any disallowed values
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $disallowedValues Comma-separated disallowed values
     * @return void
     */
    private function validateNotIn($field, $value, $disallowedValues) {
        if ($value !== null) {
            $disallowed = explode(',', $disallowedValues);
            if (in_array($value, $disallowed)) {
                $this->addError($field, "$field cannot be one of: " . $disallowedValues);
            }
        }
    }
    
    
    /**
     * Validate field matches a regex pattern
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $pattern Regex pattern (without delimiters)
     * @return void
     */
    private function validateRegex($field, $value, $pattern) {
        if ($value !== null && !preg_match("/$pattern/", $value)) {
            $this->addError($field, "$field format is invalid");
        }
    }
    
    
    /**
     * Validate field matches another field value
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $otherField The field to match
     * @return void
     */
    private function validateMatches($field, $value, $otherField) {
        if ($value !== null && $value !== ($this->data[$otherField] ?? null)) {
            $this->addError($field, "$field must match $otherField");
        }
    }
    
    
    /**
     * Validate a phone number format
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @return void
     */
    private function validatePhone($field, $value) {
        if ($value !== null) {
            // Basic international phone number validation
            if (!preg_match('/^[+]?[(]?[0-9]{1,4}[)]?[-\s.]?[(]?[0-9]{1,4}[)]?[-\s.]?[0-9]{1,9}$/', $value)) {
                $this->addError($field, "$field must be a valid phone number");
            }
        }
    }
    
    
    /**
     * Add an error message for a field
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    
    /**
     * Get all validation errors
     * 
     * @return array Array of errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    
    /**
     * Check if validation failed
     * 
     * @return bool True if there are validation errors
     */
    public function fails() {
        return !empty($this->errors);
    }
    
    
    /**
     * Check if validation passed
     * 
     * @return bool True if no validation errors
     */
    public function passes() {
        return empty($this->errors);
    }
    
    
    /**
     * Get first error for a field
     * 
     * @param string $field Field name
     * @return string|null First error message for field, or null
     */
    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }
    
    
    /**
     * Set custom error message for a rule
     * 
     * @param string $field Field name
     * @param string $rule Rule name
     * @param string $message Custom error message
     * @return void
     */
    public function setMessage($field, $rule, $message) {
        $this->messages[$field . '.' . $rule] = $message;
    }
}

?>
