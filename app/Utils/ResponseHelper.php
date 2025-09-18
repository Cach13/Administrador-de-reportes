<?php
// ========================================
// app/Utils/ResponseHelper.php
// Utilidad para manejo de respuestas HTTP y JSON
// ========================================

namespace App\Utils;

/**
 * ResponseHelper - Utilidad para respuestas HTTP estandarizadas
 * 
 * Proporciona métodos para:
 * - Respuestas JSON consistentes
 * - Códigos de estado HTTP apropiados
 * - Manejo de errores estandarizado
 * - Formateado de datos de respuesta
 */
class ResponseHelper
{
    /**
     * Códigos de respuesta estándar
     */
    const SUCCESS = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const NO_CONTENT = 204;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const VALIDATION_ERROR = 422;
    const INTERNAL_ERROR = 500;
    
    /**
     * Enviar respuesta JSON de éxito
     */
    public static function success($data = null, $message = 'Success', $code = self::SUCCESS)
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], $code);
    }
    
    /**
     * Enviar respuesta JSON de error
     */
    public static function error($message = 'Error', $code = self::BAD_REQUEST, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        return self::json($response, $code);
    }
    
    /**
     * Enviar respuesta JSON de validación
     */
    public static function validation($errors, $message = 'Validation failed')
    {
        return self::error($message, self::VALIDATION_ERROR, $errors);
    }
    
    /**
     * Enviar respuesta JSON de no encontrado
     */
    public static function notFound($message = 'Resource not found')
    {
        return self::error($message, self::NOT_FOUND);
    }
    
    /**
     * Enviar respuesta JSON de no autorizado
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        return self::error($message, self::UNAUTHORIZED);
    }
    
    /**
     * Enviar respuesta JSON de prohibido
     */
    public static function forbidden($message = 'Forbidden')
    {
        return self::error($message, self::FORBIDDEN);
    }
    
    /**
     * Enviar respuesta JSON de error interno
     */
    public static function serverError($message = 'Internal server error')
    {
        return self::error($message, self::INTERNAL_ERROR);
    }
    
    /**
     * Enviar respuesta JSON paginada
     */
    public static function paginated($data, $pagination, $message = 'Success')
    {
        return self::success([
            'items' => $data,
            'pagination' => $pagination
        ], $message);
    }
    
    /**
     * Enviar respuesta JSON con metadata
     */
    public static function withMeta($data, $meta, $message = 'Success')
    {
        return self::success([
            'data' => $data,
            'meta' => $meta
        ], $message);
    }
    
    /**
     * Enviar respuesta JSON básica
     */
    public static function json($data, $code = self::SUCCESS)
    {
        // Establecer headers
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($code);
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Redirección con mensaje flash
     */
    public static function redirect($url, $message = null, $type = 'info')
    {
        if ($message) {
            self::setFlashMessage($message, $type);
        }
        
        if (!headers_sent()) {
            header("Location: $url");
        }
        exit;
    }
    
    /**
     * Redirección de vuelta
     */
    public static function back($message = null, $type = 'info')
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer, $message, $type);
    }
    
    /**
     * Establecer mensaje flash en sesión
     */
    public static function setFlashMessage($message, $type = 'info')
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    /**
     * Obtener mensaje flash de sesión
     */
    public static function getFlashMessage()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        
        return null;
    }
    
    /**
     * Validar datos de entrada
     */
    public static function validate($data, $rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $fieldRules = explode('|', $rule);
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $singleRule) {
                if (strpos($singleRule, ':') !== false) {
                    list($ruleName, $ruleValue) = explode(':', $singleRule, 2);
                } else {
                    $ruleName = $singleRule;
                    $ruleValue = null;
                }
                
                $error = self::validateRule($field, $value, $ruleName, $ruleValue);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validar una regla específica
     */
    private static function validateRule($field, $value, $rule, $ruleValue = null)
    {
        switch ($rule) {
            case 'required':
                return empty($value) ? "$field is required" : null;
                
            case 'email':
                return !filter_var($value, FILTER_VALIDATE_EMAIL) ? "$field must be a valid email" : null;
                
            case 'min':
                return strlen($value) < $ruleValue ? "$field must be at least $ruleValue characters" : null;
                
            case 'max':
                return strlen($value) > $ruleValue ? "$field must not exceed $ruleValue characters" : null;
                
            case 'numeric':
                return !is_numeric($value) ? "$field must be numeric" : null;
                
            case 'alpha':
                return !ctype_alpha($value) ? "$field must contain only letters" : null;
                
            case 'alphanumeric':
                return !ctype_alnum($value) ? "$field must contain only letters and numbers" : null;
                
            default:
                return null;
        }
    }
    
    /**
     * Formatear datos para respuesta API
     */
    public static function formatApiData($data, $type = 'object')
    {
        if ($type === 'collection' && is_array($data)) {
            return array_map([self::class, 'formatSingleItem'], $data);
        }
        
        return self::formatSingleItem($data);
    }
    
    /**
     * Formatear un elemento individual
     */
    private static function formatSingleItem($item)
    {
        if (is_array($item)) {
            // Formatear fechas
            foreach ($item as $key => $value) {
                if (self::isDate($key) && $value) {
                    $item[$key . '_formatted'] = date('d/m/Y H:i', strtotime($value));
                }
                
                // Formatear montos
                if (self::isAmount($key) && is_numeric($value)) {
                    $item[$key . '_formatted'] = '$' . number_format($value, 2);
                }
            }
        }
        
        return $item;
    }
    
    /**
     * Verificar si un campo es fecha
     */
    private static function isDate($field)
    {
        $dateFields = ['created_at', 'updated_at', 'upload_date', 'payment_date', 'trip_date', 'generation_date'];
        return in_array($field, $dateFields) || strpos($field, '_date') !== false || strpos($field, '_at') !== false;
    }
    
    /**
     * Verificar si un campo es monto
     */
    private static function isAmount($field)
    {
        $amountFields = ['amount', 'subtotal', 'total', 'payment', 'deduction', 'haul_rate'];
        return in_array($field, $amountFields) || strpos($field, '_amount') !== false || 
               strpos($field, '_total') !== false || strpos($field, '_payment') !== false;
    }
    
    /**
     * Crear respuesta de archivo para descarga
     */
    public static function download($filePath, $fileName = null, $mimeType = null)
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
        }
        
        $fileName = $fileName ?: basename($filePath);
        $mimeType = $mimeType ?: mime_content_type($filePath);
        
        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
        }
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Crear respuesta de archivo para visualización inline
     */
    public static function inline($filePath, $mimeType = null)
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
        }
        
        $mimeType = $mimeType ?: mime_content_type($filePath);
        
        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($filePath));
        }
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Obtener información del cliente
     */
    public static function getClientInfo()
    {
        return [
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Obtener IP del cliente
     */
    public static function getClientIP()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Limpiar y sanitizar entrada
     */
    public static function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Verificar si la petición es AJAX
     */
    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Verificar método HTTP
     */
    public static function isMethod($method)
    {
        return strtoupper($_SERVER['REQUEST_METHOD']) === strtoupper($method);
    }
    
    /**
     * Generar token CSRF
     */
    public static function generateCSRFToken()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }
    
    /**
     * Verificar token CSRF
     */
    public static function verifyCSRFToken($token)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>