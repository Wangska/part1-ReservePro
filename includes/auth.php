<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

class Auth {
    
    // Register new user
    public static function register($first_name, $last_name, $email, $password, $role = 'guest') {
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
        
        // Validate role (prevent admin registration from public form)
        $allowed_roles = ['guest', 'host'];
        if (!in_array($role, $allowed_roles)) {
            $role = 'guest'; // Default to guest if invalid role
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
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
        
        // Insert user with role
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $first_name, $last_name, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();
            $conn->close();
            
            // Auto login after registration
            $_SESSION['user_id'] = $user_id;
            
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
        
        // Get user
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
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
        $conn->close();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            return ['success' => true, 'user_id' => $user['id']];
        } else {
            return ['success' => false, 'errors' => ['Invalid email or password']];
        }
    }
    
    // Logout user
    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
?>
