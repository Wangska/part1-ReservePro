-- ============================================
-- ServePro - Create Admin Account Only
-- Use this if role column already exists
-- ============================================

USE servepro_auth;

-- Create or update admin account
-- Email: admin@servepro.com
-- Password: admin123
INSERT INTO users (first_name, last_name, email, password, role) 
VALUES (
    'Admin',
    'ServePro',
    'admin@servepro.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
)
ON DUPLICATE KEY UPDATE role = 'admin';

-- Verify admin account was created
SELECT 
    'Admin account ready!' as status,
    id,
    first_name,
    last_name, 
    email, 
    role, 
    created_at 
FROM users 
WHERE email = 'admin@servepro.com';

-- ============================================
-- LOGIN CREDENTIALS:
-- Email: admin@servepro.com
-- Password: admin123
-- 
-- Access Admin Panel:
-- http://localhost/part1/admin/dashboard.php
-- ============================================
