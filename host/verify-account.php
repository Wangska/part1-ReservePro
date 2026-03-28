<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

if (!$user || $user['role'] !== 'host') {
    header('Location: ../home.php');
    exit();
}

// If already verified, go to dashboard
if (!empty($user['host_verified'])) {
    header('Location: dashboard.php');
    exit();
}

// Check if host has already submitted (pending or rejected)
$conn = getDBConnection();
initializeHostTables(); // ensures verification_status column exists
$stmt = $conn->prepare("SELECT id, verification_status, created_at FROM host_documents WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$verification_pending = ($existing && isset($existing['verification_status']) && $existing['verification_status'] === 'pending');
$verification_rejected = ($existing && isset($existing['verification_status']) && $existing['verification_status'] === 'rejected');
$submitted_at = $existing && !empty($existing['created_at']) ? $existing['created_at'] : null;
$just_submitted = isset($_GET['submitted']) && $_GET['submitted'] == '1';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gov_id_type = trim($_POST['gov_id_type'] ?? '');
    $gov_id_number = trim($_POST['gov_id_number'] ?? '');
    $ownership_proof_type = trim($_POST['ownership_proof_type'] ?? '');
    $ownership_reference = trim($_POST['ownership_reference'] ?? '');
    $business_registration = trim($_POST['business_registration'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $tourism_license = trim($_POST['tourism_license'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account_name = trim($_POST['bank_account_name'] ?? '');
    $bank_account_number = trim($_POST['bank_account_number'] ?? '');

    if ($gov_id_type === '') $errors[] = 'Government ID type is required';
    if ($ownership_proof_type === '') $errors[] = 'Property ownership/permission type is required';
    if ($bank_name === '') $errors[] = 'Bank name is required';
    if ($bank_account_name === '') $errors[] = 'Bank account name is required';
    if ($bank_account_number === '') $errors[] = 'Bank account number is required';

    // Number-only validation: digits, spaces and hyphens allowed for formatting
    function is_valid_number_field($value) {
        if ($value === '') return true;
        return preg_match('/^[\d\s\-]+$/', $value) && preg_match('/\d/', $value);
    }
    if (!is_valid_number_field($gov_id_number)) $errors[] = 'ID Number must contain only numbers (spaces or hyphens allowed).';
    if (!is_valid_number_field($business_registration)) $errors[] = 'Business registration number must contain only numbers (spaces or hyphens allowed).';
    if (!is_valid_number_field($tax_id)) $errors[] = 'Tax Identification Number (TIN) must contain only numbers (spaces or hyphens allowed).';
    if (!is_valid_number_field($tourism_license)) $errors[] = 'Local Tourism License must contain only numbers (spaces or hyphens allowed).';
    if ($bank_account_number !== '' && !is_valid_number_field($bank_account_number)) $errors[] = 'Account number must contain only numbers (spaces or hyphens allowed).';

    if (empty($errors)) {
        $conn = getDBConnection();
        initializeHostTables();

        $stmt = $conn->prepare("SELECT id FROM host_documents WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $status = 'pending';
        if ($row) {
            $stmt = $conn->prepare("
                UPDATE host_documents SET
                  gov_id_type = ?, gov_id_number = ?, ownership_proof_type = ?, ownership_reference = ?,
                  business_registration = ?, tax_id = ?, tourism_license = ?,
                  bank_name = ?, bank_account_name = ?, bank_account_number = ?,
                  verification_status = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("ssssssssssi",
                $gov_id_type, $gov_id_number, $ownership_proof_type, $ownership_reference,
                $business_registration, $tax_id, $tourism_license,
                $bank_name, $bank_account_name, $bank_account_number,
                $status, $user['id']
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO host_documents
                (user_id, gov_id_type, gov_id_number, ownership_proof_type, ownership_reference, business_registration, tax_id, tourism_license, bank_name, bank_account_name, bank_account_number, verification_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssssssssss",
                $user['id'],
                $gov_id_type, $gov_id_number, $ownership_proof_type, $ownership_reference,
                $business_registration, $tax_id, $tourism_license,
                $bank_name, $bank_account_name, $bank_account_number,
                $status
            );
            $stmt->execute();
            $stmt->close();
        }

        // Set user's host_verification_status to 'under review' in users table
        $stmt = $conn->prepare("UPDATE users SET host_verification_status = 'under review' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $stmt->close();
        }

        $conn->close();
        header('Location: verify-account.php?submitted=1');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Host Verification - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=14.0">
    <style>
        .field-error { display: block; color: #ef4444; font-size: 13px; margin-top: 6px; }
        .field-error:empty { display: none; }
        input.invalid { border-color: #ef4444 !important; box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body>
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Host (verification required)</div>
                    </div>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">
            <div class="host-header">
                <div>
                    <h1>Complete Host Verification ✅</h1>
                    <p class="subtitle">Submit your details so we can verify your identity and property.</p>
                </div>
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <h4>Please fix the following issues:</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($verification_pending): ?>
                <div class="alert" style="background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); color: #fde047; padding: 24px; border-radius: 12px;">
                    <h3 style="margin-bottom: 8px;">⏳ Verification under review</h3>
                    <p style="margin: 0 0 12px 0;">Your host verification is under review. Our team will check your details and get back to you soon. You will be able to access your dashboard and list properties once approved.</p>
                    <?php if ($submitted_at): ?>
                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">Submitted on <?php echo date('F j, Y \a\t g:i A', strtotime($submitted_at)); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($verification_rejected && !$verification_pending): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <p style="margin: 0;">Your previous verification was rejected. Please update your details below and resubmit.</p>
                </div>
            <?php endif; ?>

            <?php if (!$verification_pending): ?>
            <form method="POST" action="verify-account.php" class="property-form">
                <div class="form-section">
                    <h2 class="section-title">1️⃣ Personal Identification</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gov_id_type">Government-issued ID *</label>
                            <select id="gov_id_type" name="gov_id_type" required>
                                <option value="">Select ID type</option>
                                <option value="Passport">Passport</option>
                                <option value="National ID">National ID</option>
                                <option value="Driver's License">Driver's License</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gov_id_number">ID Number (optional)</label>
                            <input type="text" id="gov_id_number" name="gov_id_number" placeholder="ID reference number" data-number-field>
                            <span class="field-error" id="gov_id_number_error" role="alert"></span>
                        </div>
                    </div>
                    <p class="helper-text">You can later extend this to actual ID image uploads; for now we store the ID type and reference.</p>
                </div>

                <div class="form-section">
                    <h2 class="section-title">2️⃣ Property Ownership / Permission</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ownership_proof_type">Ownership / Permission Proof *</label>
                            <select id="ownership_proof_type" name="ownership_proof_type" required>
                                <option value="">Select proof type</option>
                                <option value="Land title / Ownership certificate">Land title / Ownership certificate</option>
                                <option value="Lease agreement">Lease agreement</option>
                                <option value="Written landlord permission">Written landlord permission</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ownership_reference">Reference / Notes</label>
                            <input type="text" id="ownership_reference" name="ownership_reference" placeholder="Document reference or notes">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">3️⃣ Business Documents (optional)</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="business_registration">Business Registration Certificate</label>
                            <input type="text" id="business_registration" name="business_registration" placeholder="Business registration number" data-number-field>
                            <span class="field-error" id="business_registration_error" role="alert"></span>
                        </div>
                        <div class="form-group">
                            <label for="tax_id">Tax Identification Number (TIN)</label>
                            <input type="text" id="tax_id" name="tax_id" placeholder="TIN" data-number-field>
                            <span class="field-error" id="tax_id_error" role="alert"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="tourism_license">Local Tourism License</label>
                        <input type="text" id="tourism_license" name="tourism_license" placeholder="Tourism license number (if any)" data-number-field>
                        <span class="field-error" id="tourism_license_error" role="alert"></span>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">4️⃣ Bank Account Details</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bank_name">Bank Name *</label>
                            <input type="text" id="bank_name" name="bank_name" required placeholder="Bank">
                        </div>
                        <div class="form-group">
                            <label for="bank_account_name">Account Holder Name *</label>
                            <input type="text" id="bank_account_name" name="bank_account_name" required placeholder="Name on account">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bank_account_number">Account Number *</label>
                        <input type="text" id="bank_account_number" name="bank_account_number" required placeholder="Account number" data-number-field data-required-number>
                        <span class="field-error" id="bank_account_number_error" role="alert"></span>
                    </div>
                    <p class="helper-text">Payments for bookings will be sent to this account.</p>
                </div>

                <div class="form-actions" style="margin-top: 24px;">
                    <button type="submit" class="btn-primary">Submit for approval</button>
                </div>
            </form>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
    <script>
    (function() {
        var numberFields = document.querySelectorAll('[data-number-field]');
        var requiredNumberIds = ['bank_account_number'];
        function isValidNumber(value) {
            if (value === '') return null;
            if (!/^[\d\s\-]+$/.test(value) || !/\d/.test(value)) return false;
            return true;
        }
        function validateField(input) {
            var id = input.id;
            var errorEl = document.getElementById(id + '_error');
            if (!errorEl) return true;
            var value = (input.value || '').trim();
            var required = input.hasAttribute('data-required-number');
            if (value === '') {
                if (required) {
                    errorEl.textContent = 'This field is required and must contain only numbers.';
                    input.classList.add('invalid');
                    return false;
                }
                errorEl.textContent = '';
                input.classList.remove('invalid');
                return true;
            }
            var valid = isValidNumber(value);
            if (valid === false) {
                errorEl.textContent = 'Please enter only numbers (spaces or hyphens allowed).';
                input.classList.add('invalid');
                return false;
            }
            errorEl.textContent = '';
            input.classList.remove('invalid');
            return true;
        }
        numberFields.forEach(function(input) {
            input.addEventListener('blur', function() { validateField(this); });
            input.addEventListener('input', function() {
                var err = document.getElementById(this.id + '_error');
                if (err && err.textContent) validateField(this);
            });
        });
        document.querySelector('form.property-form').addEventListener('submit', function(e) {
            var allValid = true;
            numberFields.forEach(function(input) {
                if (!validateField(input)) allValid = false;
            });
            if (!allValid) e.preventDefault();
        });
    })();
    </script>
</body>
</html>

