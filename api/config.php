<?php

error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$isProduction = getenv('APP_ENV') === 'production';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [];
if ($isProduction) {
    $allowedOrigins = array_filter(explode(',', getenv('ALLOWED_ORIGINS') ?: ''));
    if (empty($allowedOrigins) && !empty($_SERVER['HTTP_HOST'])) {
        // Check for HTTPS - support X-Forwarded-Proto header for proxies (like Render)
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
        $protocol = $isSecure ? 'https' : 'http';
        $allowedOrigins[] = $protocol . '://' . $_SERVER['HTTP_HOST'];
    }
} else {
    $allowedOrigins = ['http://localhost:5000', 'http://127.0.0.1:5000', 'http://localhost', 'http://127.0.0.1'];
}

$allowedOrigin = null;
if (!empty($requestOrigin)) {
    foreach ($allowedOrigins as $origin) {
        if ($requestOrigin === trim($origin)) {
            $allowedOrigin = $requestOrigin;
            break;
        }
    }
}

if ($allowedOrigin) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header("Access-Control-Allow-Credentials: true");
} elseif ($isProduction && !empty($_SERVER['HTTP_HOST'])) {
    // Check for HTTPS - support X-Forwarded-Proto header for proxies (like Render)
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $protocol = $isSecure ? 'https' : 'http';
    $currentOrigin = $protocol . '://' . $_SERVER['HTTP_HOST'];
    header("Access-Control-Allow-Origin: $currentOrigin");
    header("Access-Control-Allow-Credentials: true");
} elseif (!$isProduction) {
    header("Access-Control-Allow-Origin: http://localhost:5000");
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// إعدادات مسار Session
$sessionPath = sys_get_temp_dir() . '/php_sessions';
if (!file_exists($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}

// تحديد اسم موحد للـ Session
session_name('SCHOOL_GUIDANCE_SESSION');

// إعدادات Session محسّنة
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $isProduction ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 0);
ini_set('session.cookie_path', '/');

// عدم تعيين session.cookie_domain في Production لتجنب مشاكل الـ cookies
// السماح للمتصفح بتحديد الـ domain تلقائياً

function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv();

$appEnv = getenv('APP_ENV') ?: 'development';

$supabaseUrl = getenv('SUPABASE_URL');
$supabaseServiceKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
$supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

if (!$supabaseUrl || !$supabaseServiceKey || !$supabaseAnonKey) {
    error_log('❌ CRITICAL: Missing required Supabase environment variables!');
    error_log('Please set: SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_ANON_KEY');
    
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'error' => $appEnv === 'production' 
            ? 'خطأ في إعدادات قاعدة البيانات. يرجى التواصل مع المسؤول' 
            : 'Missing required environment variables: SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY, SUPABASE_ANON_KEY. Please check your .env file or server configuration.'
    ]));
}

if (!filter_var($supabaseUrl, FILTER_VALIDATE_URL)) {
    error_log('❌ Invalid Supabase URL format: ' . $supabaseUrl);
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'error' => 'Invalid Supabase URL configuration'
    ]));
}

/**
 * Supabase REST API Client
 */
class SupabaseClient {
    private $baseUrl;
    private $serviceKey;
    private $anonKey;
    
    public function __construct($url, $serviceKey, $anonKey) {
        $this->baseUrl = rtrim($url, '/') . '/rest/v1';
        $this->serviceKey = $serviceKey;
        $this->anonKey = $anonKey;
    }
    
    /**
     * Make a REST API request
     */
    private function request($method, $endpoint, $data = null, $useServiceKey = true, $returnData = false) {
        $url = $this->baseUrl . $endpoint;
        $apiKey = $useServiceKey ? $this->serviceKey : $this->anonKey;
        
        $headers = [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
        
        if ($returnData) {
            $headers[] = 'Prefer: return=representation';
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("❌ Supabase API Error: $error");
            throw new Exception("API request failed: $error");
        }
        
        if ($httpCode >= 400) {
            $errorDetails = $response ? " - Details: $response" : "";
            error_log("❌ Supabase API HTTP Error $httpCode$errorDetails");
            throw new Exception("API returned error: HTTP $httpCode");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Select records from a table
     */
    public function select($table, $columns = '*', $filters = []) {
        $endpoint = "/$table?select=$columns";
        
        foreach ($filters as $key => $value) {
            $endpoint .= "&$key=eq.$value";
        }
        
        return $this->request('GET', $endpoint);
    }
    
    /**
     * Insert a record
     */
    public function insert($table, $data, $returnData = true) {
        return $this->request('POST', "/$table", $data, true, $returnData);
    }
    
    /**
     * Update records
     */
    public function update($table, $data, $filters = [], $returnData = false) {
        $endpoint = "/$table";
        
        $first = true;
        foreach ($filters as $key => $value) {
            $endpoint .= ($first ? '?' : '&') . "$key=eq.$value";
            $first = false;
        }
        
        return $this->request('PATCH', $endpoint, $data, true, $returnData);
    }
    
    /**
     * Delete records
     */
    public function delete($table, $filters = []) {
        $endpoint = "/$table";
        
        $first = true;
        foreach ($filters as $key => $value) {
            $endpoint .= ($first ? '?' : '&') . "$key=eq.$value";
            $first = false;
        }
        
        return $this->request('DELETE', $endpoint);
    }
    
    /**
     * Simple query method - logs warning and returns empty array
     * Note: Complex SQL queries are not supported via REST API
     * Use select/insert/update/delete methods instead
     */
    public function query($sql) {
        error_log("⚠️ Warning: query() method called with SQL. This is not recommended.");
        error_log("SQL: " . substr($sql, 0, 200));
        return [];
    }
}

// Initialize Supabase client
$supabase = new SupabaseClient($supabaseUrl, $supabaseServiceKey, $supabaseAnonKey);
