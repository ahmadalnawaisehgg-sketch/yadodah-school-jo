-- ========================================
-- إصلاح قاعدة البيانات - النسخة النهائية
-- ========================================
-- نسخ هذا الكود في Supabase SQL Editor وتنفيذه
-- ========================================

-- ========================================
-- الخطوة 1: حذف الجداول القديمة إذا كانت موجودة
-- ========================================
DROP TABLE IF EXISTS messages CASCADE;
DROP TABLE IF EXISTS conversation_participants CASCADE;
DROP TABLE IF EXISTS conversations CASCADE;
DROP TABLE IF EXISTS message_templates CASCADE;
DROP TABLE IF EXISTS meeting_requests CASCADE;
DROP TABLE IF EXISTS guidance_attendance CASCADE;

-- ========================================
-- الخطوة 2: إضافة الحقول الناقصة للجداول الموجودة
-- ========================================

-- إضافة حقول الصلاحيات لجدول parent_student_link
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='parent_student_link' AND column_name='can_view_violations') THEN
        ALTER TABLE parent_student_link ADD COLUMN can_view_violations BOOLEAN DEFAULT TRUE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='parent_student_link' AND column_name='can_view_meetings') THEN
        ALTER TABLE parent_student_link ADD COLUMN can_view_meetings BOOLEAN DEFAULT TRUE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='parent_student_link' AND column_name='can_view_progress') THEN
        ALTER TABLE parent_student_link ADD COLUMN can_view_progress BOOLEAN DEFAULT TRUE;
    END IF;
END $$;

-- إضافة حقل user_type لجدول activity_log
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='activity_log' AND column_name='user_type') THEN
        ALTER TABLE activity_log ADD COLUMN user_type VARCHAR(50) DEFAULT 'user';
    END IF;
END $$;

-- إضافة حقول لجدول notifications
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='notifications' AND column_name='user_type') THEN
        ALTER TABLE notifications ADD COLUMN user_type VARCHAR(50) DEFAULT 'user';
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='notifications' AND column_name='action_required') THEN
        ALTER TABLE notifications ADD COLUMN action_required BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- إضافة حقول لجدول parent_meetings
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='parent_meetings' AND column_name='attendance_confirmed') THEN
        ALTER TABLE parent_meetings ADD COLUMN attendance_confirmed BOOLEAN DEFAULT FALSE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='parent_meetings' AND column_name='confirmed_at') THEN
        ALTER TABLE parent_meetings ADD COLUMN confirmed_at TIMESTAMP;
    END IF;
END $$;

-- ========================================
-- الخطوة 3: إنشاء الجداول الجديدة
-- ========================================

-- جدول المحادثات
CREATE TABLE conversations (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255),
    conversation_type VARCHAR(50) DEFAULT 'private',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول المشاركين في المحادثات
CREATE TABLE conversation_participants (
    id SERIAL PRIMARY KEY,
    conversation_id INT REFERENCES conversations(id) ON DELETE CASCADE,
    user_id INT NOT NULL,
    user_type VARCHAR(50) DEFAULT 'user',
    participant_role VARCHAR(50) DEFAULT 'participant',
    is_active BOOLEAN DEFAULT TRUE,
    last_read_at TIMESTAMP,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(conversation_id, user_id, user_type)
);

-- جدول الرسائل
CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    conversation_id INT REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id INT NOT NULL,
    sender_type VARCHAR(50) DEFAULT 'user',
    sender_name VARCHAR(255),
    message TEXT NOT NULL,
    message_type VARCHAR(50) DEFAULT 'text',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول طلبات المواعيد
CREATE TABLE meeting_requests (
    id SERIAL PRIMARY KEY,
    parent_id INT REFERENCES parents(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    requested_date DATE,
    requested_time TIME,
    topic TEXT,
    reason TEXT,
    request_status VARCHAR(50) DEFAULT 'pending',
    response_message TEXT,
    responded_by INT REFERENCES users(id) ON DELETE SET NULL,
    responded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول قوالب الرسائل
CREATE TABLE message_templates (
    id SERIAL PRIMARY KEY,
    category VARCHAR(100),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول حضور جلسات التوجيه
CREATE TABLE guidance_attendance (
    id SERIAL PRIMARY KEY,
    session_id INT REFERENCES guidance_sessions(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    attended BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(session_id, student_id)
);

-- ========================================
-- الخطوة 4: إنشاء الفهارس للأداء
-- ========================================
CREATE INDEX idx_conversations_type ON conversations(conversation_type);
CREATE INDEX idx_conversation_participants_conversation ON conversation_participants(conversation_id);
CREATE INDEX idx_conversation_participants_user ON conversation_participants(user_id, user_type);
CREATE INDEX idx_messages_conversation ON messages(conversation_id);
CREATE INDEX idx_messages_sender ON messages(sender_id, sender_type);
CREATE INDEX idx_messages_read ON messages(is_read);
CREATE INDEX idx_meeting_requests_parent ON meeting_requests(parent_id);
CREATE INDEX idx_meeting_requests_student ON meeting_requests(student_id);
CREATE INDEX idx_meeting_requests_status ON meeting_requests(request_status);
CREATE INDEX idx_guidance_attendance_session ON guidance_attendance(session_id);
CREATE INDEX idx_guidance_attendance_student ON guidance_attendance(student_id);

-- ========================================
-- الخطوة 5: تحديث البيانات الموجودة
-- ========================================
UPDATE parent_student_link 
SET can_view_violations = COALESCE(can_view_violations, TRUE),
    can_view_meetings = COALESCE(can_view_meetings, TRUE),
    can_view_progress = COALESCE(can_view_progress, TRUE);

-- ========================================
-- الخطوة 6: إضافة بيانات تجريبية
-- ========================================
INSERT INTO students (name, national_id, email, guardian, guardian_email, guardian_phone, guardian_relation, grade, section, phone, status) 
VALUES 
('سارة أحمد', '9998887776', 'sara.student@school.com', 'أحمد محمد', 'sara.student@school.com', '0791112222', 'والد', 'الحادي عشر', 'أ', '0793334444', 'active')
ON CONFLICT (national_id) DO UPDATE SET guardian_email = EXCLUDED.guardian_email;

-- ========================================
-- ✅ تم الانتهاء بنجاح!
-- ========================================
SELECT '✅ تم إصلاح قاعدة البيانات بنجاح! جميع الجداول والحقول جاهزة.' as status;
