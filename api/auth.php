<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            error_log("❌ Empty request body");
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'بيانات الطلب فارغة']);
            exit;
        }
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("❌ JSON decode error: " . json_last_error_msg());
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'صيغة البيانات غير صحيحة']);
            exit;
        }

        $action = $data['action'] ?? '';

        if ($action === 'login') {
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';

            error_log("🔐 Login attempt for user: $username");

            if (empty($username) || empty($password)) {
                error_log("❌ Login failed: Empty username or password");
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'اسم المستخدم وكلمة المرور مطلوبان']);
                exit;
            }
            
            try {
                $users = $supabase->select('users', '*', ['username' => $username]);
                error_log("📊 Database query result: " . (empty($users) ? "No user found" : "User found"));

                if (!empty($users) && isset($users[0])) {
                    $user = $users[0];
                    
                    if (!isset($user['is_active']) || !$user['is_active']) {
                        error_log("⚠️ Login failed: User account is inactive");
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'حسابك معطل. يرجى الاتصال بالمسؤول']);
                        exit;
                    }
                    
                    $passwordHash = $user['password_hash'] ?? '';
                    if (empty($passwordHash)) {
                        error_log("❌ Login failed: No password hash found for user");
                        http_response_code(401);
                        echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);
                        exit;
                    }
                    
                    if (!password_verify($password, $passwordHash)) {
                        error_log("❌ Login failed: Password verification failed");
                        http_response_code(401);
                        echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);
                        exit;
                    }

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'] ?? '';
                    $_SESSION['last_activity'] = time();
                    
                    error_log("✅ Login successful for user: $username");
                    
                    $supabase->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']], false);
                    
                    try {
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $supabase->insert('activity_log', [
                            'user_id' => $user['id'],
                            'action_type' => 'login',
                            'description' => 'تسجيل دخول المستخدم',
                            'ip_address' => $ipAddress,
                            'user_agent' => $userAgent
                        ], false);
                    } catch (Exception $logError) {
                        error_log("Failed to log activity: " . $logError->getMessage());
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'تم تسجيل الدخول بنجاح',
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'] ?? $user['username'],
                            'role' => $user['role'],
                            'email' => $user['email'] ?? ''
                        ]
                    ]);
                } else {
                    error_log("❌ Login failed: User not found - $username");
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);
                }
            } catch (Exception $dbError) {
                error_log("Database query error: " . $dbError->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'خطأ في الاتصال بقاعدة البيانات. تحقق من إعدادات Supabase'
                ]);
                exit;
            }

        } elseif ($action === 'logout') {
            if (isset($_SESSION['user_id'])) {
                try {
                    $supabase->insert('activity_log', [
                        'user_id' => $_SESSION['user_id'],
                        'action_type' => 'logout',
                        'description' => 'تسجيل خروج المستخدم'
                    ], false);
                } catch (Exception $logError) {
                    error_log("Failed to log activity: " . $logError->getMessage());
                }
            }
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);

        } elseif ($action === 'check') {
            if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] < 3600) {
                    $_SESSION['last_activity'] = time();
                    echo json_encode([
                        'success' => true,
                        'authenticated' => true,
                        'user' => [
                            'id' => $_SESSION['user_id'],
                            'username' => $_SESSION['username'],
                            'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'],
                            'role' => $_SESSION['role'],
                            'email' => $_SESSION['email'] ?? ''
                        ]
                    ]);
                } else {
                    session_destroy();
                    echo json_encode(['success' => true, 'authenticated' => false, 'error' => 'انتهت الجلسة']);
                }
            } else {
                echo json_encode(['success' => true, 'authenticated' => false]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'الطريقة غير مسموح بها']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Auth error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $appEnv === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

