<?php
session_start();

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $action = $data['action'] ?? '';

        if ($action === 'login') {
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';

            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'اسم المستخدم وكلمة المرور مطلوبان']);
                exit;
            }
            
            $parents = $supabase->select('parents', '*', ['username' => $username]);

            if (!empty($parents) && isset($parents[0])) {
                $parent = $parents[0];

                $_SESSION['parent_id'] = $parent['id'];
                $_SESSION['parent_username'] = $parent['username'];
                $_SESSION['is_parent'] = true;
                $_SESSION['last_activity'] = time();

                $supabase->update('parents', ['last_login' => date('Y-m-d H:i:s')], ['id' => $parent['id']], false);

                echo json_encode([
                    'success' => true,
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'parent' => [
                        'id' => $parent['id'],
                        'username' => $parent['username'],
                        'full_name' => $parent['full_name']
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);
            }

        } elseif ($action === 'logout') {
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);

        } elseif ($action === 'check') {
            if (isset($_SESSION['parent_id']) && isset($_SESSION['is_parent'])) {
                if (time() - $_SESSION['last_activity'] < 3600) {
                    $_SESSION['last_activity'] = time();
                    echo json_encode([
                        'success' => true,
                        'authenticated' => true,
                        'parent_id' => $_SESSION['parent_id']
                    ]);
                } else {
                    session_destroy();
                    echo json_encode(['success' => false, 'authenticated' => false, 'error' => 'انتهت الجلسة']);
                }
            } else {
                echo json_encode(['success' => false, 'authenticated' => false]);
            }
        } elseif ($action === 'students') {
            if (!isset($_SESSION['parent_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'غير مصرح به']);
                exit;
            }

            $links = $supabase->select('parent_student_link', 'student_id', ['parent_id' => $_SESSION['parent_id']]);
            $students = [];
            
            foreach ($links as $link) {
                $student = $supabase->select('students', '*', ['id' => $link['student_id']]);
                if ($student) {
                    $violations = $supabase->select('violations', '*', ['student_id' => $link['student_id']], 'date.desc');
                    $progress = $supabase->select('student_progress', '*', ['student_id' => $link['student_id']], 'created_at.desc');
                    
                    $student[0]['violations'] = $violations ?: [];
                    $student[0]['progress'] = $progress ?: [];
                    $students[] = $student[0];
                }
            }
            
            echo json_encode($students);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('APP_ENV') === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

