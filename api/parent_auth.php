<?php
/**
 * نظام مصادقة أولياء الأمور المتطور
 * يدعم تسجيل الدخول بالإيميل فقط (Smart Login)
 */

session_start();

require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'بيانات الطلب فارغة']);
            exit;
        }
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'صيغة البيانات غير صحيحة']);
            exit;
        }

        $action = $data['action'] ?? '';

        // تسجيل دخول ولي الأمر
        if ($action === 'parent_login') {
            $email = trim($data['email'] ?? '');

            if (empty($email)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني مطلوب']);
                exit;
            }

            error_log("🔐 Parent login attempt for email: $email");

            // التحقق من وجود ولي الأمر في قاعدة البيانات
            $parents = $supabase->select('parents', '*', ['email' => $email]);
            error_log("📊 Parents found: " . count($parents));

            if (!empty($parents) && isset($parents[0])) {
                $parent = $parents[0];
                error_log("✅ Parent found in database: " . $parent['full_name']);

                // التحقق من أن الحساب نشط
                if (!isset($parent['is_active']) || !$parent['is_active']) {
                    error_log("❌ Parent account is inactive");
                    http_response_code(403);
                    echo json_encode([
                        'success' => false, 
                        'error' => 'حسابك معطل. يرجى الاتصال بالمدرسة لتفعيل الحساب.'
                    ]);
                    exit;
                }

                // جلب معلومات الطلاب المرتبطين بولي الأمر
                $links = $supabase->select('parent_student_link', 'student_id,relation,can_view_violations,can_view_meetings,can_view_progress', ['parent_id' => $parent['id']]);
                error_log("📊 Student links found: " . count($links));
                
                if (empty($links)) {
                    error_log("❌ No students linked to this parent");
                    http_response_code(400);
                    echo json_encode([
                        'success' => false, 
                        'error' => 'لا يوجد طلاب مرتبطين بهذا الحساب. يرجى التواصل مع المدرسة لربط حسابك بأبنائك.'
                    ]);
                    exit;
                }
                
                $studentLinks = [];
                foreach ($links as $link) {
                    $students = $supabase->select('students', '*', ['id' => $link['student_id']]);
                    if (!empty($students)) {
                        $student = $students[0];
                        $student['relation'] = $link['relation'];
                        $student['can_view_violations'] = $link['can_view_violations'];
                        $student['can_view_meetings'] = $link['can_view_meetings'];
                        $student['can_view_progress'] = $link['can_view_progress'];
                        $studentLinks[] = $student;
                    }
                }

                // إنشاء جلسة لولي الأمر
                $_SESSION['parent_id'] = $parent['id'];
                $_SESSION['parent_email'] = $parent['email'];
                $_SESSION['parent_name'] = $parent['full_name'];
                $_SESSION['user_type'] = 'parent';
                $_SESSION['last_activity'] = time();

                // تحديث آخر دخول
                $supabase->update('parents', ['last_login' => date('Y-m-d H:i:s')], ['id' => $parent['id']], false);

                // تسجيل النشاط
                try {
                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $supabase->insert('activity_log', [
                        'user_id' => $parent['id'],
                        'user_type' => 'parent',
                        'action_type' => 'login',
                        'description' => 'تسجيل دخول ولي الأمر: ' . $parent['full_name'],
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent
                    ], false);
                } catch (Exception $logError) {
                    error_log("Failed to log activity: " . $logError->getMessage());
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'تم تسجيل الدخول بنجاح',
                    'parent' => [
                        'id' => $parent['id'],
                        'full_name' => $parent['full_name'],
                        'email' => $parent['email'],
                        'phone' => $parent['phone'] ?? '',
                        'students_count' => count($studentLinks)
                    ],
                    'students' => $studentLinks
                ]);

            } else {
                // إذا لم يوجد في جدول parents، نبحث في guardian_email أو email في جدول students
                error_log("🔍 Parent not found in parents table, searching in students...");
                $students = $supabase->select('students', '*', ['guardian_email' => $email]);
                error_log("📊 Students found by guardian_email: " . count($students));
                
                // إذا لم نجد في guardian_email، نبحث في email
                if (empty($students)) {
                    $students = $supabase->select('students', '*', ['email' => $email]);
                    error_log("📊 Students found by email: " . count($students));
                }

                if (!empty($students)) {
                    // إنشاء حساب ولي أمر جديد تلقائياً
                    $firstStudent = $students[0];
                    
                    // استخدام معلومات ولي الأمر إن وجدت، وإلا استخدام اسم الطالب
                    $guardianName = $firstStudent['guardian'] ?? 'ولي أمر ' . $firstStudent['name'];
                    $guardianPhone = $firstStudent['guardian_phone'] ?? $firstStudent['phone'] ?? '';
                    $guardianRelation = $firstStudent['guardian_relation'] ?? 'ولي أمر';
                    
                    $newParent = $supabase->insert('parents', [
                        'full_name' => $guardianName,
                        'email' => $email,
                        'phone' => $guardianPhone,
                        'relation' => $guardianRelation,
                        'is_active' => true
                    ]);

                    if (!empty($newParent)) {
                        $parentId = $newParent[0]['id'];

                        // ربط جميع الطلاب بولي الأمر الجديد
                        foreach ($students as $student) {
                            $supabase->insert('parent_student_link', [
                                'parent_id' => $parentId,
                                'student_id' => $student['id'],
                                'relation' => $student['guardian_relation'] ?? $guardianRelation,
                                'is_primary' => true,
                                'can_view_violations' => true,
                                'can_view_meetings' => true,
                                'can_view_progress' => true
                            ], false);
                        }

                        // إنشاء جلسة
                        $_SESSION['parent_id'] = $parentId;
                        $_SESSION['parent_email'] = $email;
                        $_SESSION['parent_name'] = $guardianName;
                        $_SESSION['user_type'] = 'parent';
                        $_SESSION['last_activity'] = time();

                        // تسجيل النشاط
                        try {
                            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                            $supabase->insert('activity_log', [
                                'user_id' => $parentId,
                                'user_type' => 'parent',
                                'action_type' => 'first_login',
                                'description' => 'أول تسجيل دخول لولي الأمر: ' . $guardianName,
                                'ip_address' => $ipAddress,
                                'user_agent' => $userAgent
                            ], false);
                        } catch (Exception $logError) {
                            error_log("Failed to log activity: " . $logError->getMessage());
                        }

                        echo json_encode([
                            'success' => true,
                            'message' => 'مرحباً بك! تم إنشاء حسابك بنجاح',
                            'first_login' => true,
                            'parent' => [
                                'id' => $parentId,
                                'full_name' => $guardianName,
                                'email' => $email,
                                'phone' => $guardianPhone,
                                'students_count' => count($students)
                            ],
                            'students' => $students
                        ]);
                    }
                } else {
                    error_log("❌ Email not found in any table: $email");
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'عذراً، البريد الإلكتروني "' . $email . '" غير مسجل في النظام. يرجى التأكد من البريد الإلكتروني أو التواصل مع المدرسة.'
                    ]);
                }
            }

        } elseif ($action === 'parent_logout') {
            if (isset($_SESSION['parent_id'])) {
                try {
                    $supabase->insert('activity_log', [
                        'user_id' => $_SESSION['parent_id'],
                        'user_type' => 'parent',
                        'action_type' => 'logout',
                        'description' => 'تسجيل خروج ولي الأمر'
                    ], false);
                } catch (Exception $logError) {
                    error_log("Failed to log activity: " . $logError->getMessage());
                }
            }
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);

        } elseif ($action === 'check_parent_session') {
            if (isset($_SESSION['parent_id']) && isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] < 3600) {
                    $_SESSION['last_activity'] = time();
                    
                    // جلب معلومات الطلاب
                    $links = $supabase->select('parent_student_link', 'student_id,relation', ['parent_id' => $_SESSION['parent_id']]);
                    
                    $studentLinks = [];
                    foreach ($links as $link) {
                        $students = $supabase->select('students', '*', ['id' => $link['student_id']]);
                        if (!empty($students)) {
                            $student = $students[0];
                            $student['relation'] = $link['relation'];
                            $studentLinks[] = $student;
                        }
                    }

                    echo json_encode([
                        'success' => true,
                        'authenticated' => true,
                        'parent' => [
                            'id' => $_SESSION['parent_id'],
                            'full_name' => $_SESSION['parent_name'],
                            'email' => $_SESSION['parent_email'],
                            'students_count' => count($studentLinks)
                        ],
                        'students' => $studentLinks
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
    error_log("Parent Auth error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في الخادم'
    ]);
}
