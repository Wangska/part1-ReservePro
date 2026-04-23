<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

// Latest host_documents per user (if any)
$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.role,
        u.email_verified,
        u.host_verified,
        u.host_verification_status,
        u.created_at,
        hd.id AS host_doc_id,
        hd.created_at AS host_doc_created_at,
        hd.verification_status AS host_doc_status,
        hd.id_full_name,
        hd.gov_id_type,
        hd.gov_id_number,
        hd.gov_id_photo_path,
        hd.ownership_proof_type,
        hd.ownership_reference,
        hd.ownership_doc_photo_path,
        hd.bank_name,
        hd.bank_account_name,
        hd.bank_account_number
    FROM users u
    LEFT JOIN (
        SELECT h1.*
        FROM host_documents h1
        JOIN (
            SELECT user_id, MAX(id) AS max_id
            FROM host_documents
            GROUP BY user_id
        ) latest ON latest.user_id = h1.user_id AND latest.max_id = h1.id
    ) hd ON hd.user_id = u.id
    ORDER BY u.created_at DESC
";
$result = $conn->query($sql);
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

$totalUsers    = count($rows);
$totalHosts    = count(array_filter($rows, fn($r) => ($r['role'] ?? '') === 'host'));
$totalGuests   = count(array_filter($rows, fn($r) => ($r['role'] ?? '') === 'guest'));
$emailVerified = count(array_filter($rows, fn($r) => !empty($r['email_verified'])));
$pendingDocs   = count(array_filter($rows, fn($r) => ($r['host_doc_status'] ?? '') === 'pending'));

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>User Submissions - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        /* === Submissions Page === */
        .admin-submissions-page .host-main {
            background: linear-gradient(180deg, rgba(15,23,42,0.18) 0%, rgba(15,15,15,0) 260px);
        }

        /* Hero */
        .sub-hero {
            display: flex;
            align-items: stretch;
            gap: 20px;
            background: linear-gradient(135deg, rgba(17,24,39,0.96), rgba(30,41,59,0.88));
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 24px;
            padding: 28px 30px;
            margin-bottom: 28px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.24);
        }
        .sub-hero-content { flex: 1; }
        .sub-eyebrow {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 13px; margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(212,165,116,0.14); color: #F3D9B4;
            font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
        }
        .sub-hero h1 {
            margin: 0 0 10px;
            color: #fff !important;
            font-size: 32px;
            font-weight: 700;
        }
        .sub-hero .subtitle { color: #CBD5E1; font-size: 14px; line-height: 1.6; margin: 0; max-width: 560px; }
        .sub-summary-card {
            min-width: 180px; padding: 22px; border-radius: 20px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
            display: flex; flex-direction: column; justify-content: center; gap: 6px;
        }
        .sub-summary-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #94A3B8; font-weight: 700; }
        .sub-summary-card strong { font-size: 38px; line-height: 1; color: #FFFFFF; }
        .sub-summary-desc { font-size: 13px; color: #CBD5E1; }

        /* Stat cards */
        .sub-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }
        .sub-stat {
            padding: 20px 22px;
            border-radius: 18px;
            background: rgba(17,24,39,0.86);
            border: 1px solid rgba(148,163,184,0.16);
            box-shadow: 0 12px 24px rgba(0,0,0,0.14);
            display: flex; align-items: center; gap: 16px;
            transition: transform 0.18s, border-color 0.18s;
        }
        .sub-stat:hover { transform: translateY(-3px); border-color: rgba(212,165,116,0.3); }
        .sub-stat-icon {
            width: 48px; height: 48px; flex-shrink: 0;
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            font-size: 18px; background: rgba(255,255,255,0.05);
            border: 1px solid rgba(148,163,184,0.14);
        }
        .sub-stat-label { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #94A3B8; margin-bottom: 4px; }
        .sub-stat-value { font-size: 26px; font-weight: 800; color: #fff; line-height: 1; }

        /* Table card */
        .sub-table-card {
            background: rgba(17,24,39,0.86);
            border: 1px solid rgba(148,163,184,0.16);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 36px rgba(0,0,0,0.18);
        }
        .sub-table-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            padding: 24px 24px 18px;
            border-bottom: 1px solid rgba(148,163,184,0.12);
        }
        .sub-table-header h2 { margin: 0 0 6px; color: #FFFFFF !important; }


        /* Table */
        .sub-table-wrap { overflow-x: auto; width: 100%; }
        .sub-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sub-table thead {
            background: rgba(255, 255, 255, 0.04);
        }
        .sub-table th {
            padding: 14px 18px;
            text-align: left;
            color: #94A3B8;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(148,163,184,0.12);
        }
        .sub-table td {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(148,163,184,0.1);
            vertical-align: middle;
            color: #E2E8F0;
            text-align: center;
        }
        .sub-table td:first-child {
            text-align: left;
        }
        .sub-col-user    { width: 26%; }
        .sub-col-role    { width: 9%; }
        .sub-col-email   { width: 12%; }
        .sub-col-host    { width: 11%; }
        .sub-col-doc     { width: 14%; }
        .sub-col-documents { width: 14%; }
        .sub-col-joined  { width: 12%; }
        .sub-col-actions { width: 16%; }
        .sub-table tbody tr { transition: background 0.15s; }
        .sub-table tbody tr:hover { background: rgba(255,255,255,0.04); }
        .sub-table tbody tr:last-child td { border-bottom: none; }

        /* User cell */
        .sub-user-cell { display: flex; align-items: center; gap: 11px; }
        .sub-avatar {
            width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800; color: #0F0F0F;
            background: linear-gradient(135deg, #D4A574, #B8935F);
        }
        .sub-user-name { color: #F1F5F9 !important; font-weight: 700; font-size: 14px; }
        .sub-user-email { color: #B8B8B8 !important; font-size: 13px; font-weight: 600; margin-top: 2px; }

        /* Role badge */
        .sub-role {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 8px;
            font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .sub-role-host     { background: rgba(167,243,208,0.1); color: #A7F3D0; border: 1px solid rgba(167,243,208,0.2); }
        .sub-role-guest    { background: rgba(186,230,253,0.1); color: #BAE6FD; border: 1px solid rgba(186,230,253,0.2); }
        .sub-role-admin    { background: rgba(252,165,165,0.1); color: #FCA5A5; border: 1px solid rgba(252,165,165,0.2); }

        /* Verified pill */
        .sub-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 9px; border-radius: 999px;
            font-size: 11px; font-weight: 700;
        }
        .sub-pill-yes { background: rgba(99,102,241,0.1); color: #C7D2FE; border: 1px solid rgba(99,102,241,0.22); }
        .sub-pill-no  { background: rgba(148,163,184,0.08); color: #64748B; border: 1px solid rgba(148,163,184,0.14); }

        /* Host status */
        .sub-hstatus { color: #F1F5F9 !important; font-size: 13px; font-weight: 700; text-transform: capitalize; }
        .sub-hstatus-none { color: #475569 !important; }

        /* Doc status badge */
        .sub-doc-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 11px; border-radius: 999px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.02em;
            border: 1px solid transparent;
        }
        .sub-doc-badge-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .sub-doc-badge-pending  { background: rgba(234,179,8,0.1);  color: #FDE047 !important; border-color: rgba(234,179,8,0.22); }
        .sub-doc-badge-pending  .sub-doc-badge-dot { background: #EAB308; }
        .sub-doc-badge-approved { background: rgba(34,197,94,0.1);  color: #86EFAC !important; border-color: rgba(34,197,94,0.22); }
        .sub-doc-badge-approved .sub-doc-badge-dot { background: #22C55E; }
        .sub-doc-badge-rejected { background: rgba(244,63,94,0.1);  color: #FDA4AF !important; border-color: rgba(244,63,94,0.22); }
        .sub-doc-badge-rejected .sub-doc-badge-dot { background: #F43F5E; }

        /* Joined date */
        .sub-date { color: #CBD5E1 !important; font-size: 13px; font-weight: 600; }

        /* Action btn */
        .sub-action-btn {
            padding: 8px 14px;
            background: transparent;
            color: #D4A574;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
            white-space: nowrap;
        }
        .sub-action-btn:hover {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            box-shadow: 0 8px 20px rgba(212,165,116,0.22);
        }

        /* Proof btn  */
        .sub-proof-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 13px; border-radius: 10px;
            background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.22);
            color: #C7D2FE !important; text-decoration: none;
            font-weight: 700; font-size: 12px; cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            white-space: nowrap;
        }
        .sub-proof-btn:hover { background: rgba(99,102,241,0.16); border-color: rgba(99,102,241,0.38); }

        /* Empty */
        .sub-empty { padding: 64px 36px; text-align: center; }
        .sub-empty-icon {
            width: 72px; height: 72px; margin: 0 auto 18px; border-radius: 20px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 28px; color: #FDE68A;
            background: rgba(234,179,8,0.1); border: 1px solid rgba(234,179,8,0.2);
        }
        .sub-empty h3 { color: #F1F5F9 !important; margin: 0 0 8px; font-size: 18px; }
        .sub-empty p  { color: #475569 !important; font-size: 14px; margin: 0; }

        /* Modal */
        .img-modal-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,0.75);
            display: none; align-items: center; justify-content: center;
            padding: 24px; z-index: 9999; backdrop-filter: blur(4px);
        }
        .img-modal-backdrop.open { display: flex; }
        .img-modal {
            width: min(900px, 94vw); max-height: 90vh;
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.12);
            background: rgba(17,24,39,0.96); overflow: hidden;
            box-shadow: 0 40px 100px rgba(0,0,0,0.7);
        }
        .img-modal-header {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .img-modal-title {
            flex: 1; min-width: 0; text-align: center;
            color: #fff; font-weight: 800; font-size: 14px;
        }
        .img-modal-back,
        .img-modal-close {
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.06);
            color: #E2E8F0; border-radius: 10px; padding: 7px 12px;
            cursor: pointer; font-weight: 700; font-size: 12px;
            transition: background 0.15s;
            flex-shrink: 0;
        }
        .img-modal-back:hover,
        .img-modal-close:hover { background: rgba(255,255,255,0.1); }
        .img-modal-body {
            padding: 16px; display: grid; place-items: center; background: rgba(0,0,0,0.2);
        }
        .img-modal-body img {
            max-width: 100%; max-height: calc(90vh - 120px);
            border-radius: 14px; border: 1px solid rgba(255,255,255,0.08);
        }

        /* Light mode */
        body.light-mode.admin-submissions-page .host-main { background: linear-gradient(180deg,rgba(248,250,252,.9) 0%,rgba(248,250,252,0) 260px); }
        body.light-mode .sub-hero { background:#fff; border-color:rgba(15,23,42,.08); box-shadow:0 16px 32px rgba(15,23,42,.08); }
        body.light-mode .sub-hero h1 { color:#0F172A !important; }
        body.light-mode .sub-hero .subtitle { color:#475569; }
        body.light-mode .sub-eyebrow { background:rgba(184,147,95,.12); color:#8B6F47; }
        body.light-mode .sub-summary-card { background:#F8FAFC; border-color:rgba(15,23,42,.08); }
        body.light-mode .sub-summary-label { color:#64748B; }
        body.light-mode .sub-summary-card strong { color:#0F172A; }
        body.light-mode .sub-summary-desc { color:#475569; }
        body.light-mode .sub-table-card { background:#fff; border-color:rgba(15,23,42,.08); box-shadow:0 16px 32px rgba(15,23,42,.08); }
        body.light-mode .sub-table-header h2 { color:#0F172A !important; }

        body.light-mode .sub-table th { color:#64748B !important; background:rgba(15,23,42,.02); border-bottom-color:rgba(15,23,42,.08); }
        body.light-mode .sub-table td { border-bottom-color:rgba(15,23,42,.06); }
        body.light-mode .sub-table tbody tr:hover { background:rgba(15,23,42,.02); }
        body.light-mode .sub-user-name { color:#0F172A !important; }
        body.light-mode .sub-user-email { color:#64748B !important; }
        body.light-mode .sub-date { color:#475569 !important; }
        body.light-mode .sub-hstatus { color:#0F172A !important; }
        body.light-mode .sub-hstatus-none { color:#94A3B8 !important; }
        body.light-mode .sub-empty h3 { color:#0F172A !important; }
        body.light-mode .sub-empty p { color:#64748B !important; }

        body.light-mode .sub-stat { background:#fff; border-color:rgba(15,23,42,.08); box-shadow:0 8px 16px rgba(15,23,42,.06); }
        body.light-mode .sub-stat-icon { background:#F8FAFC; border-color:rgba(15,23,42,.08); }
        body.light-mode .sub-stat-value { color:#0F172A; }
        body.light-mode .sub-stat-label { color:#64748B; }

        /* Animated sub-doc-link button */
        .sub-doc-link {
            transition: background 0.18s, color 0.18s, box-shadow 0.18s, border-color 0.18s, transform 0.18s !important;
        }
        .sub-doc-link:hover {
            background: linear-gradient(135deg, #B8935F, #D4A574) !important;
            color: #0F0F0F !important;
            border-color: #D4A574 !important;
            box-shadow: 0 6px 24px rgba(212,165,116,0.28) !important;
            transform: scale(1.08) !important;
        }
        .sub-doc-link:active {
            transform: scale(0.96) !important;
            box-shadow: 0 1px 2px rgba(212,165,116,0.10) !important;
        }

        @media (max-width: 1100px) { .sub-stats { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 900px) {
            .sub-hero { flex-direction: column; }
            .sub-summary-card { min-width: 0; }
            .sub-table { table-layout: auto; }
        }
        /* ── Notification bell dropdown (copied from refunds.php for pixel-perfect match) ── */
        .admin-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }
        .adm-notif-wrap {
            position: relative;
        }
        .adm-notif-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(255, 255, 255, 0.06);
            color: #A3A3A3;
            font-size: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s;
        }
        .adm-notif-btn:hover {
            background: rgba(255, 255, 255, 0.11);
            border-color: rgba(212, 165, 116, 0.4);
        }
        .adm-notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: #EF4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            pointer-events: none;
        }
        .adm-notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 340px;
            max-width: calc(100vw - 32px);
            border-radius: 18px;
            background: rgba(17, 24, 39, 0.97);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.42);
            z-index: 9999;
            overflow: hidden;
        }
        .adm-notif-dropdown-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 14px 11px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }
        .adm-notif-dropdown-title {
            font-size: 13px;
            font-weight: 900;
            color: #F1F5F9;
            letter-spacing: -0.01em;
        }
        .adm-notif-markall {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 10px;
            cursor: pointer;
        }
        .adm-notif-markall:hover {
            background: rgba(255, 255, 255, 0.11);
        }
        .adm-notif-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px;
            max-height: 340px;
            overflow-y: auto;
        }
        .adm-notif-item {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            padding: 9px 10px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.12);
            background: rgba(255, 255, 255, 0.03);
        }
        .adm-notif-item.unread {
            border-color: rgba(212, 165, 116, 0.32);
            background: rgba(212, 165, 116, 0.07);
        }
        .adm-notif-item-body {
            flex: 1;
            min-width: 0;
        }
        .adm-notif-item strong {
            display: block;
            color: #F1F5F9;
            font-weight: 800;
            font-size: 12px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .adm-notif-item small {
            display: block;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
        }
        .adm-notif-item-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
            flex-shrink: 0;
        }
        .adm-notif-link {
            color: #FDE68A;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
        }
        .adm-notif-mark {
            border: 0;
            background: transparent;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }
        .adm-notif-empty {
            padding: 14px 10px;
            color: #94A3B8;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        /* Light mode overrides */
        body.light-mode .adm-notif-btn {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.10);
            color: #6B7280;
        }
        body.light-mode .adm-notif-btn:hover {
            background: #F1F5F9;
        }
        body.light-mode .adm-notif-dropdown {
            background: #FFFFFF;
            border-color: rgba(15, 23, 42, 0.10);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
        }
        body.light-mode .adm-notif-dropdown-head {
            border-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .adm-notif-dropdown-title {
            color: #0F172A;
        }
        body.light-mode .adm-notif-markall {
            background: #F8FAFC;
            color: #0F172A;
            border-color: rgba(15, 23, 42, 0.10);
        }
        body.light-mode .adm-notif-item {
            background: #F8FAFC;
            border-color: #E2E8F0;
        }
        body.light-mode .adm-notif-item.unread {
            background: rgba(212, 165, 116, 0.10);
            border-color: rgba(212, 165, 116, 0.40);
        }
        body.light-mode .adm-notif-item strong {
            color: #0F172A;
        }
        body.light-mode .adm-notif-item small {
            color: #475569;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-submissions-page">
<div class="host-layout">
    <aside class="host-sidebar">
        <div class="sidebar-header">
            <a href="../home.php" class="sidebar-brand">
                <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                <span>ReservePro</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                <span>Dashboard</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span>
                <span>Analytics</span>
            </a>
            <a href="refunds.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                <span>Refunds</span>
            </a>
            <a href="host-verifications.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                <span>Host Verifications</span>
            </a>
            <a href="submissions.php" class="nav-item active">
                <span class="nav-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                <span>Submissions</span>
            </a>
            <a href="properties.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                <span>All Properties</span>
            </a>
            <a href="users.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                <span>Users</span>
            </a>
            <a href="bookings.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                <span>All Bookings</span>
            </a>
            <a href="earnings.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                <span>Earnings</span>
            </a>
            <a href="commission.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
                <span>Commission</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>

            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">
        <div>

            <!-- Hero -->
            <div class="sub-hero">
                <div class="sub-hero-content">
                    <h1>Submissions</h1>
                    <p class="subtitle"></p>
                </div>
                <div class="admin-hero-actions">
                    <div class="adm-notif-wrap" id="admNotifWrap">
                        <button class="adm-notif-btn" id="admNotifBtn" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="admNotifDropdown">
                            <i class="fa-solid fa-bell" aria-hidden="true" style="font-size: 17px;"></i>
                            <span class="adm-notif-badge" id="admNotifBadge" hidden></span>
                        </button>
                        <div class="adm-notif-dropdown" id="admNotifDropdown" hidden>
                            <div class="adm-notif-dropdown-head">
                                <span class="adm-notif-dropdown-title">Notifications</span>
                                <button class="adm-notif-markall" id="admNotifMarkAll" type="button">Mark all read</button>
                            </div>
                            <div class="adm-notif-list" id="admNotifList">
                                <div class="adm-notif-empty">Loading&hellip;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="sub-stats">
                <div class="sub-stat">
                    <div class="sub-stat-icon" style="color:#C7D2FE;"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="sub-stat-label">Total Users</div>
                        <div class="sub-stat-value"><?php echo $totalUsers; ?></div>
                    </div>
                </div>
                <div class="sub-stat">
                    <div class="sub-stat-icon" style="color:#A7F3D0;"><i class="fa-solid fa-house-user"></i></div>
                    <div>
                        <div class="sub-stat-label">Hosts</div>
                        <div class="sub-stat-value"><?php echo $totalHosts; ?></div>
                    </div>
                </div>
                <div class="sub-stat">
                    <div class="sub-stat-icon" style="color:#FDE68A;"><i class="fa-solid fa-envelope-circle-check"></i></div>
                    <div>
                        <div class="sub-stat-label">Email Verified</div>
                        <div class="sub-stat-value"><?php echo $emailVerified; ?></div>
                    </div>
                </div>
                <div class="sub-stat">
                    <div class="sub-stat-icon" style="color:#FCD34D;"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <div class="sub-stat-label">Pending Docs</div>
                        <div class="sub-stat-value"><?php echo $pendingDocs; ?></div>
                    </div>
                </div>
            </div>

            <!-- Table card -->
            <div class="sub-table-card">
                <div class="sub-table-header">
                    <h2>User Records</h2>
                </div>

                <?php if (empty($rows)): ?>
                <div class="sub-empty">
                    <div class="sub-empty-icon"><i class="fa-solid fa-folder-open"></i></div>
                    <h3>No users found</h3>
                    <p>Once users register and hosts submit verification, they will appear here.</p>
                </div>
                <?php else: ?>
                <div class="sub-table-wrap">
                    <table class="sub-table">
                        <thead>
                            <tr>
                                <th class="sub-col-user" style="text-align:center;">User</th>
                                <th class="sub-col-role" style="text-align:center;">Role</th>
                                <th class="sub-col-email" style="text-align:center;">Email Verified</th>
                                <th class="sub-col-host" style="text-align:center;">Host Verified</th>
                                <th class="sub-col-doc" style="text-align:center;">Host Doc Status</th>
                                <th class="sub-col-documents" style="text-align:center;">Document</th>
                                <th class="sub-col-joined" style="text-align:center;">Joined</th>
                                <th class="sub-col-actions" style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r):
                            $role     = $r['role'] ?? 'guest';
                            $initials = strtoupper(substr($r['first_name'] ?? 'U', 0, 1) . substr($r['last_name'] ?? 'U', 0, 1));
                            $govImg   = !empty($r['gov_id_photo_path'])        ? '../' . ltrim($r['gov_id_photo_path'], '/')        : '';
                            $ownImg   = !empty($r['ownership_doc_photo_path']) ? '../' . ltrim($r['ownership_doc_photo_path'], '/') : '';
                            $hStatus  = $r['host_verification_status'] ?? '';
                        ?>
                        <tr>
                            <td>
                                <div class="sub-user-cell">
                                    <div class="sub-avatar"><?php echo h($initials); ?></div>
                                    <div>
                                        <div class="sub-user-name"><?php echo h(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></div>
                                        <div class="sub-user-email">ID: <?php echo (int)$r['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="sub-role sub-role-<?php echo h($role); ?>">
                                    <?php echo h($role); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($r['email_verified'])): ?>
                                    <span class="sub-pill sub-pill-yes"><i class="fa-solid fa-circle-check" style="font-size:10px;"></i> Verified</span>
                                <?php else: ?>
                                    <span class="sub-pill sub-pill-no"><i class="fa-solid fa-circle" style="font-size:8px;"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['host_verified'])): ?>
                                    <span class="sub-pill sub-pill-yes"><i class="fa-solid fa-circle-check" style="font-size:10px;"></i> Yes</span>
                                <?php else: ?>
                                    <span class="sub-pill sub-pill-no"><i class="fa-solid fa-circle" style="font-size:8px;"></i> No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $docStatus = $r['host_doc_status'] ?? ''; ?>
                                <?php if (in_array($docStatus, ['pending','approved','rejected'])): ?>
                                    <span class="sub-doc-badge sub-doc-badge-<?php echo $docStatus; ?>">
                                        <span class="sub-doc-badge-dot"></span>
                                        <?php echo ucfirst($docStatus); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#475569;font-size:13px;font-weight:600;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                                    <?php if ($govImg): ?>
                                    <a href="<?php echo h($govImg); ?>" target="_blank" class="sub-doc-link" style="display:inline-block;margin:0 2px 2px 0;padding:4px 10px;border-radius:7px;background:linear-gradient(135deg,#D4A574,#B8935F);color:#212121;font-size:11px;font-weight:700;text-decoration:none;border:2px solid #B8935F;box-shadow:0 2px 8px rgba(212,165,116,0.13);cursor:pointer;transition:background 0.18s,color 0.18s,box-shadow 0.18s,border-color 0.18s;">Gov ID</a>
                                    <?php endif; ?>
                                    <?php if ($ownImg): ?>
                                    <a href="<?php echo h($ownImg); ?>" target="_blank" class="sub-doc-link" style="display:inline-block;margin:0 2px 2px 0;padding:4px 10px;border-radius:7px;background:linear-gradient(135deg,#D4A574,#B8935F);color:#212121;font-size:11px;font-weight:700;text-decoration:none;border:2px solid #B8935F;box-shadow:0 2px 8px rgba(212,165,116,0.13);cursor:pointer;transition:background 0.18s,color 0.18s,box-shadow 0.18s,border-color 0.18s;">Ownership</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="sub-date"><?php echo !empty($r['created_at']) ? date('M j, Y', strtotime($r['created_at'])) : '—'; ?></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                                    <a class="sub-action-btn" href="view-user.php?id=<?php echo (int)$r['id']; ?>">View</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<div class="img-modal-backdrop" id="imgModal">
    <div class="img-modal" role="dialog" aria-modal="true" aria-labelledby="imgModalTitle">
        <div class="img-modal-header">
            <button type="button" class="img-modal-back" id="imgModalBack"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
            <div class="img-modal-title" id="imgModalTitle">Proof</div>
            <button type="button" class="img-modal-close" id="imgModalClose">Close</button>
        </div>
        <div class="img-modal-body">
            <img id="imgModalImg" alt="Proof image">
        </div>
    </div>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
<script>
    (function () {
        var modal = document.getElementById('imgModal');
        var modalImg = document.getElementById('imgModalImg');
        var modalTitle = document.getElementById('imgModalTitle');
        var closeBtn = document.getElementById('imgModalClose');
        var backBtn = document.getElementById('imgModalBack');
        if (!modal || !modalImg || !modalTitle || !closeBtn) return;

        function close() { modal.classList.remove('open'); }
        function open(src, title) {
            if (!src) return;
            modalTitle.textContent = title || 'Proof';
            modalImg.src = src;
            modal.classList.add('open');
        }
        document.querySelectorAll('.sub-proof-btn').forEach(function (el) {
            el.addEventListener('click', function () {
                var src = el.getAttribute('data-img') || '';
                var title = el.getAttribute('data-title') || 'Proof';
                open(src, title);
            });
        });
        closeBtn.addEventListener('click', close);
        if (backBtn) backBtn.addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    })();
</script>
<script>
    (function(){
        var btn = document.getElementById('admNotifBtn');
        var dropdown = document.getElementById('admNotifDropdown');
        var badge = document.getElementById('admNotifBadge');
        var list = document.getElementById('admNotifList');
        var markAllBtn = document.getElementById('admNotifMarkAll');
        if (!btn || !dropdown) return;

        function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

        function render(items){
            if (!items || !items.length){
                list.innerHTML = '<div class="adm-notif-empty">No notifications yet.</div>';
                return;
            }
            list.innerHTML = items.map(function(n){
                var unread = String(n.is_read)==='0';
                var link = n.link ? String(n.link) : '';
                var body = n.body ? String(n.body) : '';
                var attrs = '';
                if (link) attrs = ' data-link="'+esc(link)+'" style="cursor:pointer"';
                return '<div class="adm-notif-item'+(unread?' unread':'')+'\"'+attrs+'>'+ 
                    '<div class="adm-notif-item-body"><strong>'+esc(n.title)+'</strong>'+ (body?'<small>'+esc(n.body)+'</small>':'')+'</div>'+ '<div class="adm-notif-item-actions">'+ (unread?'<button class="adm-notif-mark" data-mark="'+esc(n.id)+'">Mark read</button>':'')+'</div></div>';
            }).join('');
        }

        function load(){
            fetch('../api/notifications-list.php?limit=8', {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data||!data.ok) return;
                    var unread = parseInt(data.unread||0, 10);
                    var items = data.items||[];
                    if (items.length > 0) {
                        if (unread > 0) {
                            badge.textContent = unread > 99 ? '99+' : String(unread);
                            badge.hidden = false;
                        } else {
                            badge.hidden = true;
                        }
                    } else {
                        badge.hidden = true;
                    }
                    render(items);
                })
                .catch(function(){ list.innerHTML='<div class="adm-notif-empty">Failed to load.</div>'; badge.hidden = true; });
        }

        function mark(id){
            var fd = new FormData();
            if (id) fd.append('id', String(id));
            fetch('../api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){ if(data&&data.ok) load(); })
                .catch(function(){});
        }

        list.addEventListener('click', function(e){
            var item = e.target && e.target.closest && e.target.closest('.adm-notif-item');
            if (!item) return;
            
            if (item.hasAttribute('data-mark')) {
                var id = parseInt(item.getAttribute('data-mark'), 10);
                if (id) mark(id);
            }
            
            if (item.hasAttribute('data-link')) {
                var url = item.getAttribute('data-link');
                if (url) window.location.href = url;
            }
        });

        markAllBtn.addEventListener('click', function(){ mark(0); });

        btn.addEventListener('click', function(e){
            e.stopPropagation();
            var open = !dropdown.hidden;
            dropdown.hidden = open;
            btn.setAttribute('aria-expanded', String(!open));
            if (!open) load();
        });

        document.addEventListener('click', function(e){
            if (!document.getElementById('admNotifWrap').contains(e.target)){
                dropdown.hidden = true;
                btn.setAttribute('aria-expanded','false');
            }
        });

        load();
    })();
</script>
</body>
</html>

