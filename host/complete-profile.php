<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

if (!$user || ($user['role'] ?? 'guest') !== 'host') {
    header('Location: ../home.php');
    exit();
}

// Ensure extended columns exist
initializeHostTables();

$errors = [];

// Load latest user data (including date_of_birth) from DB
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT first_name, last_name, email, date_of_birth FROM users WHERE id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first_name = $row['first_name'] ?? $user['first_name'] ?? '';
$last_name = $row['last_name'] ?? $user['last_name'] ?? '';
$email = $row['email'] ?? $user['email'] ?? '';
$date_of_birth = $row['date_of_birth'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dob_month = (int) ($_POST['dob_month'] ?? 0);
    $dob_day = (int) ($_POST['dob_day'] ?? 0);
    $dob_year = (int) ($_POST['dob_year'] ?? 0);

    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '') $errors[] = 'Last name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

    $dob_value = null;
    if ($dob_year && $dob_month && $dob_day) {
        if (checkdate($dob_month, $dob_day, $dob_year)) {
            $dob_value = sprintf('%04d-%02d-%02d', $dob_year, $dob_month, $dob_day);
        } else {
            $errors[] = 'Please select a valid date of birth.';
        }
    }

    if (empty($errors)) {
        // Update user profile
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, date_of_birth = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $email, $dob_value, $user['id']);
        $stmt->execute();
        $stmt->close();

        $conn->close();
        // Next step: host verification flow
        header('Location: verify-account.php');
        exit();
    }
}

// For select defaults
$dob_year_val = null;
$dob_month_val = null;
$dob_day_val = null;
if (!empty($date_of_birth)) {
    [$dob_year_val, $dob_month_val, $dob_day_val] = explode('-', $date_of_birth);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finish Host Signup - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=15.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=15.0">
    <link rel="stylesheet" href="../assets/css/animations.css?v=1.0">
    <style>
        .host-onboard-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .host-onboard-card {
            width: 100%;
            max-width: 540px;
            background: #111827;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(0,0,0,0.6);
            border: 1px solid #1F2937;
            padding: 28px 28px 24px;
        }
        body.light-mode .host-onboard-card {
            background: #FFFFFF;
            box-shadow: 0 18px 45px rgba(0,0,0,0.15);
            border-color: #E5E7EB;
        }
        .host-onboard-header {
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        body.dark-mode .host-onboard-header {
            border-color: #1F2937;
        }
        .host-onboard-title {
            font-size: 20px;
            font-weight: 600;
            color: #F9FAFB;
        }
        body.light-mode .host-onboard-title {
            color: #111827;
        }
        .host-onboard-section-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #F9FAFB;
        }
        body.light-mode .host-onboard-section-title {
            color: #111827;
        }
        .host-onboard-field-label {
            font-size: 13px;
            font-weight: 500;
            color: #E5E7EB;
            margin-bottom: 4px;
        }
        body.light-mode .host-onboard-field-label {
            color: #4B5563;
        }
        .host-onboard-input,
        .host-onboard-select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            font-size: 14px;
            outline: none;
            background: #FFFFFF;
            color: #111827;
        }
        .host-onboard-input:focus,
        .host-onboard-select:focus {
            border-color: #D4A574;
            box-shadow: 0 0 0 1px rgba(212,165,116,0.4);
        }
        body.dark-mode .host-onboard-input,
        body.dark-mode .host-onboard-select {
            background: #111827;
            border-color: #374151;
            color: #F9FAFB;
        }
        body.dark-mode .host-onboard-input:focus,
        body.dark-mode .host-onboard-select:focus {
            border-color: #D4A574;
            box-shadow: 0 0 0 1px rgba(212,165,116,0.4);
        }
        .host-onboard-section {
            margin-bottom: 20px;
        }
        .host-onboard-helper {
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 4px;
        }
        body.light-mode .host-onboard-helper {
            color: #6B7280;
        }
        .host-onboard-dob-row {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr 1fr;
            gap: 8px;
        }
        .host-onboard-contact-note {
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 2px;
        }
        body.light-mode .host-onboard-contact-note {
            color: #6B7280;
        }
        .host-onboard-footer {
            margin-top: 16px;
            font-size: 11px;
            color: #9CA3AF;
        }
        body.light-mode .host-onboard-footer {
            color: #6B7280;
        }
        .host-onboard-footer a {
            color: #2563EB;
            text-decoration: underline;
        }
        body.dark-mode .host-onboard-footer a {
            color: #60A5FA;
        }
        .host-onboard-submit {
            width: 100%;
            margin-top: 18px;
            padding: 12px 16px;
            border-radius: 999px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
        }
        .host-onboard-errors {
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #B91C1C;
            font-size: 13px;
        }
        body.dark-mode .host-onboard-errors {
            background: rgba(239,68,68,0.15);
            border-color: rgba(239,68,68,0.5);
            color: #FCA5A5;
        }
    </style>
