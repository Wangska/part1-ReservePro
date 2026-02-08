-- ServePro Admin Account Setup
-- Run this in phpMyAdmin to create admin account

USE servepro_auth;

-- Step 1: Add role column (skip if already exists - you'll see error, that's OK)
-- If you see error "Duplicate column name 'role'" - just ignore it and continue
ALTER TABLE users 
ADD COLUMN role ENUM('guest', 'host', 'admin') DEFAULT 'guest' AFTER password;

-- Step 2: Create admin user
-- Email: admin@servepro.com
-- Password: admin123
INSERT INTO users (first_name, last_name, email, password, role) 
VALUES (
    'Admin',
    'ServePro',
    'admin@servepro.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- Password: admin123
    'admin'
)
ON DUPLICATE KEY UPDATE role = 'admin';

-- Step 3: Verify setup
SELECT id, first_name, last_name, email, role, created_at 
FROM users 
WHERE role = 'admin';

-- Show admin login info
SELECT 
    'Admin account created successfully!' as status,
    'admin@servepro.com' as email,
    'admin123' as password,
    'Change password after first login!' as note;
