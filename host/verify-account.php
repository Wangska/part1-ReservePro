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

    if (empty($errors)) {
        $conn = getDBConnection();

        // Insert or update host_documents
        $stmt = $conn->prepare("
            INSERT INTO host_documents 
            (user_id, gov_id_type, gov_id_number, ownership_proof_type, ownership_reference, business_registration, tax_id, tourism_license, bank_name, bank_account_name, bank_account_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              gov_id_type = VALUES(gov_id_type),
              gov_id_number = VALUES(gov_id_number),
              ownership_proof_type = VALUES(ownership_proof_type),
              ownership_reference = VALUES(ownership_reference),
              business_registration = VALUES(business_registration),
              tax_id = VALUES(tax_id),
              tourism_license = VALUES(tourism_license),
              bank_name = VALUES(bank_name),
              bank_account_name = VALUES(bank_account_name),
              bank_account_number = VALUES(bank_account_number)
        ");
        $stmt->bind_param(
            "issssssssss",
            $user['id'],
            $gov_id_type,
            $gov_id_number,
            $ownership_proof_type,
            $ownership_reference,
            $business_registration,
            $tax_id,
            $tourism_license,
            $bank_name,
            $bank_account_name,
            $bank_account_number
        );
        $stmt->execute();
        $stmt->close();

        // Mark host as verified
        $stmt = $conn->prepare("UPDATE users SET host_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->close();

        $conn->close();

        // Refresh user data in session helper on next request
        header('Location: dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Verification - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=14.0">
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
                            <input type="text" id="gov_id_number" name="gov_id_number" placeholder="ID reference number">
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
                            <input type="text" id="business_registration" name="business_registration" placeholder="Business registration number">
                        </div>
                        <div class="form-group">
                            <label for="tax_id">Tax Identification Number (TIN)</label>
                            <input type="text" id="tax_id" name="tax_id" placeholder="TIN">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="tourism_license">Local Tourism License</label>
                        <input type="text" id="tourism_license" name="tourism_license" placeholder="Tourism license number (if any)">
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
                        <input type="text" id="bank_account_number" name="bank_account_number" required placeholder="Account number">
                    </div>
                    <p class="helper-text">Payments for bookings will be sent to this account.</p>
                </div>

                <div class="form-actions" style="margin-top: 24px;">
                    <button type="submit" class="btn-primary">Submit &amp; Continue to Dashboard</button>
                </div>
            </form>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>

