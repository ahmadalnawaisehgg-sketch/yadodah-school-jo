<?php

function requireAuth() {
    if (!isset($GLOBALS['config_loaded'])) {
        require_once __DIR__ . '/config.php';
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Debug: log session info
    error_log("🔍 Session ID: " . session_id());
    error_log("🔍 Session data: " . json_encode($_SESSION));
    error_log("🔍 Cookies: " . json_encode($_COOKIE));
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        error_log("❌ Auth failed: user_id or last_activity not set");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'غير مصرح به', 'require_login' => true]);
        exit;
    }
    
    $timeDiff = time() - $_SESSION['last_activity'];
    if ($timeDiff > 7200) {
        error_log("❌ Session expired: $timeDiff seconds old");
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'انتهت الجلسة', 'require_login' => true]);
        exit;
    }
    
    $_SESSION['last_activity'] = time();
    return $_SESSION['user_id'];
}

function requireRole($allowedRoles) {
    if (!isset($GLOBALS['config_loaded'])) {
        require_once __DIR__ . '/config.php';
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'غير مصرح به']);
        exit;
    }
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية للوصول']);
        exit;
    }
    
    return $_SESSION['user_id'];
}

function hasPermission($permission) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $permissions = [
        'admin' => ['all'],
        'counselor' => ['students', 'violations', 'guidance', 'parents', 'progress', 'messages'],
        'teacher' => ['students_view', 'violations_view', 'messages']
    ];
    
    $userPerms = $permissions[$_SESSION['role']] ?? [];
    
    return in_array('all', $userPerms) || in_array($permission, $userPerms);
}

function logActivity($actionType, $tableName = null, $recordId = null, $description = null) {
    global $supabase;
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    $activityData = [
        'user_id' => $_SESSION['user_id'],
        'action_type' => $actionType,
        'table_name' => $tableName,
        'record_id' => $recordId,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];
    
    try {
        $supabase->insert('activity_log', $activityData, false);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = $rule['message'] ?? "الحقل {$field} مطلوب";
            continue;
        }
        
        if (!empty($value) && isset($rule['type'])) {
            switch ($rule['type']) {
                case 'string':
                    if (!is_string($value)) {
                        $errors[$field] = "الحقل {$field} يجب أن يكون نص";
                    }
                    break;
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$field] = "الحقل {$field} يجب أن يكون رقم";
                    }
                    break;
                case 'int':
                    if (!is_numeric($value) || (int)$value != $value) {
                        $errors[$field] = "الحقل {$field} يجب أن يكون رقم صحيح";
                    }
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = "الحقل {$field} يجب أن يكون بريد إلكتروني صحيح";
                    }
                    break;
                case 'date':
                    if (!strtotime($value)) {
                        $errors[$field] = "الحقل {$field} يجب أن يكون تاريخ صحيح";
                    }
                    break;
                case 'phone':
                    if (!preg_match('/^[0-9+\-\s()]+$/', $value)) {
                        $errors[$field] = "الحقل {$field} يجب أن يكون رقم هاتف صحيح";
                    }
                    break;
            }
        }
        
        if (!empty($value) && isset($rule['maxLength']) && strlen($value) > $rule['maxLength']) {
            $errors[$field] = "الحقل {$field} يجب أن لا يتجاوز {$rule['maxLength']} حرف";
        }
        
        if (!empty($value) && isset($rule['minLength']) && strlen($value) < $rule['minLength']) {
            $errors[$field] = "الحقل {$field} يجب أن لا يقل عن {$rule['minLength']} حرف";
        }
    }
    
    return count($errors) > 0 ? $errors : null;
}

function sanitizeInput($value) {
    if (is_string($value)) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
    return $value;
}

function sanitizeArray($data) {
    $sanitized = [];
    foreach ($data as $key => $value) {
        $sanitized[$key] = is_array($value) ? sanitizeArray($value) : sanitizeInput($value);
    }
    return $sanitized;
}

function rateLimit($key, $limit = 100, $window = 60) {
    $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($key) . '.txt';
    
    $current = file_exists($cacheFile) ? (int)file_get_contents($cacheFile) : 0;
    $lastCheck = file_exists($cacheFile) ? filemtime($cacheFile) : 0;
    
    if (time() - $lastCheck > $window) {
        $current = 0;
    }
    
    if ($current >= $limit) {
        http_response_code(429);
        echo json_encode(['error' => 'تم تجاوز عدد المحاولات المسموح بها. يرجى المحاولة لاحقاً']);
        exit;
    }
    
    file_put_contents($cacheFile, $current + 1);
}
