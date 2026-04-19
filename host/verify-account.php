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
$stmt = $conn->prepare("SELECT id, verification_status, created_at, id_full_name, gov_id_photo_path, ownership_doc_photo_path, gov_id_number, ownership_reference FROM host_documents WHERE user_id = ? ORDER BY id DESC LIMIT 1");
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
    $id_full_name = trim($_POST['id_full_name'] ?? '');
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

    if ($id_full_name === '') $errors[] = 'Full name (as shown on your ID) is required';
    if ($gov_id_type === '') $errors[] = 'Government ID type is required';
    if ($ownership_proof_type === '') $errors[] = 'Property ownership/permission type is required';
    if ($bank_name === '') $errors[] = 'Bank name is required';
    if ($bank_account_name === '') $errors[] = 'Bank account name is required';
    if ($bank_account_number === '') $errors[] = 'Bank account number is required';
    if ($gov_id_number === '') $errors[] = 'Government ID number is required';
    if ($ownership_reference === '') $errors[] = 'Supporting document number/reference is required';

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

    function save_verification_upload($file, $userId, $prefix, &$errors) {
        if (!isset($file) || !isset($file['error'])) {
            $errors[] = 'Upload is missing for ' . $prefix . '.';
            return null;
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // caller decides if required
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload ' . $prefix . '. Please try again.';
            return null;
        }
        $maxSize = 6 * 1024 * 1024; // 6MB
        $minSize = 60 * 1024;       // 60KB (helps reject ultra-blurry tiny images)
        if (($file['size'] ?? 0) > $maxSize) {
            $errors[] = ucfirst($prefix) . ' file is too large (max 6MB).';
            return null;
        }
        if (($file['size'] ?? 0) < $minSize) {
            $errors[] = ucfirst($prefix) . ' image looks too small. Please upload a clearer photo.';
            return null;
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
        if (!in_array($ext, $allowed, true)) {
            $errors[] = ucfirst($prefix) . ' must be an image (JPG, PNG, WEBP, or AVIF).';
            return null;
        }
        $tmp = $file['tmp_name'] ?? '';
        $img = @getimagesize($tmp);
        if ($img && !empty($img[0]) && !empty($img[1])) {
            $w = (int)$img[0];
            $h = (int)$img[1];
            if ($w < 900 || $h < 600) {
                $errors[] = ucfirst($prefix) . ' image resolution is too low. Please upload a clearer photo (at least 900×600).';
                return null;
            }
        } elseif ($ext === 'avif') {
            $mime = function_exists('mime_content_type') ? (string)@mime_content_type($tmp) : '';
            if ($mime !== '' && stripos($mime, 'image/') !== 0) {
                $errors[] = ucfirst($prefix) . ' must be a valid image file.';
                return null;
            }
        } else {
            $errors[] = ucfirst($prefix) . ' must be a valid image file.';
            return null;
        }
<<<<<<< HEAD
=======
        $w = (int)$img[0];
        $h = (int)$img[1];
        if ($w < 900 || $h < 600) {
            $errors[] = ucfirst($prefix) . ' image resolution is too low. Please upload a clearer photo (at least 900Ã—600).';
            return null;
        }
>>>>>>> ad87513098603380b3b373b63b23603737d70897

        $baseDir = dirname(__DIR__) . '/uploads/host-documents/' . (int)$userId . '/';
        if (!file_exists($baseDir)) {
            @mkdir($baseDir, 0777, true);
            @chmod($baseDir, 0777);
        }
        if (!is_dir($baseDir) || !is_writable($baseDir)) {
            $errors[] = 'Upload directory is not writable. Please contact support.';
            return null;
        }
        $filename = $prefix . '_' . (int)$userId . '_' . time() . '.' . $ext;
        $dest = $baseDir . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            $errors[] = 'Failed to save ' . $prefix . ' image. Please try again.';
            return null;
        }
        return 'uploads/host-documents/' . (int)$userId . '/' . $filename;
    }

    if (empty($errors)) {
        $conn = getDBConnection();
        initializeHostTables();

        $stmt = $conn->prepare("SELECT id FROM host_documents WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Existing file paths (if re-submitting without re-upload)
        $existingGovPath = $existing['gov_id_photo_path'] ?? null;
        $existingOwnPath = $existing['ownership_doc_photo_path'] ?? null;

        $govPath = save_verification_upload($_FILES['gov_id_photo'] ?? null, $user['id'], 'gov_id', $errors);
        $ownPath = save_verification_upload($_FILES['supporting_doc_photo'] ?? null, $user['id'], 'supporting_doc', $errors);

        if ($govPath === null && $existingGovPath) $govPath = $existingGovPath;
        if ($ownPath === null && $existingOwnPath) $ownPath = $existingOwnPath;

        if (!$govPath) $errors[] = 'Government ID photo is required (must be clear, not blurry/cropped).';
        if (!$ownPath) $errors[] = 'Supporting document photo is required (must be clear, not blurry/cropped).';

        if (!empty($errors)) {
            $conn->close();
        } else {
        $status = 'pending';
        if ($row) {
            $stmt = $conn->prepare("
                UPDATE host_documents SET
                  id_full_name = ?, gov_id_type = ?, gov_id_number = ?, gov_id_photo_path = ?,
                  ownership_proof_type = ?, ownership_reference = ?, ownership_doc_photo_path = ?,
                  business_registration = ?, tax_id = ?, tourism_license = ?,
                  bank_name = ?, bank_account_name = ?, bank_account_number = ?,
                  verification_status = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("ssssssssssssssi",
                $id_full_name, $gov_id_type, $gov_id_number, $govPath,
                $ownership_proof_type, $ownership_reference, $ownPath,
                $business_registration, $tax_id, $tourism_license,
                $bank_name, $bank_account_name, $bank_account_number,
                $status, $user['id']
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO host_documents
                (user_id, id_full_name, gov_id_type, gov_id_number, gov_id_photo_path, ownership_proof_type, ownership_reference, ownership_doc_photo_path, business_registration, tax_id, tourism_license, bank_name, bank_account_name, bank_account_number, verification_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssssssssssss",
                $user['id'],
                $id_full_name,
                $gov_id_type, $gov_id_number, $govPath,
                $ownership_proof_type, $ownership_reference, $ownPath,
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
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body.hv-page { background: #06090F; color: #e5e7eb; min-height: 100vh; }
        body.hv-page::before, body.hv-page::after { display: none !important; }

        /* Top bar */
        .hv-topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            height: 60px; padding: 0 28px;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(6,9,15,0.97);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            backdrop-filter: blur(20px);
        }
        .hv-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .hv-brand .brand-icon { width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; border: 2px solid rgba(212,165,116,0.6); box-sizing: border-box; }
        .hv-brand span { font-size: 16px; font-weight: 700; background: linear-gradient(135deg,#D4A574,#B8935F); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hv-topbar-right { display: flex; align-items: center; gap: 18px; }
        .hv-user-chip { display: flex; align-items: center; gap: 8px; }
        .hv-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,#D4A574,#B8935F); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #0f0f0f; flex-shrink: 0; }
        .hv-user-chip span { font-size: 13px; color: #d1d5db; font-weight: 500; }
        .hv-logout { font-size: 13px; color: #6b7280; text-decoration: none; transition: color 0.2s; }
        .hv-logout:hover { color: #D4A574; }

        /* Main */
        .hv-main { padding: 100px 64px 80px; }
        .hv-container { max-width: 1200px; margin: 0 auto; }

        /* Hero */
        .hv-hero {
            padding: 36px 40px; margin-bottom: 28px; border-radius: 24px;
            border: 1px solid rgba(148,163,184,0.16);
            background: linear-gradient(135deg, rgba(17,24,39,0.96), rgba(30,41,59,0.88));
            box-shadow: 0 24px 48px rgba(0,0,0,0.24);
        }
        .hv-hero h1 { font-size: 32px; font-weight: 700; color: #fff; margin: 0 0 8px; }
        .hv-hero p { font-size: 16px; color: #94a3b8; margin: 0; }

        /* Alerts */
        .hv-alert { padding: 18px 22px; border-radius: 14px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 14px; font-size: 15px; }
        .hv-alert-icon { flex-shrink: 0; margin-top: 1px; font-size: 18px; }
        .hv-alert h4 { margin: 0 0 4px; font-size: 15px; font-weight: 700; }
        .hv-alert p, .hv-alert ul { margin: 0; font-size: 14px; line-height: 1.6; }
        .hv-alert ul { padding-left: 16px; }
        .hv-alert-error  { background: rgba(239,68,68,0.1);   border: 1px solid rgba(239,68,68,0.25);   color: #fca5a5; }
        .hv-alert-error h4  { color: #fca5a5; }
        .hv-alert-success { background: rgba(34,197,94,0.1);  border: 1px solid rgba(34,197,94,0.22);   color: #86efac; }
        .hv-alert-success h4 { color: #86efac; }
        .hv-alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.22);  color: #fcd34d; }
        .hv-alert-warning h4 { color: #fcd34d; }

        /* Pending status card */
        .hv-status-card {
            padding: 48px 40px; border-radius: 24px; text-align: center;
            background: rgba(17,24,39,0.86);
            border: 1px solid rgba(148,163,184,0.16);
            box-shadow: 0 24px 48px rgba(0,0,0,0.24);
        }
        .hv-status-icon { font-size: 48px; color: #fbbf24; margin-bottom: 18px; }
        .hv-status-card h2 { font-size: 26px; font-weight: 700; color: #fff; margin: 0 0 10px; }
        .hv-status-card > p { font-size: 16px; color: #94a3b8; margin: 0 0 28px; }
        .hv-meta-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 14px; text-align: left; }
        .hv-meta-item { padding: 16px 18px; border-radius: 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); }
        .hv-meta-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; display: block; margin-bottom: 6px; }
        .hv-meta-value { font-size: 16px; font-weight: 600; color: #e5e7eb; }

        /* Form sections */
        .hv-form { display: flex; flex-direction: column; gap: 20px; }
        .hv-section {
            padding: 32px 36px; border-radius: 24px;
            background: rgba(17,24,39,0.86);
            border: 1px solid rgba(148,163,184,0.16);
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        }
        .hv-section-title { font-size: 17px; font-weight: 700; color: #e5e7eb; margin: 0 0 22px; display: flex; align-items: center; gap: 10px; }
        .hv-section-title i { color: #D4A574; font-size: 16px; }
        .hv-optional { font-size: 13px; font-weight: 400; color: #6b7280; margin-left: 4px; }
        .hv-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .hv-group { display: flex; flex-direction: column; gap: 7px; }
        .hv-group + .hv-group, .hv-row + .hv-group, .hv-row + .hv-row { margin-top: 18px; }
        .hv-group label { font-size: 13px; font-weight: 600; color: #cbd5e1; }
        .hv-group input, .hv-group select {
            width: 100%; min-height: 50px; padding: 12px 16px; box-sizing: border-box;
            border-radius: 12px; border: 1px solid rgba(148,163,184,0.16);
            background: rgba(255,255,255,0.04); color: #e5e7eb; font-size: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .hv-group input::placeholder { color: #4b5563; }
        .hv-group input:focus, .hv-group select:focus { outline: none; border-color: rgba(212,165,116,0.5); box-shadow: 0 0 0 3px rgba(212,165,116,0.08); }
        .hv-group option { background: #1a1a2e; color: #e5e7eb; }
        .hv-hint { font-size: 13px; color: #4b5563; margin: 0; }
        .hv-hint a { color: #D4A574; }
        .field-error { display: block; color: #fca5a5; font-size: 13px; }
        .field-error:empty { display: none; }
        input.invalid, select.invalid { border-color: #ef4444 !important; }

        /* File preview */
        .hv-preview { margin-top: 10px; border-radius: 12px; border: 1px solid rgba(148,163,184,0.12); background: rgba(0,0,0,0.2); padding: 14px; }
        .hv-preview.hidden { display: none; }
        .hv-preview img { width: 100%; max-height: 200px; object-fit: contain; border-radius: 8px; }
        .hv-preview-meta { font-size: 13px; color: #9ca3af; margin-top: 8px; }
        .hv-preview-warn { margin-top: 8px; padding: 10px 12px; border-radius: 10px; font-size: 13px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }
        .hv-preview-warn.hidden { display: none; }

        /* Submit row */
        .hv-submit { display: flex; align-items: center; justify-content: flex-end; gap: 18px; padding-top: 10px; }
        .hv-submit-note { font-size: 13px; color: #4b5563; margin-right: auto; line-height: 1.6; max-width: 360px; }
        .hv-btn-submit {
            padding: 15px 36px; border: none; border-radius: 999px; cursor: pointer; white-space: nowrap;
            background: linear-gradient(135deg,#d4a574,#b8935f);
            color: #0f0f0f; font-size: 16px; font-weight: 700;
            box-shadow: 0 8px 24px rgba(184,147,95,0.25);
            transition: opacity 0.2s, transform 0.15s;
        }
        .hv-btn-submit:hover { opacity: 0.88; transform: translateY(-1px); }

        /* Light mode */
        body.light-mode.hv-page { background: #f1f5f9; color: #0f172a; }
        body.light-mode .hv-topbar { background: rgba(255,255,255,0.97); border-bottom-color: rgba(15,23,42,0.08); }
        body.light-mode .hv-brand span { background: linear-gradient(135deg,#8c6740,#6d4e2e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        body.light-mode .hv-user-chip span { color: #374151; }
        body.light-mode .hv-logout { color: #94a3b8; }
        body.light-mode .hv-hero { background: linear-gradient(135deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94)); border-color: rgba(15,23,42,0.1); box-shadow: 0 12px 32px rgba(15,23,42,0.07); }
        body.light-mode .hv-hero h1 { color: #0f172a; }
        body.light-mode .hv-hero p { color: #64748b; }
        body.light-mode .hv-section, body.light-mode .hv-status-card { background: #fff; border-color: rgba(15,23,42,0.08); box-shadow: 0 4px 16px rgba(15,23,42,0.06); }
        body.light-mode .hv-section-title, body.light-mode .hv-status-card h2 { color: #0f172a; }
        body.light-mode .hv-status-card > p { color: #64748b; }
        body.light-mode .hv-meta-item { background: #f8fafc; border-color: rgba(15,23,42,0.06); }
        body.light-mode .hv-meta-label { color: #64748b; }
        body.light-mode .hv-meta-value { color: #0f172a; }
        body.light-mode .hv-group label { color: #374151; }
        body.light-mode .hv-group input, body.light-mode .hv-group select { background: #fff; color: #0f172a; border-color: rgba(15,23,42,0.12); }
        body.light-mode .hv-group input::placeholder { color: #94a3b8; }
        body.light-mode .hv-hint { color: #94a3b8; }
        body.light-mode .hv-submit-note { color: #94a3b8; }
        body.light-mode .hv-preview { background: #f8fafc; border-color: #e2e8f0; }
        body.light-mode .hv-preview-meta { color: #475569; }

        @media (max-width: 640px) {
            .hv-topbar { padding: 0 16px; }
            .hv-user-chip span { display: none; }
            .hv-main { padding: 76px 20px 48px; }
            .hv-hero { padding: 20px; }
            .hv-row { grid-template-columns: 1fr; }
            .hv-submit { flex-direction: column; align-items: stretch; }
            .hv-btn-submit { width: 100%; text-align: center; }
            .hv-submit-note { max-width: 100%; margin-right: 0; }
        }
    </style>
</head>
<body class="hv-page">

<header class="hv-topbar">
    <a href="../home.php" class="hv-brand">
        <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
        <span>ReservePro</span>
    </a>
    <div class="hv-topbar-right">
        
        <div class="hv-user-chip">
            <div class="hv-avatar"><?php echo strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)); ?></div>
            <span><?php echo htmlspecialchars($user['first_name'].' '.$user['last_name']); ?></span>
        </div>
        <a href="../logout.php" class="hv-logout">Logout</a>
    </div>
</header>

<main class="hv-main">
    <div class="hv-container">

        <div class="hv-hero">
            <h1>Host Verification</h1>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="hv-alert hv-alert-error">
            <div class="hv-alert-icon"><i class="fa-solid fa-circle-xmark"></i></div>
            <div>
                <h4>Please fix the following:</h4>
                <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($just_submitted): ?>
        <div class="hv-alert hv-alert-success">
            <div class="hv-alert-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><h4>Submitted!</h4><p>We&rsquo;ve received your details and will review them shortly.</p></div>
        </div>
        <?php endif; ?>

        <?php if ($verification_rejected && !$verification_pending): ?>
        <div class="hv-alert hv-alert-warning">
            <div class="hv-alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div><h4>Update required</h4><p>Your previous submission wasn&rsquo;t approved. Correct your details and resubmit.</p></div>
        </div>
        <?php endif; ?>

        <?php if ($verification_pending): ?>
        <div class="hv-status-card">
            <div class="hv-status-icon"><i class="fa-solid fa-clock"></i></div>
            <h2>Under Review</h2>
            <p>Our team is reviewing your submission. You&rsquo;ll be notified by email once approved.</p>
            <div class="hv-meta-grid">
                <div class="hv-meta-item">
                    <span class="hv-meta-label">Status</span>
                    <span class="hv-meta-value">Under review</span>
                </div>
                <div class="hv-meta-item">
                    <span class="hv-meta-label">Next step</span>
                    <span class="hv-meta-value">Email notification</span>
                </div>
                <?php if ($submitted_at): ?>
                <div class="hv-meta-item">
                    <span class="hv-meta-label">Submitted</span>
                    <span class="hv-meta-value"><?php echo date('M j, Y', strtotime($submitted_at)); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$verification_pending): ?>
        <form method="POST" action="verify-account.php" class="hv-form" enctype="multipart/form-data">

            <div class="hv-section">
                <h3 class="hv-section-title">Identity</h3>
                <div class="hv-row">
                    <div class="hv-group">
                        <label for="id_full_name">Full name (as on your ID) *</label>
                        <input type="text" id="id_full_name" name="id_full_name" required placeholder="Legal name"
                               value="<?php echo htmlspecialchars($_POST['id_full_name'] ?? ($existing['id_full_name'] ?? '')); ?>">
                    </div>
                    <div class="hv-group">
                        <label for="gov_id_number">ID number *</label>
                        <input type="text" id="gov_id_number" name="gov_id_number" placeholder="Reference number" data-number-field required
                               value="<?php echo htmlspecialchars($_POST['gov_id_number'] ?? ($existing['gov_id_number'] ?? '')); ?>">
                        <span class="field-error" id="gov_id_number_error" role="alert"></span>
                    </div>
                </div>
                <div class="hv-row">
                    <div class="hv-group">
                        <label for="gov_id_type">ID type *</label>
                        <select id="gov_id_type" name="gov_id_type" required>
                            <option value="">Select type</option>
                            <?php $gid = $_POST['gov_id_type'] ?? ''; ?>
                            <option value="Passport" <?php echo $gid==='Passport'?'selected':''; ?>>Passport</option>
                            <option value="National ID" <?php echo $gid==='National ID'?'selected':''; ?>>National ID</option>
                            <option value="Driver's License" <?php echo $gid==="Driver's License"?'selected':''; ?>>Driver's License</option>
                            <option value="Other" <?php echo $gid==='Other'?'selected':''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="hv-group">
                    <label for="gov_id_photo">ID photo * <span class="hv-hint" style="display:inline;">(min 900&times;600, max 6MB)</span></label>
                    <input type="file" id="gov_id_photo" name="gov_id_photo" accept="image/*" <?php echo empty($existing['gov_id_photo_path'])?'required':''; ?>>
                    <?php if (!empty($existing['gov_id_photo_path'])): ?>
                        <p class="hv-hint">Current: <a href="../<?php echo htmlspecialchars($existing['gov_id_photo_path']); ?>" target="_blank" rel="noopener">View file</a></p>
                    <?php endif; ?>
                    <div class="hv-preview hidden" id="govPreview">
                        <img id="govPreviewImg" alt="ID preview" />
                        <div class="hv-preview-meta" id="govPreviewMeta"></div>
                        <div class="hv-preview-warn hidden" id="govPreviewWarn"></div>
                    </div>
                </div>
            </div>

            <div class="hv-section">
                <h3 class="hv-section-title">Hosting Permission</h3>
                <div class="hv-row">
                    <div class="hv-group">
                        <label for="ownership_proof_type">Proof type *</label>
                        <select id="ownership_proof_type" name="ownership_proof_type" required>
                            <option value="">Select type</option>
                            <?php $opt = $_POST['ownership_proof_type'] ?? ''; ?>
                            <option value="Land title / Ownership certificate" <?php echo $opt==='Land title / Ownership certificate'?'selected':''; ?>>Land title</option>
                            <option value="Lease agreement" <?php echo $opt==='Lease agreement'?'selected':''; ?>>Lease agreement</option>
                            <option value="Written landlord permission" <?php echo $opt==='Written landlord permission'?'selected':''; ?>>Landlord permission</option>
                        </select>
                    </div>
                    <div class="hv-group">
                        <label for="ownership_reference">Reference number *</label>
                        <input type="text" id="ownership_reference" name="ownership_reference" placeholder="Permit / title no." data-number-field required
                               value="<?php echo htmlspecialchars($_POST['ownership_reference'] ?? ($existing['ownership_reference'] ?? '')); ?>">
                    </div>
                </div>
                <div class="hv-group">
                    <label for="supporting_doc_photo">Document photo * <span class="hv-hint" style="display:inline;">(min 900&times;600, max 6MB)</span></label>
                    <input type="file" id="supporting_doc_photo" name="supporting_doc_photo" accept="image/*" <?php echo empty($existing['ownership_doc_photo_path'])?'required':''; ?>>
                    <?php if (!empty($existing['ownership_doc_photo_path'])): ?>
                        <p class="hv-hint">Current: <a href="../<?php echo htmlspecialchars($existing['ownership_doc_photo_path']); ?>" target="_blank" rel="noopener">View file</a></p>
                    <?php endif; ?>
                    <div class="hv-preview hidden" id="supportPreview">
                        <img id="supportPreviewImg" alt="Document preview" />
                        <div class="hv-preview-meta" id="supportPreviewMeta"></div>
                        <div class="hv-preview-warn hidden" id="supportPreviewWarn"></div>
                    </div>
                </div>
            </div>

            <div class="hv-section">
                <h3 class="hv-section-title">Business Documents <span class="hv-optional">(optional)</span></h3>
                <div class="hv-row">
                    <div class="hv-group">
                        <label for="business_registration">Business registration no.</label>
                        <input type="text" id="business_registration" name="business_registration" placeholder="Registration number" data-number-field>
                        <span class="field-error" id="business_registration_error" role="alert"></span>
                    </div>
                    <div class="hv-group">
                        <label for="tax_id">Tax ID (TIN)</label>
                        <input type="text" id="tax_id" name="tax_id" placeholder="TIN" data-number-field>
                        <span class="field-error" id="tax_id_error" role="alert"></span>
                    </div>
                </div>
                <div class="hv-group">
                    <label for="tourism_license">Tourism license no.</label>
                    <input type="text" id="tourism_license" name="tourism_license" placeholder="If applicable" data-number-field>
                    <span class="field-error" id="tourism_license_error" role="alert"></span>
                </div>
            </div>

            <div class="hv-section">
                <h3 class="hv-section-title">Payout Account</h3>
                <div class="hv-row">
                    <div class="hv-group">
                        <label for="bank_name">Bank name *</label>
                        <input type="text" id="bank_name" name="bank_name" required placeholder="e.g. BDO, BPI">
                    </div>
                    <div class="hv-group">
                        <label for="bank_account_name">Account holder *</label>
                        <input type="text" id="bank_account_name" name="bank_account_name" required placeholder="Name on account">
                    </div>
                </div>
                <div class="hv-group">
                    <label for="bank_account_number">Account number *</label>
                    <input type="text" id="bank_account_number" name="bank_account_number" required placeholder="Account number" data-number-field data-required-number>
                    <span class="field-error" id="bank_account_number_error" role="alert"></span>
                </div>
            </div>

            <div class="hv-submit">
                <p class="hv-submit-note">By submitting, you confirm all information is accurate and can be reviewed by our team.</p>
                <button type="submit" class="hv-btn-submit">Submit for approval</button>
            </div>

        </form>
        <?php endif; ?>

    </div>
</main>


<script>
(function() {
    var numberFields = document.querySelectorAll('[data-number-field]');
    var form = document.querySelector('form.hv-form');
    function isValidNumber(v) {
        if (v === '') return null;
        return /^[\d\s\-]+$/.test(v) && /\d/.test(v);
    }
    function validateField(input) {
        var err = document.getElementById(input.id + '_error');
        if (!err) return true;
        var val = (input.value || '').trim();
        var required = input.hasAttribute('data-required-number');
        if (val === '') {
            if (required) { err.textContent = 'Required — numbers only.'; input.classList.add('invalid'); return false; }
            err.textContent = ''; input.classList.remove('invalid'); return true;
        }
        if (!isValidNumber(val)) {
            err.textContent = 'Numbers only (spaces or hyphens allowed).';
            input.classList.add('invalid'); return false;
        }
        err.textContent = ''; input.classList.remove('invalid'); return true;
    }
    numberFields.forEach(function(f) {
        f.addEventListener('blur', function() { validateField(this); });
        f.addEventListener('input', function() { var e = document.getElementById(this.id+'_error'); if (e && e.textContent) validateField(this); });
    });
    if (form) form.addEventListener('submit', function(e) {
        var ok = true;
        numberFields.forEach(function(f) { if (!validateField(f)) ok = false; });
        if (!ok) e.preventDefault();
    });

    function fmtBytes(b) { b=Number(b)||0; if(b<1024) return b+' B'; var k=b/1024; if(k<1024) return k.toFixed(1)+' KB'; return (k/1024).toFixed(1)+' MB'; }
    function setupPreview(inputId, boxId, imgId, metaId, warnId) {
        var inp=document.getElementById(inputId), box=document.getElementById(boxId),
            img=document.getElementById(imgId), meta=document.getElementById(metaId), warn=document.getElementById(warnId);
        if (!inp||!box||!img||!meta||!warn) return;
        function clr() { box.classList.add('hidden'); warn.classList.add('hidden'); warn.textContent=''; img.removeAttribute('src'); meta.textContent=''; }
        inp.addEventListener('change', function() {
            var file = inp.files && inp.files[0] ? inp.files[0] : null;
            if (!file || file.type.indexOf('image/') !== 0) { clr(); return; }
            var url = URL.createObjectURL(file);
            img.onload = function() {
                var w=img.naturalWidth||0, h=img.naturalHeight||0;
                meta.innerHTML = '<strong>'+file.name+'</strong> &mdash; '+fmtBytes(file.size)+' &mdash; '+w+'&times;'+h;
                var ws = [];
                if (file.size < 60*1024) ws.push('File too small, may be blurry.');
                if (file.size > 6*1024*1024) ws.push('File exceeds 6MB limit.');
                if (w && h && (w < 900 || h < 600)) ws.push('Resolution too low — use at least 900\u00d7600.');
                if (ws.length) { warn.textContent = ws.join(' '); warn.classList.remove('hidden'); }
                else warn.classList.add('hidden');
                box.classList.remove('hidden');
            };
            img.onerror = clr;
            img.src = url;
        });
    }
    setupPreview('gov_id_photo','govPreview','govPreviewImg','govPreviewMeta','govPreviewWarn');
    setupPreview('supporting_doc_photo','supportPreview','supportPreviewImg','supportPreviewMeta','supportPreviewWarn');
})();
</script>
</body>
</html>