</head>
<body>
    <div class="theme-toggle" style="position: fixed; top: 16px; right: 16px; z-index: 1000;">
        <span class="theme-toggle-icon">☀️</span>
        <span class="theme-toggle-text">Light</span>
    </div>

    <div class="host-onboard-wrapper">
        <div class="host-onboard-card">
            <div class="host-onboard-header">
                <div class="host-onboard-title">Finish setting up your host account</div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="host-onboard-errors">
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="complete-profile.php" novalidate>
                <div class="host-onboard-section">
                    <div class="host-onboard-section-title">Legal name</div>
                    <div class="host-onboard-helper">Make sure this matches the name on your government ID.</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
                        <div>
                            <div class="host-onboard-field-label">First name on ID</div>
                            <input type="text" name="first_name" class="host-onboard-input" value="<?php echo htmlspecialchars($first_name); ?>" required>
                        </div>
                        <div>
                            <div class="host-onboard-field-label">Last name on ID</div>
                            <input type="text" name="last_name" class="host-onboard-input" value="<?php echo htmlspecialchars($last_name); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="host-onboard-section">
                    <div class="host-onboard-section-title">Date of birth</div>
                    <div class="host-onboard-dob-row" style="margin-top:8px;">
                        <div>
                            <div class="host-onboard-field-label">Month</div>
                            <select name="dob_month" class="host-onboard-select">
                                <option value="">Month</option>
                                <?php
                                $months = [
                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                ];
                                foreach ($months as $mVal => $mName):
                                    $selected = ((int)$dob_month_val === $mVal) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $mVal; ?>" <?php echo $selected; ?>><?php echo $mName; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <div class="host-onboard-field-label">Day</div>
                            <select name="dob_day" class="host-onboard-select">
                                <option value="">Day</option>
                                <?php for ($d = 1; $d <= 31; $d++): 
                                    $sel = ((int)$dob_day_val === $d) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $d; ?>" <?php echo $sel; ?>><?php echo $d; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <div class="host-onboard-field-label">Year</div>
                            <select name="dob_year" class="host-onboard-select">
                                <option value="">Year</option>
                                <?php
                                $currentYear = (int)date('Y');
                                for ($y = $currentYear - 18; $y >= $currentYear - 100; $y--):
                                    $sel = ((int)$dob_year_val === $y) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $y; ?>" <?php echo $sel; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="host-onboard-section">
                    <div class="host-onboard-section-title">Contact info</div>
                    <div style="margin-top:8px;">
                        <div class="host-onboard-field-label">Email</div>
                        <input type="email" name="email" class="host-onboard-input" value="<?php echo htmlspecialchars($email); ?>" required>
                        <div class="host-onboard-contact-note">We’ll email you booking updates and important notifications.</div>
                    </div>
                </div>

                <button type="submit" class="host-onboard-submit">
                    Agree and continue
                </button>

                <div class="host-onboard-footer">
                    By selecting Agree and continue, you confirm that your details are correct and agree to ReservePro’s
                    <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>

