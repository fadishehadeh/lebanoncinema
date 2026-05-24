<?php
/**
 * SEO Meta Variables — set these before including header.php:
 *
 * $pageTitle         (string)  Page title (already used)
 * $pageDescription   (string)  Meta description
 * $pageImage         (string)  OG/Twitter image URL
 * $canonical         (string)  Canonical URL (relative or absolute)
 * $ogType            (string)  og:type (default 'website')
 * $jsonLd            (array)   Additional JSON-LD schema objects
 *
 * Examples:
 *   $pageDescription = 'Showtimes for Inception at VOX Cinemas Beirut. Book tickets online.';
 *   $canonical = '/movies/inception';
 *   $jsonLd = ['@type' => 'Movie', 'name' => 'Inception'];
 */
$canonical    = $canonical ?? $_SERVER['REQUEST_URI'];
$pageDescription = $pageDescription ?? 'Discover what\'s playing at cinemas across Lebanon. Browse showtimes, watch trailers, and book tickets for VOX, Grand, Empire and more.';
$pageImage    = $pageImage ?? '';
$ogType       = $ogType ?? 'website';
$jsonLd       = $jsonLd ?? [];
$breadcrumbs  = $breadcrumbs ?? [];
$siteUrl      = rtrim(SITE_URL, '/');
$canonicalUrl = str_starts_with($canonical, 'http') ? $canonical : url($canonical);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? SITE_NAME) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="<?= htmlspecialchars($ogType) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? SITE_NAME) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars(SITE_NAME) ?>">
    <meta property="og:locale" content="en_US">
    <?php if ($pageImage): ?>
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">
    <meta property="og:image:width" content="500">
    <meta property="og:image:height" content="750">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? SITE_NAME) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php if ($pageImage): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($pageImage) ?>">
    <?php endif; ?>

    <!-- Robots -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5198102919338219" crossorigin="anonymous"></script>

    <style>
        /* ══════════════════════════════════════════════════
           DESIGN TOKENS
           ══════════════════════════════════════════════════ */
        :root {
            --bg:          #050505;
            --bg-alt:      #0B0B0B;
            --surface:     #0B0B0B;
            --surface2:    #111111;
            --card:        #111111;
            --card-hover:  #1A1A1A;
            --border:      rgba(255,255,255,0.03);
            --border-strong: rgba(255,255,255,0.06);
            --text:        #FFFFFF;
            --text-muted:  #808080;
            --accent:      #E50914;
            --accent-blue: #5E8BFF;
            --accent-glow: rgba(229,9,20,0.3);
            --blue-glow:   rgba(94,139,255,0.15);
            --radius-sm:   4px;
            --radius-md:   8px;
            --radius-lg:   12px;
            --radius-xl:   16px;
            --shadow-card: 0 8px 30px rgba(0,0,0,0.5);
            --shadow-glow: 0 0 40px rgba(229,9,20,0.15);
            --ease:        cubic-bezier(0.4, 0, 0.2, 1);
            --nav-height:  68px;
            --bottom-nav-height: 64px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-font-smoothing: antialiased; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            min-height: 100vh;
            padding-bottom: var(--bottom-nav-height);
            overflow-x: hidden;
        }

        h1, h2, h3, h4 {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            line-height: 1.15;
        }
        a { color: inherit; text-decoration: none; transition: color 0.2s; }
        a:hover { color: var(--accent); }
        img { display: block; max-width: 100%; }

        /* ══════════════════════════════════════════════════
           NAVBAR
           ══════════════════════════════════════════════════ */
        .topnav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            height: var(--nav-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 48px;
            background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, transparent 100%);
            transition: background 0.3s var(--ease);
        }
        .topnav.scrolled {
            background: rgba(5,5,5,0.95);
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .brand {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: -0.02em;
        }
        .brand span { color: var(--text); }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .nav-link {
            color: #b3b3b3;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-link:hover,
        .nav-link.active { color: var(--text); }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nav-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 6px;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }
        .nav-btn:hover { color: var(--text); background: var(--card); }
        .nav-btn svg { width: 20px; height: 20px; }

        /* ══════════════════════════════════════════════════
           HERO
           ══════════════════════════════════════════════════ */
        .hero {
            position: relative;
            width: 100%;
            height: 80vh;
            min-height: 600px;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .hero-backdrop {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .hero-backdrop img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero-backdrop::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(5,5,5,0.85) 0%, rgba(5,5,5,0.4) 35%, transparent 60%),
                        linear-gradient(0deg, rgba(5,5,5,1) 0%, rgba(5,5,5,0.2) 40%, transparent 60%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-end;
            gap: 48px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 80px 48px 60px;
            width: 100%;
        }

        .hero-poster {
            flex-shrink: 0;
            width: 200px;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: 0 16px 48px rgba(0,0,0,0.6);
        }
        .hero-poster img {
            width: 100%;
            display: block;
            aspect-ratio: 2/3;
            object-fit: cover;
        }

        .hero-info { flex: 1; min-width: 0; max-width: 600px; }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: rgba(229,9,20,0.15);
            border: 1px solid rgba(229,9,20,0.25);
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
        }

        .hero-title {
            font-size: 3rem;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
            line-height: 1.05;
        }

        .hero-tagline {
            font-size: 1rem;
            color: #b3b3b3;
            margin-bottom: 20px;
            max-width: 500px;
            line-height: 1.5;
        }

        .hero-meta {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 24px;
            font-size: 0.85rem;
            color: #b3b3b3;
            flex-wrap: wrap;
        }
        .hero-meta span { display: flex; align-items: center; gap: 6px; }
        .hero-meta .dot { width: 3px; height: 3px; border-radius: 50%; background: #666; }

        .hero-ctas { display: flex; gap: 12px; flex-wrap: wrap; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s var(--ease);
            border: none;
            font-family: inherit;
        }
        .btn svg { width: 20px; height: 20px; }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        .btn-primary:hover {
            background: #f6121d;
            color: #fff;
        }

        .btn-outline {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: var(--text);
        }
        .btn-outline:hover {
            background: rgba(255,255,255,0.2);
            color: var(--text);
            border-color: rgba(255,255,255,0.4);
        }

        .hero-quick-times {
            margin-top: 20px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .hero-quick-times .label {
            font-size: 0.7rem;
            color: #b3b3b3;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        .hero-time-chip {
            padding: 6px 14px;
            border-radius: 4px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            color: var(--text);
        }
        .hero-time-chip:hover {
            border-color: var(--accent);
            background: rgba(229,9,20,0.08);
        }

        .hero-search-fallback {
            text-align: center;
            padding: 120px 48px 80px;
            width: 100%;
        }
        .hero-search-fallback h1 {
            font-size: 2.5rem;
            margin-bottom: 12px;
            color: var(--text);
        }
        .hero-search-fallback p {
            color: var(--text-muted);
            margin-bottom: 32px;
        }

        /* ══════════════════════════════════════════════════
           SEARCH
           ══════════════════════════════════════════════════ */
        .search-section {
            max-width: 640px;
            margin: 0 auto 48px;
            padding: 0 24px;
        }

        .search-wrap {
            position: relative;
        }
        .search-input {
            width: 100%;
            height: 56px;
            padding: 0 48px 0 20px;
            border-radius: var(--radius-xl);
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s var(--ease);
            font-family: inherit;
        }
        .search-input::placeholder { color: var(--text-muted); }
        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(255,61,113,0.1);
            background: var(--card-hover);
        }

        .search-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .search-icon svg { width: 20px; height: 20px; }

        .search-dropdown {
            position: absolute;
            top: 64px;
            left: 0;
            right: 0;
            background: var(--card);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            z-index: 500;
            max-height: 420px;
            overflow-y: auto;
            display: none;
        }
        .search-dropdown.open { display: block; }

        .search-group-label {
            padding: 12px 16px 6px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            font-weight: 600;
        }

        .search-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            transition: background 0.15s;
            cursor: pointer;
        }
        .search-item:hover { background: var(--card-hover); }

        .search-item-poster {
            width: 36px;
            height: 54px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            flex-shrink: 0;
            background: var(--surface);
        }
        .search-item-info { flex: 1; min-width: 0; }
        .search-item-title {
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .search-item-meta {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .search-item svg {
            width: 16px;
            height: 16px;
            color: var(--accent);
            flex-shrink: 0;
        }

        /* ══════════════════════════════════════════════════
           DISCOVERY CHIPS
           ══════════════════════════════════════════════════ */
        .chips-row {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 0 24px 24px;
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .chips-row::-webkit-scrollbar { display: none; }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            flex-shrink: 0;
            cursor: pointer;
            transition: all 0.2s var(--ease);
        }
        .chip:hover {
            border-color: var(--accent);
            color: var(--text);
            background: rgba(255,61,113,0.06);
        }
        .chip.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #000;
            font-weight: 600;
        }

        /* ══════════════════════════════════════════════════
           SECTION / CAROUSEL
           ══════════════════════════════════════════════════ */
        .section {
            margin-bottom: 36px;
            position: relative;
        }

        .section-header {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            padding: 0 48px;
            margin-bottom: 16px;
            gap: 16px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
            letter-spacing: -0.01em;
        }

        .section-link {
            font-size: 0.8rem;
            color: #b3b3b3;
            font-weight: 400;
            transition: color 0.2s;
        }
        .section-link:hover { color: var(--text); }

        .carousel {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 0 48px 8px;
            scroll-behavior: smooth;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .carousel::-webkit-scrollbar { display: none; }
        .carousel::after {
            content: '';
            flex-shrink: 0;
            width: 48px;
            height: 1px;
        }

        .carousel-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            padding: 0 48px;
        }
        .carousel-grid .poster-card { width: 100%; }
        @media (min-width: 1400px) {
            .carousel-grid { grid-template-columns: repeat(5, 1fr); gap: 18px; }
        }
        @media (min-width: 1800px) {
            .carousel-grid { grid-template-columns: repeat(6, 1fr); gap: 20px; }
        }
        @media (max-width: 900px) {
            .carousel-grid { grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 0 24px; }
        }
        @media (max-width: 768px) {
            .carousel-grid { padding: 0 16px; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        }

        /* ══════════════════════════════════════════════════
           POSTER CARD
           ══════════════════════════════════════════════════ */
        .poster-card {
            position: relative;
            flex-shrink: 0;
            width: 200px;
            aspect-ratio: 2/3;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: #111;
            scroll-snap-align: start;
            cursor: pointer;
            transition: all 0.3s var(--ease);
            border: none;
        }
        @media (min-width: 1400px) { .poster-card { width: 100%; } }
        @media (min-width: 1800px) { .poster-card { width: 100%; } }
        .poster-card:hover {
            transform: translateY(-4px) scale(1.04);
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
            z-index: 10;
        }

        .poster-card-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s var(--ease);
        }
        .poster-card:hover .poster-card-img {
            transform: scale(1.06);
        }

        .poster-card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top,
                rgba(0,0,0,0.95) 0%,
                rgba(0,0,0,0.3) 45%,
                transparent 70%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 12px;
            opacity: 0;
            transition: opacity 0.25s var(--ease);
            z-index: 2;
            pointer-events: none;
        }
        .poster-card:hover .poster-card-overlay {
            opacity: 1;
        }
        .poster-card-overlay .poster-card-title {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .poster-card-overlay .poster-card-meta {
            font-size: 0.7rem;
            color: #b3b3b3;
        }

        .default-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top,
                rgba(0,0,0,0.9) 0%,
                rgba(0,0,0,0.1) 50%,
                transparent 70%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 12px;
            z-index: 1;
            pointer-events: none;
        }
        .default-overlay .poster-card-title {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .default-overlay .poster-card-meta {
            font-size: 0.7rem;
            color: #b3b3b3;
        }

        .poster-card-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; }
        .poster-card-meta { font-size: 0.7rem; color: #b3b3b3; }

        .poster-card-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.6rem;
            font-weight: 700;
            z-index: 3;
        }
        .poster-card:hover {
            border-color: var(--border-strong);
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-card);
        }

        .poster-card-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s var(--ease);
        }
        .poster-card:hover .poster-card-img {
            transform: scale(1.06);
        }

        .poster-card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top,
                rgba(11,11,15,0.95) 0%,
                rgba(11,11,15,0.3) 50%,
                transparent 70%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 14px;
            opacity: 0;
            transition: opacity 0.3s var(--ease);
            z-index: 2;
            pointer-events: none;
        }
        .poster-card:hover .poster-card-overlay {
            opacity: 1;
        }

        .default-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top,
                rgba(11,11,15,0.92) 0%,
                rgba(11,11,15,0.15) 60%,
                transparent 80%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 14px;
            z-index: 1;
            pointer-events: none;
        }

        .poster-card-title {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .poster-card-meta {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .poster-card-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 10px;
            border-radius: 6px;
            z-index: 3;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            z-index: 2;
        }
        .poster-card-badge.accent {
            background: var(--accent);
            color: #000;
        }
        .poster-card-badge.blue {
            background: var(--accent-blue);
            color: #fff;
        }
        .poster-card-badge.orange {
            background: #FF9500;
            color: #000;
        }

        .poster-card-stats {
            position: absolute;
            bottom: 14px;
            left: 14px;
            display: flex;
            gap: 8px;
            z-index: 2;
        }
        .stat-pill {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 700;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
        }
        .stat-pill.rating { color: #FFD700; }

        /* ══════════════════════════════════════════════════
           CINEMA CARD
           ══════════════════════════════════════════════════ */
        .cinema-scroll {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 0 24px 8px;
            scrollbar-width: none;
        }
        .cinema-scroll::-webkit-scrollbar { display: none; }

        .cinema-card {
            flex-shrink: 0;
            width: 280px;
            padding: 20px;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--card) 0%, var(--surface2) 100%);
            border: 1px solid rgba(255,255,255,0.04);
            transition: all 0.35s var(--ease);
            cursor: pointer;
            scroll-snap-align: start;
        }
        .cinema-card:hover {
            border-color: rgba(94,139,255,0.15);
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.5), 0 0 30px rgba(94,139,255,0.06);
        }

        .cinema-card-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .cinema-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .cinema-card-name {
            font-size: 0.95rem;
            font-weight: 600;
            flex: 1;
        }
        .cinema-card-detail {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .cinema-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cinema-card-badges {
            display: flex;
            gap: 4px;
        }
        .mini-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 700;
            background: rgba(255,255,255,0.06);
            color: var(--text-muted);
            text-transform: uppercase;
        }
        .cinema-card-next {
            font-size: 0.75rem;
            color: var(--accent);
            font-weight: 600;
        }

        /* ══════════════════════════════════════════════════
           POSTER WALL (Upcoming)
           ══════════════════════════════════════════════════ */
        .poster-wall {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            padding: 0 24px;
        }

        .wall-card {
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--card);
            cursor: pointer;
            transition: all 0.35s var(--ease);
            border: 1px solid rgba(255,255,255,0.03);
        }
        .wall-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.5), 0 0 30px rgba(255,45,111,0.06);
            border-color: rgba(255,45,111,0.12);
        }
        .wall-card img {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            display: block;
            transition: transform 0.5s var(--ease);
        }
        .wall-card:hover img { transform: scale(1.06); }
        .wall-card-info {
            padding: 10px 12px;
        }
        .wall-card-title {
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .wall-card-meta {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ══════════════════════════════════════════════════
           MOVIE GRID (Movies Listing Page)
           ══════════════════════════════════════════════════ */
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 14px;
            padding: 0 24px;
        }

        .movie-grid .poster-card {
            width: 100%;
            aspect-ratio: 2/3;
        }

        .movie-grid .poster-card .default-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top,
                rgba(11,11,15,0.9) 0%,
                rgba(11,11,15,0.2) 50%,
                transparent 70%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 14px;
        }
        .movie-grid .poster-card .default-overlay .poster-card-title {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .movie-grid .poster-card .default-overlay .poster-card-meta {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        /* ══════════════════════════════════════════════════
           MOVIE DETAIL PAGE
           ══════════════════════════════════════════════════ */
        .movie-backdrop {
            position: fixed;
            inset: 0;
            z-index: -1;
            background: center/cover no-repeat;
            filter: blur(80px) brightness(0.12) saturate(0.5);
        }

        .detail-hero {
            display: flex;
            gap: 48px;
            align-items: flex-start;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .detail-poster {
            flex-shrink: 0;
            width: 260px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-card), 0 0 30px var(--accent-glow);
        }
        .detail-poster img {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            display: block;
        }

        .detail-info { flex: 1; }

        .detail-title {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }

        .detail-meta {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 16px;
        }

        .detail-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .detail-tag {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .detail-tag.rating {
            background: var(--accent);
            color: #000;
        }
        .detail-tag.genre {
            background: var(--card);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .detail-synopsis {
            max-width: 560px;
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .detail-ctas {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* ══════════════════════════════════════════════════
           SHOWTIMES
           ══════════════════════════════════════════════════ */
        .showtimes-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px 48px;
        }

        .date-strip {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 24px;
            scrollbar-width: none;
        }
        .date-strip::-webkit-scrollbar { display: none; }

        .date-chip {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 16px;
            border-radius: var(--radius-md);
            background: var(--card);
            border: 1px solid var(--border);
            flex-shrink: 0;
            min-width: 60px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }
        .date-chip:hover {
            border-color: var(--border-strong);
        }
        .date-chip.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #000;
        }
        .date-chip .day {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .date-chip .num {
            font-size: 0.95rem;
            font-weight: 700;
            margin-top: 2px;
        }

        .showtime-group {
            margin-bottom: 20px;
        }

        .showtime-cinema {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .showtime-cinema .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .showtime-cinema .name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .showtime-cinema .format {
            font-size: 0.7rem;
            color: var(--accent-blue);
            font-weight: 600;
            text-transform: uppercase;
        }

        .times-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .time-btn {
            padding: 8px 18px;
            border-radius: 20px;
            border: 1.5px solid var(--accent);
            background: transparent;
            color: var(--text);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s var(--ease);
            font-family: inherit;
        }
        .time-btn:hover {
            background: var(--accent);
            color: #000;
            box-shadow: 0 0 20px var(--accent-glow);
        }
        .time-btn.bookable:hover {
            background: var(--accent);
        }

        /* ══════════════════════════════════════════════════
           CINEMA DETAIL
           ══════════════════════════════════════════════════ */
        .cinema-detail-hero {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .cinema-detail-info h1 {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        .cinema-detail-chain {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 12px;
        }
        .cinema-detail-location {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 16px;
        }
        .cinema-detail-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .cinema-detail-stats {
            text-align: right;
        }
        .cinema-detail-stats .num {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
        }
        .cinema-detail-stats .label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .cinema-chain-line {
            height: 3px;
            border-radius: 2px;
            margin-bottom: 32px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ══════════════════════════════════════════════════
           BOTTOM NAV
           ══════════════════════════════════════════════════ */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 999;
            height: var(--bottom-nav-height);
            background: rgba(11,11,15,0.92);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 0 8px;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            color: var(--text-muted);
            font-size: 0.65rem;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
            border: none;
            background: none;
            cursor: pointer;
            font-family: inherit;
        }
        .bottom-nav-item svg {
            width: 22px;
            height: 22px;
        }
        .bottom-nav-item.active,
        .bottom-nav-item:hover {
            color: var(--accent);
        }

        /* ══════════════════════════════════════════════════
           FOOTER
           ══════════════════════════════════════════════════ */
        .footer {
            position: relative;
            padding: 48px 48px 24px;
            margin-top: 48px;
            background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.8) 100%);
            border-top: 1px solid rgba(255,255,255,0.03);
        }
        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 48px;
        }
        .footer h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text);
        }
        .footer h4 {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 12px;
            font-weight: 500;
        }
        .footer a {
            display: block;
            font-size: 0.8rem;
            color: #808080;
            margin-bottom: 8px;
            transition: color 0.2s;
        }
        .footer a:hover { color: var(--text); }
        .footer p { color: #808080; font-size: 0.8rem; line-height: 1.5; }
        .footer-brand p { margin-top: 8px; }
        .footer-bottom {
            max-width: 1200px;
            margin: 32px auto 0;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.03);
            text-align: center;
            color: #555;
            font-size: 0.75rem;
        }

        /* ══════════════════════════════════════════════════
           NO RESULTS
           ══════════════════════════════════════════════════ */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            color: var(--text-muted);
        }
        .empty-state p { font-size: 0.9rem; }

        /* ══════════════════════════════════════════════════
           ANIMATIONS
           ══════════════════════════════════════════════════ */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .fade-up {
            animation: fadeUp 0.5s var(--ease) both;
        }
        .fade-in {
            animation: fadeIn 0.5s var(--ease) both;
        }

        .stagger > * {
            animation: fadeUp 0.5s var(--ease) both;
        }
        .stagger > *:nth-child(1) { animation-delay: 0.05s; }
        .stagger > *:nth-child(2) { animation-delay: 0.1s; }
        .stagger > *:nth-child(3) { animation-delay: 0.15s; }
        .stagger > *:nth-child(4) { animation-delay: 0.2s; }
        .stagger > *:nth-child(5) { animation-delay: 0.25s; }
        .stagger > *:nth-child(6) { animation-delay: 0.3s; }
        .stagger > *:nth-child(7) { animation-delay: 0.35s; }
        .stagger > *:nth-child(8) { animation-delay: 0.4s; }
        .stagger > *:nth-child(9) { animation-delay: 0.45s; }
        .stagger > *:nth-child(10) { animation-delay: 0.5s; }

        /* ══════════════════════════════════════════════════
           GENRE STRIP (movies page)
           ══════════════════════════════════════════════════ */
        .genre-strip {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 0 24px 24px;
            scrollbar-width: none;
        }
        .genre-strip::-webkit-scrollbar { display: none; }

        .genre-pill {
            display: inline-flex;
            padding: 7px 16px;
            border-radius: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .genre-pill:hover {
            border-color: var(--accent);
            color: var(--text);
            background: rgba(255,61,113,0.06);
        }
        .genre-pill.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #000;
            font-weight: 600;
        }

        /* ══════════════════════════════════════════════════
           SKELETON LOADERS
           ══════════════════════════════════════════════════ */
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .skel {
            background: linear-gradient(90deg, var(--card) 25%, var(--card-hover) 50%, var(--card) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s ease-in-out infinite;
            border-radius: var(--radius-sm);
        }
        .skel-poster {
            width: 160px;
            aspect-ratio: 2/3;
            border-radius: var(--radius-md);
            flex-shrink: 0;
        }
        .skel-text {
            height: 14px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        .skel-text-sm { height: 10px; width: 60%; }
        .skel-text-md { width: 80%; }
        .skel-text-lg { height: 18px; width: 70%; margin-bottom: 12px; }
        .skel-hero {
            width: 100%;
            min-height: 50vh;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
        }
        .skel-row {
            display: flex;
            gap: 12px;
            padding: 0 24px;
            overflow: hidden;
        }

        /* ══════════════════════════════════════════════════
           HAMBURGER MENU
           ══════════════════════════════════════════════════ */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
        }
        .hamburger span {
            display: block;
            width: 20px;
            height: 2px;
            background: var(--text-muted);
            border-radius: 2px;
            transition: all 0.3s var(--ease);
        }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

        .mobile-menu {
            display: none;
            position: fixed;
            top: var(--nav-height);
            left: 0;
            right: 0;
            bottom: var(--bottom-nav-height);
            z-index: 999;
            background: rgba(11,11,15,0.98);
            backdrop-filter: blur(24px);
            padding: 24px;
            flex-direction: column;
            gap: 8px;
            animation: fadeIn 0.2s var(--ease);
        }
        .mobile-menu.open { display: flex; }

        .mobile-menu-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border-radius: var(--radius-md);
            color: var(--text);
            font-size: 1.05rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        .mobile-menu-link:hover { background: var(--card); }
        .mobile-menu-link svg { width: 22px; height: 22px; color: var(--accent); }
        .mobile-menu-link .label { flex: 1; }
        .mobile-menu-link .arrow { color: var(--text-muted); }

        /* ══════════════════════════════════════════════════
           AD SPACING
           ══════════════════════════════════════════════════ */
        .ad-mt-2 { margin-top: 16px; }
        .ad-mt-4 { margin-top: 24px; }
        .ad-mt-6 { margin-top: 40px; }
        .ad-mb-2 { margin-bottom: 16px; }
        .ad-mb-6 { margin-bottom: 40px; }
        .ad-inline { flex-shrink: 0; }

        /* ══════════════════════════════════════════════════
           PAGE TRANSITIONS
           ══════════════════════════════════════════════════ */
        .page-enter {
            animation: fadeUp 0.4s var(--ease);
        }

        /* ══════════════════════════════════════════════════
           RESPONSIVE
           ══════════════════════════════════════════════════ */
        @media (max-width: 900px) {
            .topnav { padding: 0 24px; }
            .hero-content { padding: 60px 24px 40px; }
            .hero-poster { width: 140px; }
            .hero-title { font-size: 2rem; }
            .section-header { padding: 0 24px; }
            .carousel { padding: 0 24px; }
            .footer-inner { grid-template-columns: 1fr 1fr; gap: 32px; }
            .footer { padding: 40px 24px 20px; }
        }

        @media (max-width: 768px) {
            body { padding-bottom: var(--bottom-nav-height); }
            .topnav { height: 56px; padding: 0 16px; }
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .brand { font-size: 1rem; }
            .hero { height: 60vh; min-height: 400px; margin-bottom: 16px; }
            .hero-content { padding: 48px 16px 32px; gap: 24px; flex-direction: column; align-items: flex-start; }
            .hero-poster { width: 120px; display: none; }
            .hero-title { font-size: 1.6rem; }
            .hero-tagline { font-size: 0.85rem; }
            .poster-card { width: 160px; }
            .cinema-card { width: 240px; }
            .movie-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; padding: 0 16px; }
            .poster-wall { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); padding: 0 16px; }
            .section-header { padding: 0 16px; }
            .carousel { padding: 0 16px; gap: 6px; }
            .chips-row { padding: 0 16px 12px; }
            .search-section { padding: 0 16px; }
            .detail-hero { padding: 24px 16px; }
            .detail-title { font-size: 1.6rem; }
            .showtimes-wrap { padding: 0 16px 32px; }
            .cinema-detail-hero { padding: 24px 16px; }
            .section { margin-bottom: 24px; }
            .hero-search-fallback { padding: 48px 16px; }
            .hero-search-fallback h1 { font-size: 1.6rem; }
            .footer-inner { grid-template-columns: 1fr 1fr; gap: 24px; }
            .footer { padding: 32px 16px 16px; }
        }

        @media (max-width: 480px) {
            .poster-card { width: 120px; }
            .movie-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
            .poster-wall { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
            .cinema-card { width: 200px; padding: 16px; }
        }
    </style>

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Organization",
                "@id": "<?= $siteUrl ?>/#organization",
                "name": "<?= htmlspecialchars(SITE_NAME) ?>",
                "url": "<?= $siteUrl ?>",
                "description": "Lebanon's cinema showtimes guide. Find movies, cinemas, and showtimes across VOX, Grand, Empire, CinemaCity and more.",
                "areaServed": "LB",
                "areaServed": {
                    "@type": "Country",
                    "name": "Lebanon"
                }
            },
            {
                "@type": "WebSite",
                "@id": "<?= $siteUrl ?>/#website",
                "url": "<?= $siteUrl ?>",
                "name": "<?= htmlspecialchars(SITE_NAME) ?>",
                "publisher": { "@id": "<?= $siteUrl ?>/#organization" },
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": {
                        "@type": "EntryPoint",
                        "urlTemplate": "<?= $siteUrl ?>/search?q={search_term_string}"
                    },
                    "query-input": "required name=search_term_string"
                }
            },
            {
                "@type": "BreadcrumbList",
                "@id": "<?= $siteUrl ?>/breadcrumb",
                "itemListElement": [
                    { "@type": "ListItem", "position": 1, "name": "Home", "item": "<?= $siteUrl ?>/" }
                    <?php if (!empty($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $b): ?>,
                    { "@type": "ListItem", "position": <?= (int)($b['pos'] ?? 2) ?>, "name": "<?= htmlspecialchars($b['name']) ?>", "item": "<?= $siteUrl ?>/<?= ltrim($b['url'] ?? '', '/') ?>" }
                    <?php endforeach; ?>
                    <?php endif; ?>
                ]
            }
            <?php foreach ($jsonLd as $item): ?>,
            <?= json_encode($item, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>
            <?php endforeach; ?>
        ]
    }
    </script>

