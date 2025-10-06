-- ========================================
-- نظام الإرشاد المدرسي الموحد - قاعدة بيانات كاملة
-- يعمل من الصفر بدون أخطاء
-- ========================================

-- ========================================
-- 1. جدول المستخدمين
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    role VARCHAR(50) DEFAULT 'counselor',
    permissions TEXT,
    avatar_url TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    backup_codes TEXT,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password_hash, full_name, role, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin', 'admin@school.com'),
('counselor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'المرشد التربوي', 'counselor', 'counselor@school.com')
ON CONFLICT (username) DO NOTHING;

-- ========================================
-- 2. جدول الصفوف (تاسع - ثاني ثانوي)
-- ========================================
CREATE TABLE IF NOT EXISTS grades (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    level INT,
    academic_year VARCHAR(50),
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO grades (name, level, academic_year, is_active) VALUES
('التاسع', 9, '2024-2025', TRUE),
('العاشر', 10, '2024-2025', TRUE),
('الحادي عشر', 11, '2024-2025', TRUE),
('الثاني عشر', 12, '2024-2025', TRUE)
ON CONFLICT DO NOTHING;

-- ========================================
-- 3. جدول الشعب (أ - ح)
-- ========================================
CREATE TABLE IF NOT EXISTS sections (
    id SERIAL PRIMARY KEY,
    grade_id INT REFERENCES grades(id) ON DELETE CASCADE,
    name VARCHAR(50) NOT NULL,
    capacity INT DEFAULT 30,
    current_count INT DEFAULT 0,
    classroom VARCHAR(100),
    class_teacher_id INT REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO sections (grade_id, name, capacity, classroom) VALUES
-- التاسع
(1, 'أ', 35, 'غرفة 101'), (1, 'ب', 35, 'غرفة 102'), (1, 'ج', 35, 'غرفة 103'),
(1, 'د', 35, 'غرفة 104'), (1, 'ه', 35, 'غرفة 105'), (1, 'و', 35, 'غرفة 106'),
(1, 'ز', 35, 'غرفة 107'), (1, 'ح', 35, 'غرفة 108'),
-- العاشر
(2, 'أ', 35, 'غرفة 201'), (2, 'ب', 35, 'غرفة 202'), (2, 'ج', 35, 'غرفة 203'),
(2, 'د', 35, 'غرفة 204'), (2, 'ه', 35, 'غرفة 205'), (2, 'و', 35, 'غرفة 206'),
(2, 'ز', 35, 'غرفة 207'), (2, 'ح', 35, 'غرفة 208'),
-- الحادي عشر
(3, 'أ', 35, 'غرفة 301'), (3, 'ب', 35, 'غرفة 302'), (3, 'ج', 35, 'غرفة 303'),
(3, 'د', 35, 'غرفة 304'), (3, 'ه', 35, 'غرفة 305'), (3, 'و', 35, 'غرفة 306'),
(3, 'ز', 35, 'غرفة 307'), (3, 'ح', 35, 'غرفة 308'),
-- الثاني عشر
(4, 'أ', 35, 'غرفة 401'), (4, 'ب', 35, 'غرفة 402'), (4, 'ج', 35, 'غرفة 403'),
(4, 'د', 35, 'غرفة 404'), (4, 'ه', 35, 'غرفة 405'), (4, 'و', 35, 'غرفة 406'),
(4, 'ز', 35, 'غرفة 407'), (4, 'ح', 35, 'غرفة 408')
ON CONFLICT DO NOTHING;

-- ========================================
-- 4. جدول الطلاب
-- ========================================
CREATE TABLE IF NOT EXISTS students (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    national_id VARCHAR(20) UNIQUE,
    email VARCHAR(255),
    phone VARCHAR(50),
    birth_date DATE,
    gender VARCHAR(10),
    grade_id INT REFERENCES grades(id) ON DELETE SET NULL,
    section_id INT REFERENCES sections(id) ON DELETE SET NULL,
    grade VARCHAR(255),
    section VARCHAR(50),
    address TEXT,
    photo_url TEXT,
    enrollment_date DATE DEFAULT CURRENT_DATE,
    status VARCHAR(50) DEFAULT 'active',
    guardian VARCHAR(255),
    guardian_email VARCHAR(255),
    guardian_phone VARCHAR(50),
    guardian_relation VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 5. جدول أولياء الأمور
-- ========================================
CREATE TABLE IF NOT EXISTS parents (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    national_id VARCHAR(20),
    relation VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    backup_codes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS parent_student_link (
    id SERIAL PRIMARY KEY,
    parent_id INT REFERENCES parents(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    relation VARCHAR(50),
    is_primary BOOLEAN DEFAULT TRUE,
    can_view_violations BOOLEAN DEFAULT TRUE,
    can_view_meetings BOOLEAN DEFAULT TRUE,
    can_view_progress BOOLEAN DEFAULT TRUE,
    can_view_attendance BOOLEAN DEFAULT TRUE,
    can_view_grades BOOLEAN DEFAULT TRUE,
    can_chat_teachers BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(parent_id, student_id)
);

-- ========================================
-- 6. جدول المخالفات
-- ========================================
CREATE TABLE IF NOT EXISTS violations (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    student_name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    type VARCHAR(255) NOT NULL,
    severity VARCHAR(50) DEFAULT 'medium',
    description TEXT NOT NULL,
    action_taken TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    reported_by INT REFERENCES users(id) ON DELETE SET NULL,
    reporter_email VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 7. جدول المعلمين
-- ========================================
CREATE TABLE IF NOT EXISTS teachers (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    subject VARCHAR(255),
    specialization VARCHAR(255),
    hire_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 8. جدول الحضور والغياب (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS attendance (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    student_name VARCHAR(255) NOT NULL,
    grade_id INT REFERENCES grades(id) ON DELETE SET NULL,
    section_id INT REFERENCES sections(id) ON DELETE SET NULL,
    date DATE NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'present',
    notes TEXT,
    recorded_by INT REFERENCES users(id) ON DELETE SET NULL,
    parent_notified BOOLEAN DEFAULT FALSE,
    notification_sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, date)
);

-- ========================================
-- 9. جدول الدرجات الأكاديمية (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS academic_grades (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    student_name VARCHAR(255) NOT NULL,
    grade_id INT REFERENCES grades(id) ON DELETE SET NULL,
    section_id INT REFERENCES sections(id) ON DELETE SET NULL,
    academic_year VARCHAR(50) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    exam_type VARCHAR(100),
    total_marks DECIMAL(5,2),
    obtained_marks DECIMAL(5,2),
    percentage DECIMAL(5,2),
    grade VARCHAR(10),
    rank_in_class INT,
    teacher_id INT REFERENCES teachers(id) ON DELETE SET NULL,
    notes TEXT,
    recorded_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 10. جدول التقويم المدرسي (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS school_calendar (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    start_time TIME,
    end_time TIME,
    location VARCHAR(255),
    target_grades TEXT,
    target_sections TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    color VARCHAR(50),
    reminder_enabled BOOLEAN DEFAULT TRUE,
    reminder_days INT DEFAULT 1,
    created_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 11. جدول الشكاوى والاقتراحات (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS complaints_suggestions (
    id SERIAL PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    category VARCHAR(100),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(50) DEFAULT 'medium',
    status VARCHAR(50) DEFAULT 'pending',
    submitted_by_type VARCHAR(50) NOT NULL,
    submitted_by_id INT,
    submitted_by_name VARCHAR(255),
    submitted_by_email VARCHAR(255),
    submitted_by_phone VARCHAR(50),
    is_anonymous BOOLEAN DEFAULT FALSE,
    assigned_to INT REFERENCES users(id) ON DELETE SET NULL,
    response TEXT,
    response_date TIMESTAMP,
    resolved_by INT REFERENCES users(id) ON DELETE SET NULL,
    resolved_at TIMESTAMP,
    attachments TEXT,
    satisfaction_rating INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 12. نظام الشات (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS conversations (
    id SERIAL PRIMARY KEY,
    teacher_id INT REFERENCES teachers(id) ON DELETE CASCADE,
    parent_id INT REFERENCES parents(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    subject VARCHAR(255),
    status VARCHAR(50) DEFAULT 'active',
    last_message_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(teacher_id, parent_id, student_id)
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id SERIAL PRIMARY KEY,
    conversation_id INT REFERENCES conversations(id) ON DELETE CASCADE,
    sender_type VARCHAR(50) NOT NULL,
    sender_id INT NOT NULL,
    sender_name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    attachment_url TEXT,
    attachment_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 13. إشعارات الطوارئ (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS emergency_notifications (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority VARCHAR(50) DEFAULT 'high',
    target_type VARCHAR(100) NOT NULL,
    target_grades TEXT,
    target_sections TEXT,
    notification_methods TEXT,
    email_sent BOOLEAN DEFAULT FALSE,
    sms_sent BOOLEAN DEFAULT FALSE,
    recipients_count INT DEFAULT 0,
    sent_by INT REFERENCES users(id) ON DELETE SET NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 14. نظام متعدد اللغات (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS translations (
    id SERIAL PRIMARY KEY,
    key VARCHAR(255) NOT NULL,
    language VARCHAR(10) NOT NULL,
    value TEXT NOT NULL,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(key, language)
);

INSERT INTO translations (key, language, value, category) VALUES
('welcome_message', 'ar', 'مرحباً بكم في نظام الإرشاد المدرسي', 'general'),
('welcome_message', 'en', 'Welcome to School Guidance System', 'general')
ON CONFLICT (key, language) DO NOTHING;

-- ========================================
-- 15. تفضيلات المستخدمين (جديد)
-- ========================================
CREATE TABLE IF NOT EXISTS user_preferences (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    theme VARCHAR(50) DEFAULT 'light',
    language VARCHAR(10) DEFAULT 'ar',
    notification_preferences TEXT,
    dashboard_layout TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

CREATE TABLE IF NOT EXISTS parent_preferences (
    id SERIAL PRIMARY KEY,
    parent_id INT REFERENCES parents(id) ON DELETE CASCADE,
    theme VARCHAR(50) DEFAULT 'light',
    language VARCHAR(10) DEFAULT 'ar',
    notification_preferences TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(parent_id)
);

-- ========================================
-- 16. جدول الإشعارات الداخلية
-- ========================================
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link TEXT,
    icon VARCHAR(100),
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    priority VARCHAR(50) DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 17. جدول إشعارات البريد الإلكتروني
-- ========================================
CREATE TABLE IF NOT EXISTS email_notifications (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE SET NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    notification_type VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    sent_at TIMESTAMP,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 18. سجل النشاطات
-- ========================================
CREATE TABLE IF NOT EXISTS activity_log (
    id SERIAL PRIMARY KEY,
    user_id INT,
    user_type VARCHAR(50) DEFAULT 'staff',
    action_type VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    description TEXT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    changes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- Indexes للأداء
-- ========================================
CREATE INDEX IF NOT EXISTS idx_students_grade_id ON students(grade_id);
CREATE INDEX IF NOT EXISTS idx_students_section_id ON students(section_id);
CREATE INDEX IF NOT EXISTS idx_students_status ON students(status);
CREATE INDEX IF NOT EXISTS idx_violations_student_id ON violations(student_id);
CREATE INDEX IF NOT EXISTS idx_violations_date ON violations(date);
CREATE INDEX IF NOT EXISTS idx_attendance_student ON attendance(student_id);
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);
CREATE INDEX IF NOT EXISTS idx_grades_student ON academic_grades(student_id);
CREATE INDEX IF NOT EXISTS idx_calendar_start_date ON school_calendar(start_date);
CREATE INDEX IF NOT EXISTS idx_chat_messages_conversation ON chat_messages(conversation_id);

-- ========================================
-- 19. جدول جلسات التوجيه الجمعي
-- ========================================
CREATE TABLE IF NOT EXISTS guidance_sessions (
    id SERIAL PRIMARY KEY,
    topic VARCHAR(255) NOT NULL,
    date DATE,
    grade VARCHAR(255),
    section VARCHAR(50),
    duration INT,
    location VARCHAR(255),
    presenter_id INT REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT,
    attendees_count INT DEFAULT 0,
    materials TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_guidance_date ON guidance_sessions(date);

-- ========================================
-- 20. جدول الاجتماعات
-- ========================================
CREATE TABLE IF NOT EXISTS meetings (
    id SERIAL PRIMARY KEY,
    meeting_number VARCHAR(100),
    title VARCHAR(255),
    date DATE,
    start_time TIME,
    end_time TIME,
    location VARCHAR(255),
    attendees_count INT DEFAULT 0,
    topics TEXT,
    decisions TEXT,
    organizer_id INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_meetings_date ON meetings(date);

-- ========================================
-- 21. جدول مقابلات أولياء الأمور
-- ========================================
CREATE TABLE IF NOT EXISTS parent_meetings (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    student_name VARCHAR(255) NOT NULL,
    parent_name VARCHAR(255),
    parent_email VARCHAR(255),
    parent_phone VARCHAR(50),
    date DATE,
    time TIME,
    topic TEXT,
    notes TEXT,
    status VARCHAR(50) DEFAULT 'scheduled',
    counselor_id INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_parent_meetings_date ON parent_meetings(date);
CREATE INDEX IF NOT EXISTS idx_parent_meetings_status ON parent_meetings(status);
CREATE INDEX IF NOT EXISTS idx_parent_meetings_student ON parent_meetings(student_id);

-- ========================================
-- Triggers
-- ========================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_students_updated_at BEFORE UPDATE ON students
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ========================================
-- بيانات تجريبية
-- ========================================
INSERT INTO teachers (name, email, subject, phone) VALUES
('أحمد محمد', 'ahmad.teacher@school.com', 'رياضيات', '0791234567'),
('سارة علي', 'sara.teacher@school.com', 'لغة عربية', '0797654321')
ON CONFLICT (email) DO NOTHING;

INSERT INTO school_calendar (title, description, event_type, start_date, is_public, color) VALUES
('بداية الفصل الدراسي', 'انطلاق العام الدراسي الجديد', 'academic', '2024-09-01', TRUE, '#10b981'),
('امتحانات نصف الفصل', 'امتحانات منتصف الفصل', 'exam', '2024-10-15', TRUE, '#f59e0b')
ON CONFLICT DO NOTHING;

-- ========================================
-- ✅ تم إنشاء قاعدة البيانات الكاملة بنجاح!
-- ========================================
SELECT '✅ تم إنشاء قاعدة البيانات بنجاح! جميع الجداول جاهزة.' AS status;
