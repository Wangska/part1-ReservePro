<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';

// Build photo ticker images from real approved listings (fallback to demo if none)
$tickerImages = [];
try {
    $conn = getDBConnection();
    initializeHostTables();
    $res = $conn->query("
        SELECT pp.photo_url
        FROM property_photos pp
        JOIN properties p ON p.id = pp.property_id
        WHERE p.status = 'approved'
        ORDER BY pp.is_primary DESC, pp.id DESC
        LIMIT 14
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $raw = (string)($row['photo_url'] ?? '');
            if ($raw === '') continue;
            // Allow absolute URLs; otherwise treat as local relative path.
            $tickerImages[] = (preg_match('#^https?://#i', $raw) ? $raw : ltrim($raw, '/'));
        }
        $res->free();
    }
    $conn->close();
} catch (Throwable $e) {
    // If DB is unavailable, keep demo images below.
    $tickerImages = [];
}

// Redirect logged-in users to their appropriate dashboard
if (isLoggedIn()) {
    // No redirection for admin or host; allow all roles to access index.php
    // If you want to restrict other roles, add logic here
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReservePro - Find Your Perfect Stay in the Philippines</title>
    <meta name="description" content="Browse curated apartments, vacation homes, and hosted stays across the Philippines. Book with confidence through verified hosts and secure payments.">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/modal.css?v=25.2">
    <style>
        /* =====================
           RESET & BASE
        ===================== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --gold: #D4A574;
            --gold-dark: #B8935F;
            --navy: #06090F;
            --surface: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.08);
            --muted: rgba(203, 213, 225, 0.68);
            --font: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            background: var(--navy);
            color: #F1F5F9;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }
        button { cursor: pointer; font-family: inherit; }

        /* =====================
           NAVBAR
        ===================== */
        .lp-nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 900;
            height: 68px;
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.3s ease, border-bottom 0.3s ease;
        }
        .lp-nav.scrolled {
            background: rgba(6, 9, 15, 0.88);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
        }
        .lp-brand { display: flex; align-items: center; gap: 10px; }
        .lp-brand-icon {
            width: 34px; height: 34px;
            border-radius: 10px;
            border: 2px solid rgba(212, 165, 116, 0.55);
            object-fit: contain;
        }
        .lp-brand-name {
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #D4A574, #FAD798);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .lp-nav-links { display: flex; align-items: center; gap: 30px; }
        .lp-nav-links a {
            font-size: 14px;
            font-weight: 500;
            color: rgba(226, 232, 240, 0.78);
            transition: color 0.18s;
        }
        .lp-nav-links a:hover { color: #F1F5F9; }
        .lp-nav-actions { display: flex; align-items: center; gap: 10px; }
        .lp-btn-ghost {
            padding: 9px 20px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 999px;
            background: transparent;
            color: rgba(241, 245, 249, 0.88);
            font-size: 14px;
            font-weight: 600;
            transition: background 0.18s, border-color 0.18s;
        }
        .lp-btn-ghost:hover {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .lp-btn-gold {
            display: inline-flex;
            align-items: center;
            padding: 9px 22px;
            border-radius: 999px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            box-shadow: 0 6px 20px rgba(212, 165, 116, 0.3);
            transition: transform 0.18s, box-shadow 0.18s;
        }
        .lp-btn-gold:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(212, 165, 116, 0.44);
        }
        .guest-menu {
            position: relative;
        }
        .guest-menu-trigger {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 999px;
            color: #F3F4F6;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.25s ease;
            font-family: inherit;
            letter-spacing: -0.01em;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .guest-menu-trigger:hover {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            border-color: rgba(148, 163, 184, 0.3);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
        }
        .guest-menu.guest-menu-open .guest-menu-trigger {
            border-color: rgba(212, 165, 116, 0.5);
            box-shadow: 0 0 0 1px rgba(212, 165, 116, 0.15);
        }
        .guest-menu-chevron {
            opacity: 0.7;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .guest-menu.guest-menu-open .guest-menu-chevron {
            transform: rotate(180deg);
            opacity: 1;
        }
        .guest-menu-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 180px;
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.97), rgba(15, 23, 39, 0.98));
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            box-shadow: 0 18px 60px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.04) inset;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-12px) scale(0.96);
            transform-origin: top right;
            transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.22s;
            z-index: 1100;
        }
        .guest-menu-panel-open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        .guest-menu-item {
            display: block;
            padding: 12px 18px;
            color: #E5E7EB;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s ease, color 0.2s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .guest-menu-item:last-child {
            border-bottom: none;
        }
        .guest-menu-item:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #D4A574;
        }
        .guest-menu-item-logout {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 4px;
            padding-top: 12px;
        }
        .guest-menu-item-logout:hover {
            color: #FCA5A5;
        }
        body.light-mode .guest-menu-trigger {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
            border-color: rgba(15, 23, 42, 0.1);
            color: #111827;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }
        body.light-mode .guest-menu-trigger:hover {
            background: linear-gradient(145deg, #FFFFFF, #F8FAFC);
            border-color: rgba(184, 147, 95, 0.4);
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }
        body.light-mode .guest-menu.guest-menu-open .guest-menu-trigger {
            border-color: rgba(184, 147, 95, 0.6);
            box-shadow: 0 0 0 1px rgba(184, 147, 95, 0.2);
        }
        body.light-mode .guest-menu-panel {
            background: linear-gradient(160deg, #FFFFFF, #F8FAFC);
            border-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .guest-menu-item {
            color: rgba(15, 23, 42, 0.90);
            border-bottom-color: rgba(15, 23, 42, 0.06);
            text-align: center;
        }
        body.light-mode .guest-menu-item:hover {
            background: rgba(15, 23, 42, 0.04);
            color: rgba(139, 111, 71, 0.95);
        }
        body.light-mode .guest-menu-item-logout {
            border-top-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .guest-menu-item-logout:hover {
            color: rgba(248, 113, 113, 0.95);
        }

        /* =====================
           HERO
        ===================== */
        .lp-hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            padding: 100px 24px 80px;
        }
        .lp-hero-bg { position: absolute; inset: 0; z-index: 0; }
        .lp-hero-bg-img {
            width: 100%; height: 100%;
            object-fit: cover;
            object-position: center 30%;
            animation: heroZoom 18s ease-in-out infinite alternate;
        }
        @keyframes heroZoom {
            from { transform: scale(1.00); }
            to   { transform: scale(1.07); }
        }
        .main-banner {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(6, 9, 15, 0.40) 0%,
                rgba(6, 9, 15, 0.24) 35%,
                rgba(6, 9, 15, 0.65) 65%,
                rgba(6, 9, 15, 1.00) 100%
            );
        }
        .lp-hero-content {
            position: relative;
            z-index: 2;
            max-width: 860px;
        }
        .lp-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            background: rgba(6, 9, 15, 0.48);
            border: 1px solid rgba(212, 165, 116, 0.55);
            box-shadow: 0 18px 45px rgba(0,0,0,0.35), 0 0 0 1px rgba(212, 165, 116, 0.20) inset;
            backdrop-filter: blur(10px) saturate(160%);
            -webkit-backdrop-filter: blur(10px) saturate(160%);
            color: #FFF7D6;
            text-shadow: 0 2px 10px rgba(0,0,0,0.55);
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 30px;
        }
        .lp-hero-badge-dot {
            width: 9px; height: 9px;
            border-radius: 999px;
            background: #FDE68A;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18), 0 8px 20px rgba(245, 158, 11, 0.25);
            animation: blink 2.2s ease infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }
        .lp-hero-title {
            font-size: clamp(40px, 6.5vw, 78px);
            font-weight: 900;
            line-height: 1.03;
            letter-spacing: -0.04em;
            color: #FFFFFF;
            margin-bottom: 22px;
        }
        .lp-hero-title .gold {
            background: linear-gradient(135deg, #D4A574 0%, #FAD798 50%, #D4A574 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .lp-hero-sub {
            font-size: clamp(15px, 2vw, 18px);
            line-height: 1.65;
            color: rgba(226, 232, 240, 0.80);
            max-width: 520px;
            margin: 0 auto 42px;
        }
        .lp-hero-ctas {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .lp-cta-primary {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 16px 32px;
            border-radius: 999px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            border: none;
            box-shadow: 0 14px 38px rgba(212, 165, 116, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .lp-cta-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 52px rgba(212, 165, 116, 0.55);
        }
        .lp-cta-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 15px 28px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: rgba(241, 245, 249, 0.92);
            font-size: 15px;
            font-weight: 600;
            backdrop-filter: blur(8px);
            transition: background 0.18s, border-color 0.18s;
        }
        .lp-cta-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .lp-hero-trust {
            margin-top: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            color: rgba(203, 213, 225, 0.65);
            font-size: 13px;
        }
        .lp-trust-item { display: inline-flex; align-items: center; gap: 6px; }
        .lp-trust-sep { width: 4px; height: 4px; border-radius: 999px; background: rgba(212, 165, 116, 0.4); }
        .lp-hero-scroll {
            position: absolute;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            color: rgba(203, 213, 225, 0.52);
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            animation: scrollBounce 2.2s ease infinite;
        }
        @keyframes scrollBounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50%       { transform: translateX(-50%) translateY(7px); }
        }

        /* =====================
           PHOTO TICKER
        ===================== */
        .lp-ticker {
            overflow: hidden;
            padding: 18px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.015);
        }
        .lp-ticker-track {
            display: flex;
            gap: 14px;
            width: max-content;
            animation: tickerScroll 32s linear infinite;
        }
        .lp-ticker:hover .lp-ticker-track { animation-play-state: paused; }
        @keyframes tickerScroll {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .sliding-image {
            width: 240px;
            height: 128px;
            object-fit: cover;
            border-radius: 14px;
            flex-shrink: 0;
            opacity: 0.88;
        }

        /* =====================
           SHARED SECTION STYLES
        ===================== */
        .lp-section { max-width: 1180px; margin: 0 auto; padding: 100px 24px; }
        .lp-kicker {
            display: inline-block;
            margin-bottom: 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #D4A574;
        }
        .lp-h2 {
            font-size: clamp(28px, 4vw, 48px);
            font-weight: 900;
            line-height: 1.07;
            letter-spacing: -0.035em;
            color: #FFFFFF;
            margin-bottom: 14px;
        }
        .lp-lead { font-size: 16px; line-height: 1.7; color: var(--muted); max-width: 520px; margin: 0 auto; }

        /* =====================
           TWO-PATH CARDS
        ===================== */
        .lp-two-path { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .lp-path-card {
            position: relative;
            border-radius: 26px;
            overflow: hidden;
            min-height: 340px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding: 22px 28px 18px 28px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: transform 0.28s ease, background 0.28s ease, border-color 0.28s ease;
        }
        .lp-path-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(212, 165, 116, 0.22);
        }
        .lp-path-card-bg {
            position: absolute;
            inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .lp-path-card:hover .lp-path-card-bg { transform: scale(1.05); }
        .lp-path-card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(6, 9, 15, 0.92) 0%, rgba(6, 9, 15, 0.10) 60%);
        }
        .lp-path-content { position: relative; z-index: 2; }
        .lp-path-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .lp-path-pill.guest {
            background: none;
            border: none;
            color: inherit;
        }
        .lp-path-pill.host {
            background: rgba(129, 140, 248, 0.18);
            border: 1px solid rgba(129, 140, 248, 0.28);
            color: #a5b4fc;
        }
        .lp-path-card h3 {
            font-size: 27px;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.025em;
            margin-bottom: 10px;
        }
        .lp-path-card p {
            font-size: 14px;
            line-height: 1.6;
            color: rgba(203, 213, 225, 0.78);
            margin-bottom: 20px;
        }
        .lp-path-feats { list-style: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 26px; }
        .lp-path-feats li {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            color: rgba(203, 213, 225, 0.85);
        }
        .lp-path-feats li::before {
            content: "";
            width: 6px; height: 6px;
            border-radius: 999px;
            background: #D4A574;
            flex-shrink: 0;
        }
        .lp-path-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 22px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            transition: transform 0.18s;
        }
        .lp-path-cta:hover { transform: translateY(-2px); }
        .lp-path-cta.browse {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #fff;
            box-shadow: 0 10px 28px rgba(212, 165, 116, 0.35);
        }
        .lp-path-cta.host {
            background: rgba(255, 255, 255, 0.12);
            color: #F1F5F9;
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
        }

        /* =====================
           WHY SECTION
        ===================== */
        .lp-why-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 52px; }
        .lp-why-card {
            padding: 40px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            transition: transform 0.24s ease, background 0.24s ease, border-color 0.24s ease;
        }
        .lp-why-card:hover {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(212, 165, 116, 0.22);
        }
        .lp-why-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            background: rgba(212, 165, 116, 0.12);
            border: 1px solid rgba(212, 165, 116, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #D4A574;
            margin-bottom: 20px;
        }
        .lp-why-card h4 { font-size: 18px; font-weight: 700; letter-spacing: -0.01em; margin-bottom: 11px; }
        .lp-why-card p { font-size: 15px; line-height: 1.7; color: var(--muted); }

        /* =====================
           CTA BAND
        ===================== */
        .lp-cta-band {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 30% 50%, rgba(212, 165, 116, 0.12), transparent 55%),
                radial-gradient(ellipse at 70% 50%, rgba(99, 102, 241, 0.08), transparent 55%);
            border-top: 1px solid rgba(212, 165, 116, 0.12);
            border-bottom: 1px solid rgba(212, 165, 116, 0.12);
        }
        .lp-cta-inner { max-width: 720px; margin: 0 auto; padding: 100px 24px; text-align: center; }
        .lp-cta-inner .lp-h2 { margin-bottom: 16px; }
        .lp-cta-inner .lp-lead { margin: 0 auto 38px; }
        .lp-cta-btns { display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; }

        /* =====================
           FOOTER
        ===================== */
        .lp-footer {
            background: #03050A;
            border-top: 1px solid rgba(255, 255, 255, 0.055);
            padding: 64px 24px 36px;
        }
        .lp-footer-inner { max-width: 1180px; margin: 0 auto; }
        .lp-footer-top { display: grid; grid-template-columns: 1.5fr repeat(3, 1fr); gap: 44px; margin-bottom: 52px; }
        .lp-footer-brand-desc {
            margin-top: 14px;
            font-size: 14px;
            line-height: 1.7;
            color: rgba(203, 213, 225, 0.60);
            max-width: 280px;
        }
        .lp-footer-col h5 {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(248, 250, 252, 0.42);
            margin-bottom: 18px;
        }
        .lp-footer-col ul { list-style: none; }
        .lp-footer-col ul li { margin-bottom: 11px; }
        .lp-footer-col ul li a {
            font-size: 14px;
            color: rgba(203, 213, 225, 0.65);
            transition: color 0.18s;
        }
        .lp-footer-col ul li a:hover { color: #D4A574; }
        .lp-footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.055);
            padding-top: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .lp-footer-bottom p { font-size: 13px; color: rgba(203, 213, 225, 0.38); }
        .lp-footer-bottom-links { display: flex; gap: 22px; }
        .lp-footer-bottom-links a {
            font-size: 13px;
            color: rgba(203, 213, 225, 0.40);
            transition: color 0.18s;
        }
        .lp-footer-bottom-links a:hover { color: rgba(203, 213, 225, 0.75); }

        /* =====================
           MODAL DISPLAY OVERRIDE
        ===================== */
        .modal.lp-open {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* =====================
           RESPONSIVE
        ===================== */
        @media (max-width: 1024px) {
            .lp-why-grid { grid-template-columns: 1fr 1fr; }
            .lp-footer-top { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .lp-nav-links { display: none; }
            .lp-two-path { grid-template-columns: 1fr; }
            .lp-path-card { min-height: 260px; padding: 18px 14px 10px 14px; }
            .lp-why-grid { grid-template-columns: 1fr; }
            .lp-footer-top { grid-template-columns: 1fr; gap: 32px; }
            .lp-footer-brand-desc { max-width: 100%; }
        }
        @media (max-width: 480px) {
            .lp-nav { padding: 0 18px; }
            .lp-btn-ghost { display: none; }
        }
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<nav class="lp-nav" id="lpNav">
    <a href="index.php" class="lp-brand">
        <img class="lp-brand-icon" src="/part1-ReservePro/background%20image/asd.webp" alt="ReservePro">
        <span class="lp-brand-name">ReservePro</span>
    </a>
    <div class="lp-nav-links">
        <a href="home.php">Browse Stays</a>
        <a href="become-host.php">Become a Host</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
    </div>
    <div class="lp-nav-actions">
        <?php $user = isLoggedIn() ? getCurrentUser() : null; ?>
        <?php if ($user): ?>
        <div class="guest-menu">
            <button type="button" class="guest-menu-trigger" id="guestMenuTrigger" aria-expanded="false" aria-haspopup="true">
                <span class="guest-menu-name">Hi, <?php echo htmlspecialchars($user['first_name']); ?></span>
                <svg class="guest-menu-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="guest-menu-panel" id="guestMenuPanel" role="menu" aria-hidden="true">
                <?php if (isset($user['role']) && $user['role'] !== 'admin'): ?>
                <a href="messages.php" role="menuitem" class="guest-menu-item">Messages</a>
                <?php endif; ?>
                <?php if (isset($user['role']) && $user['role'] === 'guest'): ?>
                <a href="my-bookings.php" role="menuitem" class="guest-menu-item">My bookings</a>
                <a href="profile.php" role="menuitem" class="guest-menu-item">Profile</a>
                <?php elseif (isset($user['role']) && $user['role'] === 'host'): ?>
                <a href="host/properties.php" role="menuitem" class="guest-menu-item">Property</a>
                <?php elseif (isset($user['role']) && $user['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" role="menuitem" class="guest-menu-item">Admin</a>
                <?php endif; ?>
                <a href="logout.php" role="menuitem" class="guest-menu-item guest-menu-item-logout">Logout</a>
            </div>
        </div>
        <script>
        (function() {
            var trigger = document.getElementById('guestMenuTrigger');
            var panel = document.getElementById('guestMenuPanel');
            var menu = trigger && trigger.closest('.guest-menu');
            if (!trigger || !panel) return;
            function toggle() {
                var open = panel.classList.toggle('guest-menu-panel-open');
                trigger.setAttribute('aria-expanded', open);
                panel.setAttribute('aria-hidden', !open);
                if (menu) menu.classList.toggle('guest-menu-open', open);
            }
            function close() {
                panel.classList.remove('guest-menu-panel-open');
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('aria-hidden', 'true');
                if (menu) menu.classList.remove('guest-menu-open');
            }
            trigger.addEventListener('click', function(e) { e.stopPropagation(); toggle(); });
            document.addEventListener('click', function() { close(); });
            panel.addEventListener('click', function(e) { e.stopPropagation(); });
        })();
        </script>
        <?php else: ?>
        <a href="login.php" class="lp-btn-ghost" id="openLoginBtn">Sign In</a>
        <a href="register.php" class="lp-btn-gold">Get Started</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===================== HERO ===================== -->
<section class="lp-hero">
    <div class="lp-hero-bg">
        <img class="lp-hero-bg-img"
             src="background%20image/main-banner.jpg"
             alt="Beautiful Philippine destination"
             onerror="this.src='https://images.unsplash.com/photo-1518684079-3c830dcef090?w=1600&auto=format&fit=crop&q=80'">
        <div class="main-banner"></div>
    </div>

    <div class="lp-hero-content">
        <h1 class="lp-hero-title">
            Find your perfect<br>
            <span class="gold">stay in the Philippines.</span>
        </h1>

        <div class="lp-hero-ctas">
            <a href="home.php" class="lp-cta-primary">
                Browse Stays
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
            <a href="become-host.php" class="lp-cta-secondary">Become a Host</a>
        </div>
    </div>

    <div class="lp-hero-scroll" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        Scroll
    </div>
</section>

<!-- ===================== PHOTO TICKER ===================== -->
<div class="lp-ticker" aria-hidden="true">
    <div class="lp-ticker-track">
        <?php
        $demo = [
            'assets/sliding-img/1.jpg',
            'assets/sliding-img/2.jpg',
            'assets/sliding-img/3.jpg',
            'assets/sliding-img/4.jpg',
            'assets/sliding-img/5.jpg',
            'assets/sliding-img/6.jpg',
            'assets/sliding-img/7.jpg',
        ];
        $srcs = !empty($tickerImages) ? $tickerImages : $demo;
        // Duplicate for seamless loop (CSS animates -50%).
        $loop = array_merge($srcs, $srcs);
        foreach ($loop as $src) {
            $safe = htmlspecialchars((string)$src);
            echo '<img class="sliding-image" src="' . $safe . '" alt="">';
        }
        ?>
    </div>
</div>

<!-- ===================== TWO-PATH SECTION ===================== -->
<section class="lp-section">
    <div style="text-align:center; margin-bottom:56px;">
        <span class="lp-kicker">How it works</span>
        <h2 class="lp-h2">Two ways to use ReservePro</h2>
    </div>
    <div class="lp-two-path">
        <!-- Guest card -->
        <a href="home.php" class="lp-path-card">
            <div class="lp-path-content">
                <span class="lp-path-pill guest">For Guests</span>
                <h3>Browse &amp; Book Stays</h3>
                <p>Find apartments, condos, and vacation homes across the Philippines. Filter by price, amenities, and location.</p>
                <ul class="lp-path-feats">
                    <li>Verified listings from trusted hosts</li>
                    <li>Flexible short-term bookings</li>
                    <li>Secure online payment via PayMongo</li>
                </ul>
                <span class="lp-path-cta browse">
                    Browse Stays
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </span>
            </div>
        </a>
        <!-- Host card -->
        <a href="become-host.php" class="lp-path-card">
            <div class="lp-path-content">
                <span class="lp-path-pill host">For Hosts</span>
                <h3>List Your Property</h3>
                <p>Turn your space into income. List your property, set your own rates, and manage bookings from your personal dashboard.</p>
                <ul class="lp-path-feats">
                    <li>Easy step-by-step listing setup</li>
                    <li>Real-time booking management</li>
                    <li>Track earnings and guest reviews</li>
                </ul>
                <span class="lp-path-cta host">
                    Become a Host
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </span>
            </div>
        </a>
    </div>
</section>

<!-- ===================== WHY SECTION ===================== -->
<section class="lp-section" style="padding-top:0;">
    <div style="text-align: center;">
        <span class="lp-kicker">Why ReservePro</span>
        <h2 class="lp-h2">Built for a better<br>booking experience.</h2>
    </div>
    <div class="lp-why-grid">
        <div class="lp-why-card">
            <div class="lp-why-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <h4>Lower Fees Better Value</h4>
            <p>Enjoy competitive rates with lower platform fees. More value for both hosts and guests compared to international platforms.</p>
        </div>
        <div class="lp-why-card">
            <div class="lp-why-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <h4>100% Philippine Focused</h4>
            <p>Designed exclusively for the Philippine market with local payment methods, support, and deep understanding of regional needs.</p>
        </div>
        <div class="lp-why-card">
            <div class="lp-why-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 2l-4 4m0-4 4 4"/></svg>
            </div>
            <h4>Community Driven</h4>
            <p>Built by Filipinos for Filipinos. Join a growing community of verified hosts and travelers sharing authentic local experiences.</p>
        </div>
    </div>
</section>



<!-- ===================== CTA BAND ===================== -->
<section class="lp-cta-band">
    <div class="lp-cta-inner">
        <h2 class="lp-h2">Find your perfect stay today.</h2>
    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="lp-footer">
    <div class="lp-footer-inner">
        <div class="lp-footer-top">
            <div>
                <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;">
                    <img style="width:32px;height:32px;border-radius:10px;border:2px solid rgba(212,165,116,0.5);object-fit:contain;"
                         src="/part1-ReservePro/background%20image/asd.webp" alt="ReservePro">
                    <span style="font-size:20px;font-weight:800;background:linear-gradient(135deg,#D4A574,#FAD798);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-0.5px;">ReservePro</span>
                </a>
                <p class="lp-footer-brand-desc">Find, compare, and book curated stays across the Philippines. Built for travelers, designed for hosts.</p>
            </div>
            <div class="lp-footer-col">
                <h5>Explore</h5>
                <ul>
                    <li><a href="home.php">Browse Stays</a></li>
                    <li><a href="experiences.php">Experiences</a></li>
                    <li><a href="become-host.php">Become a Host</a></li>
                </ul>
            </div>
            <div class="lp-footer-col">
                <h5>Company</h5>
                <ul>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="#">Careers</a></li>
                </ul>
            </div>
            <div class="lp-footer-col">
                <h5>Support</h5>
                <ul>
                    <li><a href="contact.php">Help Center</a></li>
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="lp-footer-bottom">
            <p>&copy; 2026 ReservePro. All rights reserved.</p>
            <div class="lp-footer-bottom-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="contact.php">Contact</a>
            </div>
        </div>
    </div>
</footer>

<!-- ===================== LOGIN MODAL ===================== -->
<div id="loginModal" class="modal">
    <div class="modal-overlay" id="loginModalOverlay"></div>
    <div class="modal-content">
        <button class="modal-close" id="closeLoginBtn">&times;</button>
        <div class="modal-header">
            <div style="margin-bottom:12px;">
                <img src="background%20image/z.jpg"
                     alt=""
                     style="width:64px;height:64px;border-radius:18px;object-fit:cover;display:block;margin:0 auto;"
                     onerror="this.style.display='none'">
            </div>
            <h2>Welcome Back</h2>
            <p>Log in to your ReservePro account</p>
        </div>
        <form class="modal-form" method="POST" action="login.php">
            <div class="form-group">
                <label for="lp-email">Email</label>
                <input type="email" id="lp-email" name="email" placeholder="john@example.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="lp-password">Password</label>
                <input type="password" id="lp-password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="modal-btn">Sign In</button>
        </form>
        <div class="modal-divider"><span>or</span></div>
        <button class="modal-btn-social" onclick="window.location.href='google-login.php'">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Continue with Google
        </button>
        <div class="modal-footer">
            <p>Don't have an account? <a href="register.php">Sign up as Guest</a></p>
        </div>
    </div>
</div>

<script>
    // Navbar scroll glass effect
    (function () {
        var nav = document.getElementById('lpNav');
        function tick() { nav.classList.toggle('scrolled', window.scrollY > 50); }
        window.addEventListener('scroll', tick, { passive: true });
        tick();
    })();


</script>

</body>
</html>