</head>
<body>

<nav class="topnav">
    <a class="brand" href="<?= _link('/') ?>">Lebanon<span>Cinema</span></a>
    <div class="nav-links">
        <a class="nav-link" href="<?= _link('/') ?>">Today</a>
        <a class="nav-link" href="<?= _link('/movies') ?>">Movies</a>
        <a class="nav-link" href="<?= _link('/cinemas') ?>">Cinemas</a>
    </div>
    <div class="nav-right">
        <button class="nav-btn" id="search-btn" aria-label="Search">
            <i data-lucide="search"></i>
        </button>
        <button class="nav-btn" id="theme-toggle" aria-label="Toggle theme">
            <i data-lucide="sun" class="theme-icon-light"></i>
            <i data-lucide="moon" class="theme-icon-dark" style="display:none;"></i>
        </button>
        <button class="hamburger" id="hamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobile-menu">
    <a class="mobile-menu-link" href="<?= _link('/') ?>" data-mobile-link>
        <i data-lucide="calendar"></i>
        <span class="label">Today</span>
        <i data-lucide="chevron-right" class="arrow"></i>
    </a>
    <a class="mobile-menu-link" href="<?= _link('/movies') ?>" data-mobile-link>
        <i data-lucide="film"></i>
        <span class="label">Movies</span>
        <i data-lucide="chevron-right" class="arrow"></i>
    </a>
    <a class="mobile-menu-link" href="<?= _link('/cinemas') ?>" data-mobile-link>
        <i data-lucide="map-pin"></i>
        <span class="label">Cinemas</span>
        <i data-lucide="chevron-right" class="arrow"></i>
    </a>
    <a class="mobile-menu-link" href="<?= _link('/search') ?>" data-mobile-link>
        <i data-lucide="search"></i>
        <span class="label">Search</span>
        <i data-lucide="chevron-right" class="arrow"></i>
    </a>
    <a class="mobile-menu-link" href="<?= _link('/coming-soon') ?>" data-mobile-link>
        <i data-lucide="clock"></i>
        <span class="label">Coming Soon</span>
        <i data-lucide="chevron-right" class="arrow"></i>
    </a>
</div>
