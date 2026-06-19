<?php
declare(strict_types=1);

namespace App;

class Validator
{
    /**
     * Sanitize a string input or an array of strings
     * 
     * @param mixed $value The value to sanitize
     * @param bool $allowHtml Whether to allow HTML tags (default: false)
     * @return mixed (string|array|null)
     */
    public static function sanitizeString($value, bool $allowHtml = false)
    {
        // If input is array, sanitize each value recursively
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $val) {
                $sanitized[$key] = self::sanitizeString($val, $allowHtml);
            }
            return $sanitized;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string)$value);
        
        if (!$allowHtml) {
            $value = strip_tags($value);
        }
        
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize an email address
     * 
     * @param mixed $value The email to sanitize
     * @return string|null
     */
    public static function sanitizeEmail($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $email = filter_var(trim((string)$value), FILTER_SANITIZE_EMAIL);
        return $email !== false ? $email : null;
    }

    /**
     * Sanitize an integer value
     * 
     * @param mixed $value The value to sanitize
     * @return int|null
     */
    public static function sanitizeInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int !== false ? $int : null;
    }

    /**
     * Validate email format
     * 
     * @param string|null $email The email to validate
     * @return bool
     */
    public static function isValidEmail(?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate required fields in an array
     * 
     * @param array $data The data array to validate
     * @param array $requiredFields Array of required field names
     * @return array Array of missing field names (empty if all present)
     */
    public static function validateRequired(array $data, array $requiredFields): array
    {
        $missing = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }

}
