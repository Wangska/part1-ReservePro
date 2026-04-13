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
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .field-error {
            display: block;
            color: #fca5a5;
            font-size: 13px;
            margin-top: 6px;
        }

        .field-error:empty {
            display: none;
        }

        input.invalid,
        select.invalid {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.3) !important;
        }

        .host-verification-page .host-main {
            padding-top: 28px;
            padding-bottom: 40px;
        }

        .host-verification-page .host-sidebar {
            background:
                radial-gradient(circle at top left, rgba(212, 165, 116, 0.20), transparent 38%),
                radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.12), transparent 30%),
                linear-gradient(180deg, #121212 0%, #0d0d0d 100%);
            border-right: 1px solid rgba(212, 165, 116, 0.18);
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.02);
        }

        .host-verification-page .sidebar-header {
            padding-bottom: 20px;
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .verify-sidebar-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 22px 18px 18px;
        }

        .verify-sidebar-hero,
        .verify-progress-card,
        .verify-sidebar-note {
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.04);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
        }

        .verify-sidebar-hero {
            padding: 18px;
            background:
                linear-gradient(145deg, rgba(212, 165, 116, 0.18), rgba(255, 255, 255, 0.03)),
                rgba(255, 255, 255, 0.04);
        }

        .verify-sidebar-hero::after,
        .verify-progress-card::after,
        .verify-sidebar-note::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), transparent 36%);
            pointer-events: none;
        }

        .verify-sidebar-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 11px;
            border-radius: 999px;
            background: rgba(15, 15, 15, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #f3d8af;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .verify-sidebar-hero h2,
        .verify-progress-card h3,
        .verify-sidebar-note h3 {
            margin: 0 0 8px;
            color: #ffffff;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.3;
        }

        .verify-sidebar-hero p,
        .verify-progress-card p,
        .verify-sidebar-note p {
            margin: 0;
            color: #bebebe;
            font-size: 14px;
            line-height: 1.65;
        }

        .verify-progress-card {
            padding: 18px;
        }

        .verify-progress-label {
            display: block;
            margin-bottom: 10px;
            color: #f0cf9d;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .verify-progress-meter {
            height: 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            overflow: hidden;
            margin-bottom: 10px;
        }

        .verify-progress-meter span {
            display: block;
            width: 72%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #d4a574, #f0cf9d 55%, #7dd3fc);
            box-shadow: 0 0 18px rgba(212, 165, 116, 0.28);
        }

        .verify-progress-caption {
            color: #d8d8d8;
            font-size: 13px;
            line-height: 1.6;
        }

        .verify-sidebar-steps {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .verify-step-card {
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 12px;
            align-items: start;
            padding: 14px 14px 14px 12px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(255, 255, 255, 0.03);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.16);
        }

        .verify-step-index {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.95), rgba(184, 147, 95, 0.95));
            color: #0f0f0f;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.06em;
        }

        .verify-step-card strong {
            display: block;
            margin-bottom: 4px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
        }

        .verify-step-card span {
            display: block;
            color: #adadad;
            font-size: 13px;
            line-height: 1.6;
        }

        .verify-sidebar-note {
            margin-top: auto;
            padding: 18px;
        }

        .verify-sidebar-note h3 {
            font-size: 16px;
        }

        .host-verification-page .sidebar-footer {
            padding-top: 18px;
            border-top-color: rgba(255, 255, 255, 0.08);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), transparent);
        }

        .host-verification-page .user-profile {
            padding: 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 14px;
        }

        .host-verification-page .user-avatar {
            box-shadow: 0 10px 24px rgba(212, 165, 116, 0.22);
        }

        .host-verification-page .btn-logout {
            border-color: rgba(212, 165, 116, 0.35);
            background: rgba(255, 255, 255, 0.03);
            color: #f5f5f5;
        }

        .host-verification-page .btn-logout:hover {
            color: #0f0f0f;
        }

        .verify-page-header {
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 28px;
        }

        .verify-hero-copy {
            max-width: 720px;
        }

        .verify-eyebrow {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(212, 165, 116, 0.12);
            border: 1px solid rgba(212, 165, 116, 0.2);
            color: #e7c89a;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .verify-page-header h1 {
            margin-bottom: 10px;
        }

        .verify-lead {
            max-width: 640px;
            font-size: 15px;
            line-height: 1.65;
            color: #b8b8b8;
        }

        .verify-header-actions {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-left: auto;
        }

        .verify-summary-chip {
            min-width: 220px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(212, 165, 116, 0.18);
            background: linear-gradient(145deg, rgba(34, 34, 34, 0.88), rgba(20, 20, 20, 0.92));
            box-shadow: 0 20px 44px rgba(0, 0, 0, 0.24);
        }

        .verify-summary-chip strong {
            display: block;
            margin-bottom: 6px;
            color: #ffffff;
            font-size: 14px;
        }

        .verify-summary-chip span {
            display: block;
            color: #a8a8a8;
            font-size: 13px;
            line-height: 1.6;
        }

        .verify-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.85fr);
            gap: 24px;
            align-items: start;
        }

        .verify-panel,
        .verify-side-panel {
            border-radius: 24px;
            border: 1px solid rgba(212, 165, 116, 0.16);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.015)),
                linear-gradient(160deg, rgba(24, 24, 24, 0.95), rgba(14, 14, 14, 0.97));
            box-shadow: 0 28px 80px rgba(0, 0, 0, 0.32);
        }

        .verify-panel {
            padding: 28px;
        }

        .verify-side-panel {
            padding: 22px;
            position: sticky;
            top: 28px;
        }

        .verify-side-panel h3,
        .verify-panel h3 {
            margin: 0 0 10px;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
        }

        .verify-side-panel p,
        .verify-panel p {
            color: #b7b7b7;
            line-height: 1.65;
        }

        .verify-meta-list,
        .verify-checklist,
        .verify-notes {
            list-style: none;
            padding: 0;
            margin: 18px 0 0;
        }

        .verify-meta-list li,
        .verify-checklist li,
        .verify-notes li {
            position: relative;
            padding-left: 18px;
            color: #d7d7d7;
            font-size: 14px;
            line-height: 1.6;
        }

        .verify-meta-list li + li,
        .verify-checklist li + li,
        .verify-notes li + li {
            margin-top: 10px;
        }

        .verify-meta-list li::before,
        .verify-checklist li::before,
        .verify-notes li::before {
            content: '';
            position: absolute;
            top: 9px;
            left: 0;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: linear-gradient(135deg, #d4a574, #b8935f);
        }

        .verification-alert {
            margin-bottom: 20px;
            padding: 18px 20px;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(15, 23, 42, 0.38);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
        }

        .verification-alert h4,
        .verification-alert h3 {
            margin: 0 0 8px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
        }

        .verification-alert p,
        .verification-alert li {
            color: #d6d6d6;
            line-height: 1.65;
        }

        .verification-alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .verification-alert-success {
            border-color: rgba(45, 212, 191, 0.28);
            background: linear-gradient(145deg, rgba(15, 118, 110, 0.18), rgba(15, 23, 42, 0.44));
        }

        .verification-alert-warning {
            border-color: rgba(245, 158, 11, 0.28);
            background: linear-gradient(145deg, rgba(146, 64, 14, 0.18), rgba(15, 23, 42, 0.44));
        }

        .verification-alert-error {
            border-color: rgba(239, 68, 68, 0.28);
            background: linear-gradient(145deg, rgba(127, 29, 29, 0.18), rgba(15, 23, 42, 0.44));
        }

        .verify-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .verify-form .form-section {
            margin: 0;
            padding: 22px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.025);
        }

        .verify-section-heading {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 18px;
        }

        .section-step {
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #0f0f0f;
            background: linear-gradient(135deg, #d4a574, #b8935f);
        }

        .verify-form .section-title {
            margin: 0 0 6px;
            color: #ffffff;
            font-size: 19px;
            font-weight: 700;
        }

        .section-copy,
        .helper-text {
            margin: 0;
            color: #a8a8a8;
            font-size: 13px;
            line-height: 1.65;
        }

        .verify-form .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .verify-form .form-group + .form-group,
        .verify-form .form-row + .form-group,
        .verify-form .form-row + .helper-text {
            margin-top: 0;
        }

        .verify-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .verify-form label {
            color: #ececec;
            font-size: 14px;
            font-weight: 600;
        }

        .verify-form input,
        .verify-form select {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            color: #ffffff;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .verify-form input::placeholder {
            color: #909090;
        }

        .verify-form input:focus,
        .verify-form select:focus {
            outline: none;
            border-color: rgba(212, 165, 116, 0.55);
            box-shadow: 0 0 0 4px rgba(212, 165, 116, 0.12);
            background: rgba(255, 255, 255, 0.055);
        }

        .verify-form option {
            color: #111827;
        }

        .verify-submit-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 6px;
            padding-top: 4px;
        }

        .verify-submit-note {
            max-width: 420px;
            color: #9f9f9f;
            font-size: 13px;
            line-height: 1.6;
        }

        .verify-form .btn-primary {
            border: none;
            min-width: 210px;
            padding: 14px 24px;
            border-radius: 999px;
            box-shadow: 0 14px 32px rgba(184, 147, 95, 0.26);
            cursor: pointer;
        }

        .verify-status-shell {
            max-width: 900px;
        }

        .verify-status-card {
            padding: 28px;
            border-radius: 24px;
            border: 1px solid rgba(212, 165, 116, 0.18);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.015)),
                linear-gradient(160deg, rgba(24, 24, 24, 0.95), rgba(14, 14, 14, 0.97));
            box-shadow: 0 28px 80px rgba(0, 0, 0, 0.32);
        }

        .verify-status-card h3 {
            margin: 0 0 10px;
            color: #ffffff;
            font-size: 24px;
            font-weight: 700;
        }

        .verify-status-card p {
            margin: 0;
            color: #d0d0d0;
            line-height: 1.7;
        }

        .verify-status-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 22px;
        }

        .verify-status-meta .meta-card {
            padding: 16px 18px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.03);
        }

        .verify-status-meta .meta-label {
            display: block;
            margin-bottom: 6px;
            color: #999999;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .verify-status-meta .meta-value {
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
        }

        body.light-mode .verify-eyebrow {
            background: rgba(212, 165, 116, 0.14);
            color: #8c6740;
            border-color: rgba(184, 147, 95, 0.25);
        }

        body.light-mode .host-verification-page .host-sidebar {
            background:
                radial-gradient(circle at top left, rgba(212, 165, 116, 0.18), transparent 36%),
                radial-gradient(circle at bottom right, rgba(96, 165, 250, 0.14), transparent 30%),
                linear-gradient(180deg, #fffdf9 0%, #f8fafc 100%);
            border-right-color: rgba(15, 23, 42, 0.08);
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.4);
        }

        body.light-mode .host-verification-page .sidebar-header,
        body.light-mode .host-verification-page .sidebar-footer {
            border-color: rgba(15, 23, 42, 0.08);
        }

        body.light-mode .verify-sidebar-hero,
        body.light-mode .verify-progress-card,
        body.light-mode .verify-sidebar-note,
        body.light-mode .verify-step-card,
        body.light-mode .host-verification-page .user-profile {
            background: rgba(255, 255, 255, 0.72);
            border-color: rgba(15, 23, 42, 0.06);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        body.light-mode .verify-sidebar-badge {
            background: rgba(15, 23, 42, 0.04);
            border-color: rgba(15, 23, 42, 0.06);
            color: #8c6740;
        }

        body.light-mode .verify-sidebar-hero h2,
        body.light-mode .verify-progress-card h3,
        body.light-mode .verify-sidebar-note h3,
        body.light-mode .verify-step-card strong,
        body.light-mode .host-verification-page .user-name {
            color: #0f172a;
        }

        body.light-mode .verify-sidebar-hero p,
        body.light-mode .verify-progress-card p,
        body.light-mode .verify-progress-caption,
        body.light-mode .verify-sidebar-note p,
        body.light-mode .verify-step-card span,
        body.light-mode .host-verification-page .user-role {
            color: #5b6472;
        }

        body.light-mode .verify-progress-label {
            color: #8c6740;
        }

        body.light-mode .host-verification-page .btn-logout {
            background: #ffffff;
            border-color: rgba(15, 23, 42, 0.08);
            color: #1f2937;
        }

        body.light-mode .verify-lead,
        body.light-mode .section-copy,
        body.light-mode .helper-text,
        body.light-mode .verify-summary-chip span,
        body.light-mode .verify-submit-note,
        body.light-mode .verify-side-panel p,
        body.light-mode .verify-panel p,
        body.light-mode .verification-alert p,
        body.light-mode .verification-alert li,
        body.light-mode .verify-status-card p {
            color: #5b6472;
        }

        body.light-mode .verify-summary-chip,
        body.light-mode .verify-panel,
        body.light-mode .verify-side-panel,
        body.light-mode .verify-status-card {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.98)),
                #ffffff;
            border-color: rgba(15, 23, 42, 0.08);
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
        }

        body.light-mode .verify-summary-chip strong,
        body.light-mode .verify-side-panel h3,
        body.light-mode .verify-panel h3,
        body.light-mode .verify-form .section-title,
        body.light-mode .verify-form label,
        body.light-mode .verify-status-card h3,
        body.light-mode .verification-alert h3,
        body.light-mode .verification-alert h4 {
            color: #0f172a;
        }

        body.light-mode .verify-meta-list li,
        body.light-mode .verify-checklist li,
        body.light-mode .verify-notes li {
            color: #334155;
        }

        body.light-mode .verification-alert {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(148, 163, 184, 0.18);
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.06);
        }

        body.light-mode .verification-alert-success {
            background: linear-gradient(145deg, rgba(236, 253, 245, 0.96), rgba(255, 255, 255, 0.98));
            border-color: rgba(16, 185, 129, 0.22);
        }

        body.light-mode .verification-alert-warning {
            background: linear-gradient(145deg, rgba(255, 251, 235, 0.96), rgba(255, 255, 255, 0.98));
            border-color: rgba(245, 158, 11, 0.22);
        }

        body.light-mode .verification-alert-error {
            background: linear-gradient(145deg, rgba(254, 242, 242, 0.96), rgba(255, 255, 255, 0.98));
            border-color: rgba(239, 68, 68, 0.2);
        }

        body.light-mode .verify-form .form-section,
        body.light-mode .verify-status-meta .meta-card {
            background: rgba(248, 250, 252, 0.86);
            border-color: rgba(15, 23, 42, 0.06);
        }

        body.light-mode .verify-form input,
        body.light-mode .verify-form select {
            background: #ffffff;
            color: #0f172a;
            border-color: rgba(15, 23, 42, 0.1);
        }

        body.light-mode .verify-form input::placeholder {
            color: #94a3b8;
        }

        body.light-mode .verify-status-meta .meta-label {
            color: #64748b;
        }

        body.light-mode .verify-status-meta .meta-value {
            color: #0f172a;
        }

        @media (max-width: 1100px) {
            .verify-layout {
                grid-template-columns: 1fr;
            }

            .verify-side-panel {
                position: static;
            }
        }

        @media (max-width: 820px) {
            .verify-page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .verify-header-actions {
                width: 100%;
                justify-content: space-between;
                margin-left: 0;
            }

            .verify-summary-chip {
                min-width: 0;
                flex: 1;
            }

            .verify-form .form-row,
            .verify-status-meta {
                grid-template-columns: 1fr;
            }

            .verify-submit-row {
                flex-direction: column;
                align-items: stretch;
            }

            .verify-form .btn-primary {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .host-verification-page .host-layout {
                flex-direction: column;
            }

            .host-verification-page .host-sidebar {
                position: static;
                width: 100%;
                height: auto;
                transform: none;
                border-right: none;
                border-bottom: 1px solid rgba(212, 165, 116, 0.16);
            }

            body.light-mode .host-verification-page .host-sidebar {
                border-bottom-color: rgba(15, 23, 42, 0.08);
            }

            .host-verification-page .sidebar-header,
            .host-verification-page .verify-sidebar-body,
            .host-verification-page .sidebar-footer {
                padding-left: 20px;
                padding-right: 20px;
            }
        }
    </style>
</head>
<body class="host-verification-page">
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <div class="verify-sidebar-body">
                <div class="verify-sidebar-hero">
                    <span class="verify-sidebar-badge">Secure Review</span>
                    <h2>Become a verified host</h2>
                    <p>Finish a few trust checks so your hosting account can go live with fewer delays.</p>
                </div>

                <div class="verify-progress-card">
                    <span class="verify-progress-label">Verification flow</span>
                    <div class="verify-progress-meter"><span></span></div>
                    <p class="verify-progress-caption">Identity, property permission, and payout setup all in one review.</p>
                </div>

                <div class="verify-sidebar-steps">
                    <div class="verify-step-card">
                        <span class="verify-step-index">01</span>
                        <div>
                            <strong>Confirm identity</strong>
                            <span>Choose the government ID you want us to use for verification.</span>
                        </div>
                    </div>
                    <div class="verify-step-card">
                        <span class="verify-step-index">02</span>
                        <div>
                            <strong>Confirm hosting rights</strong>
                            <span>Show that you own the property or have permission to manage it.</span>
                        </div>
                    </div>
                    <div class="verify-step-card">
                        <span class="verify-step-index">03</span>
                        <div>
                            <strong>Confirm payout details</strong>
                            <span>Add the account that should receive booking payouts after approval.</span>
                        </div>
                    </div>
                </div>

                <div class="verify-sidebar-note">
                    <h3>Typical review time</h3>
                    <p>Most verification checks are completed after the team confirms the information you submit. Clear, accurate details help speed that up.</p>
                </div>
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
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">
            <div class="host-header verify-page-header">
                <div class="verify-hero-copy">
                    <span class="verify-eyebrow">Host Onboarding</span>
                    <h1>Complete Host Verification</h1>
                    <p class="verify-lead">Share the details we need to confirm your identity, your right to host, and the account where payouts should be sent. The review is straightforward, and once approved you can start listing properties.</p>
                </div>
                <div class="verify-header-actions">
                    <div class="verify-summary-chip">
                        <strong>What happens next</strong>
                        <span>We review your submission, verify the details, and unlock your host dashboard once everything checks out.</span>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="verification-alert verification-alert-error">
                    <h4>Please fix the following issues:</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($just_submitted): ?>
                <div class="verification-alert verification-alert-success">
                    <h3>Submission received</h3>
                    <p>Your verification details were sent successfully. We are now reviewing your account and will update your status once the review is complete.</p>
                </div>
            <?php endif; ?>

            <?php if ($verification_pending): ?>
                <div class="verify-status-shell">
                    <div class="verify-status-card">
                        <h3>Verification under review</h3>
                        <p>Your host profile is currently being checked by the ReservePro team. Once approved, you will be able to access the host dashboard and start publishing properties.</p>
                        <div class="verify-status-meta">
                            <div class="meta-card">
                                <span class="meta-label">Current status</span>
                                <span class="meta-value">Under review</span>
                            </div>
                            <div class="meta-card">
                                <span class="meta-label">Expected next step</span>
                                <span class="meta-value">Approval update by email</span>
                            </div>
                            <?php if ($submitted_at): ?>
                            <div class="meta-card">
                                <span class="meta-label">Submitted on</span>
                                <span class="meta-value"><?php echo date('F j, Y \a\t g:i A', strtotime($submitted_at)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($verification_rejected && !$verification_pending): ?>
                <div class="verification-alert verification-alert-error">
                    <h3>Update required</h3>
                    <p>Your previous verification was not approved. Review your details, correct any issues, and send the form again for another review.</p>
                </div>
            <?php endif; ?>

            <?php if (!$verification_pending): ?>
            <div class="verify-layout">
                <form method="POST" action="verify-account.php" class="property-form verify-form verify-panel">
                    <div class="form-section">
                        <div class="verify-section-heading">
                            <span class="section-step">01</span>
                            <div>
                                <h2 class="section-title">Personal identification</h2>
                                <p class="section-copy">Tell us which government ID you are using so we can confirm the host identity attached to this account.</p>
                            </div>
                        </div>
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
                                <label for="gov_id_number">ID number</label>
                                <input type="text" id="gov_id_number" name="gov_id_number" placeholder="ID reference number" data-number-field>
                                <span class="field-error" id="gov_id_number_error" role="alert"></span>
                            </div>
                        </div>
                        <p class="helper-text">Image uploads can be added later. For now, the ID type and reference number are enough for review.</p>
                    </div>

                    <div class="form-section">
                        <div class="verify-section-heading">
                            <span class="section-step">02</span>
                            <div>
                                <h2 class="section-title">Hosting permission</h2>
                                <p class="section-copy">Show the type of document that proves you own the property or have permission to manage it as a host.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ownership_proof_type">Ownership or permission proof *</label>
                                <select id="ownership_proof_type" name="ownership_proof_type" required>
                                    <option value="">Select proof type</option>
                                    <option value="Land title / Ownership certificate">Land title / Ownership certificate</option>
                                    <option value="Lease agreement">Lease agreement</option>
                                    <option value="Written landlord permission">Written landlord permission</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ownership_reference">Reference or notes</label>
                                <input type="text" id="ownership_reference" name="ownership_reference" placeholder="Document reference or notes">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="verify-section-heading">
                            <span class="section-step">03</span>
                            <div>
                                <h2 class="section-title">Business documents</h2>
                                <p class="section-copy">These fields are optional, but adding them can help complete your verification profile faster when they apply to your business.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="business_registration">Business registration certificate</label>
                                <input type="text" id="business_registration" name="business_registration" placeholder="Business registration number" data-number-field>
                                <span class="field-error" id="business_registration_error" role="alert"></span>
                            </div>
                            <div class="form-group">
                                <label for="tax_id">Tax Identification Number</label>
                                <input type="text" id="tax_id" name="tax_id" placeholder="TIN" data-number-field>
                                <span class="field-error" id="tax_id_error" role="alert"></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="tourism_license">Local tourism license</label>
                            <input type="text" id="tourism_license" name="tourism_license" placeholder="Tourism license number, if available" data-number-field>
                            <span class="field-error" id="tourism_license_error" role="alert"></span>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="verify-section-heading">
                            <span class="section-step">04</span>
                            <div>
                                <h2 class="section-title">Payout account details</h2>
                                <p class="section-copy">Add the bank account where verified bookings and payouts should be sent once your host account goes live.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bank_name">Bank name *</label>
                                <input type="text" id="bank_name" name="bank_name" required placeholder="Bank name">
                            </div>
                            <div class="form-group">
                                <label for="bank_account_name">Account holder name *</label>
                                <input type="text" id="bank_account_name" name="bank_account_name" required placeholder="Name on account">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="bank_account_number">Account number *</label>
                            <input type="text" id="bank_account_number" name="bank_account_number" required placeholder="Account number" data-number-field data-required-number>
                            <span class="field-error" id="bank_account_number_error" role="alert"></span>
                        </div>
                        <p class="helper-text">Use an account that belongs to you or your registered business to avoid payout delays.</p>
                    </div>

                    <div class="verify-submit-row">
                        <p class="verify-submit-note">By submitting this form, you confirm that the information provided is accurate and can be reviewed by the ReservePro team.</p>
                        <button type="submit" class="btn-primary">Submit for approval</button>
                    </div>
                </form>

                <aside class="verify-side-panel">
                    <h3>Before you submit</h3>
                    <p>Use the checklist below to make sure the review can move forward without delays.</p>
                    <ul class="verify-checklist">
                        <li>Choose the document type that best matches your legal records.</li>
                        <li>Enter reference numbers with digits only. Spaces and hyphens are allowed.</li>
                        <li>Use a payout account that can receive booking payments without mismatch issues.</li>
                    </ul>

                    <h3 style="margin-top: 24px;">What we review</h3>
                    <ul class="verify-meta-list">
                        <li>Your identity as the account owner.</li>
                        <li>Your ownership or permission to host the property.</li>
                        <li>Your payout details for future host earnings.</li>
                    </ul>

                    <h3 style="margin-top: 24px;">Helpful note</h3>
                    <ul class="verify-notes">
                        <li>You can resubmit corrected information if your earlier review was not approved.</li>
                        <li>Once approved, you will be redirected to the host dashboard automatically on your next visit.</li>
                    </ul>
                </aside>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
    <script>
    (function() {
        var numberFields = document.querySelectorAll('[data-number-field]');
        var form = document.querySelector('form.property-form');
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
        if (!form) return;
        form.addEventListener('submit', function(e) {
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

