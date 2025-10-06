<?php
require __DIR__ . '/config.php';
require __DIR__ . '/middleware.php';

try {
    $userId = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];

    switch($method) {
        case 'GET':
            $studentId = $_GET['student_id'] ?? null;
            $category = $_GET['category'] ?? null;
            
            $filters = [];
            if ($studentId) $filters['student_id'] = (int)$studentId;
            if ($category) $filters['category'] = $category;
            
            $files = $supabase->select('files', '*', $filters, 'created_at.desc');
            echo json_encode($files ?: []);
            break;
            
        case 'POST':
            $action = $_POST['action'] ?? 'upload';
            
            if ($action === 'upload' && isset($_FILES['file'])) {
                $file = $_FILES['file'];
                $studentId = (int)$_POST['student_id'];
                $category = sanitizeInput($_POST['category'] ?? 'other');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                $uploadDir = __DIR__ . '/../uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . basename($file['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $fileData = [
                        'student_id' => $studentId,
                        'uploader_id' => $userId,
                        'file_name' => $file['name'],
                        'file_path' => 'uploads/' . $fileName,
                        'file_size' => $file['size'],
                        'file_type' => $file['type'],
                        'category' => $category,
                        'description' => $description
                    ];
                    
                    $result = $supabase->insert('files', $fileData, false);
                    echo json_encode(['success' => true, 'message' => 'تم رفع الملف بنجاح', 'file' => $fileData]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'فشل رفع الملف']);
                }
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)$data['id'];
            
            $file = $supabase->select('files', '*', ['id' => $id]);
            if ($file) {
                $filePath = __DIR__ . '/../' . $file[0]['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $supabase->delete('files', ['id' => $id]);
                echo json_encode(['success' => true, 'message' => 'تم حذف الملف']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'الطريقة غير مسموح بها']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('APP_ENV') === 'production' ? 'حدث خطأ في الخادم' : $e->getMessage()
    ]);
}

