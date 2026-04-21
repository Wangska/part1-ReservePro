<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database_schema.php';
require_once __DIR__ . '/../config/notifications.php';
require_once __DIR__ . '/mailer2.php';

class Auth {
    
    // Register new user
    public static function register($first_name, $last_name, $email, $password, $role = 'guest', $date_of_birth = null) {
        $errors = [];
        
        // Validation
        if (empty($first_name)) {
            $errors[] = "First name is required";
        }
        
        if (empty($last_name)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }

        // Date of birth (required)
        $dob = null;
        $date_of_birth = is_string($date_of_birth) ? trim($date_of_birth) : '';
        if ($date_of_birth === '') {
            $errors[] = "Date of birth is required";
        } else {
            try {
                $d = new DateTimeImmutable($date_of_birth);
                $dob = $d->format('Y-m-d');
            } catch (Exception $e) {
                $errors[] = "Invalid date of birth";
            }
        }
        
        // Validate role (prevent admin registration from public form)
        $allowed_roles = ['guest', 'host'];
        if (!in_array($role, $allowed_roles)) {
            $role = 'guest'; // Default to guest if invalid role
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Ensure extended schema (roles, verification fields, etc.) exists
        initializeHostTables();

        $conn = getDBConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'errors' => ['Email already exists']];
        }
        $stmt->close();
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Generate verification token
        try {
            $verification_token = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $verification_token = bin2hex(uniqid((string)mt_rand(), true));
        }
        
        // Insert user with role and verification info
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, date_of_birth, email, password, role, email_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
        $stmt->bind_param("sssssss", $first_name, $last_name, $dob, $email, $hashed_password, $role, $verification_token);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();
            $conn->close();

            // Fire-and-forget verification email
            sendVerificationEmail($email, $first_name, $verification_token);

            // Notify admins
            reservepro_notification_notify_admins(
                'user_created',
                'New user created',
                trim($first_name . ' ' . $last_name) . ' (' . $email . ')'
            );
            
            return ['success' => true, 'user_id' => $user_id];
        } else {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }
    }
    
    // Login user
    public static function login($email, $password) {
        $errors = [];
        
        // Validation
        if (empty($email)) {
            $errors[] = "Email is required";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $conn = getDBConnection();
        
        // Get user (including email_verified to enforce verification)
        $stmt = $conn->prepare("SELECT id, password, email_verified, first_name, verification_token FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'errors' => ['Invalid email or password']];
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            $conn->close();
            return ['success' => false, 'errors' => ['Invalid email or password']];
        }

        // Block login if email not verified yet
        if (isset($user['email_verified']) && (int)$user['email_verified'] !== 1) {
            // If there is no verification token (older accounts), generate a new one and send email again
            if (empty($user['verification_token'])) {
                try {
                    $newToken = bin2hex(random_bytes(32));
                } catch (Exception $e) {
                    $newToken = bin2hex(uniqid((string)mt_rand(), true));
                }
                $update = $conn->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
                if ($update) {
                    $update->bind_param("si", $newToken, $user['id']);
                    $update->execute();
                    $update->close();
                    sendVerificationEmail($email, $user['first_name'] ?? '', $newToken);
                }
            } else {
                // Resend existing token for convenience
                sendVerificationEmail($email, $user['first_name'] ?? '', $user['verification_token']);
            }

            $conn->close();
            return [
                'success' => false,
                'errors'  => [
                    'Your email is not verified yet. Please check your inbox (Mailtrap) and click the verification link before signing in.'
                ],
            ];
        }

        $conn->close();

        // Successful login
        $_SESSION['user_id'] = $user['id'];
        return ['success' => true, 'user_id' => $user['id']];
    }
    
    // Logout user
    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
?>
