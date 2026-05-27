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
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-HWSXMBHVRG"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-HWSXMBHVRG');
    </script>
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
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" as="style" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <link rel="alternate" hreflang="en" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($canonicalUrl) ?>">

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
            padding-top: var(--nav-height);
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
            height: calc(72vh + var(--nav-height));
            min-height: calc(560px + var(--nav-height));
            display: flex;
            align-items: flex-end;
            overflow: hidden;
            margin-top: calc(var(--nav-height) * -1);
            margin-bottom: 10px;
            background: radial-gradient(circle at top center, rgba(229,9,20,0.16), transparent 42%);
        }

        .hero-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.7s ease;
            z-index: 0;
            display: flex;
            align-items: flex-end;
        }
        .hero-slide.active {
            opacity: 1;
            z-index: 1;
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
            filter: saturate(0.95);
        }
        .hero-backdrop::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(5,5,5,0.92) 0%, rgba(5,5,5,0.68) 34%, rgba(5,5,5,0.2) 68%, rgba(5,5,5,0.8) 100%),
                linear-gradient(180deg, rgba(5,5,5,0.28) 0%, rgba(5,5,5,0.82) 78%, rgba(5,5,5,1) 100%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 280px;
            align-items: end;
            gap: 32px;
            max-width: 1320px;
            margin: 0 auto;
            padding: 108px 48px 54px;
            width: 100%;
        }

        .hero-stage {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 28px;
            align-items: end;
            padding: 26px;
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(8,8,8,0.42), rgba(8,8,8,0.76));
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 24px 90px rgba(0,0,0,0.45);
            backdrop-filter: blur(10px);
        }

        .hero-poster {
            flex-shrink: 0;
            width: 100%;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(0,0,0,0.55);
            background: rgba(255,255,255,0.03);
        }
        .hero-poster img {
            width: 100%;
            display: block;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        .hero-poster-fallback {
            width: 100%;
            aspect-ratio: 2/3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.4rem;
            color: rgba(255,255,255,0.55);
            background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
        }

        .hero-info {
            min-width: 0;
            max-width: 620px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(229,9,20,0.12);
            border: 1px solid rgba(229,9,20,0.24);
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 700;
            color: #ffd2d5;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 16px;
        }

        .hero-title {
            font-size: clamp(2.4rem, 4.7vw, 4.4rem);
            margin-bottom: 12px;
            letter-spacing: -0.04em;
            line-height: 0.95;
            max-width: 11ch;
        }

        .hero-tagline {
            font-size: 0.98rem;
            color: rgba(255,255,255,0.78);
            margin-bottom: 18px;
            max-width: 56ch;
            line-height: 1.65;
        }

        .hero-meta {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 22px;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.76);
            flex-wrap: wrap;
        }
        .hero-meta span {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .hero-ctas {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

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
            margin-top: 18px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .hero-quick-times .label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.66);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 600;
        }
        .hero-time-chip {
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            font-size: 0.76rem;
            font-weight: 500;
            transition: all 0.2s;
            color: var(--text);
        }
        .hero-time-chip:hover {
            border-color: var(--accent);
            background: rgba(229,9,20,0.08);
        }

        .hero-queue {
            display: grid;
            gap: 10px;
            align-self: stretch;
        }
        .hero-queue-item {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 12px;
            align-items: center;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(8,8,8,0.55);
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            text-align: left;
            transition: all 0.25s var(--ease);
            backdrop-filter: blur(10px);
        }
        .hero-queue-item:hover,
        .hero-queue-item.active {
            border-color: rgba(229,9,20,0.32);
            background: rgba(20,20,20,0.86);
            color: var(--text);
            transform: translateY(-2px);
        }
        .hero-queue-rank {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.42);
        }
        .hero-queue-copy {
            min-width: 0;
            display: grid;
            gap: 3px;
        }
        .hero-queue-title {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .hero-queue-meta {
            color: rgba(255,255,255,0.5);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .hero-dots {
            position: absolute;
            bottom: 18px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            gap: 8px;
        }
        .hero-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.45);
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0;
        }
        .hero-dot.active {
            background: var(--accent);
            border-color: var(--accent);
        }
        .hero-dot:hover {
            border-color: #fff;
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
        .home-discovery-shell {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 48px 20px;
        }

        .search-section {
            max-width: 1280px;
            margin: 0 auto 18px;
            padding: 0 24px;
        }

        .search-section-home {
            display: grid;
            gap: 20px;
            padding: 28px;
            border-radius: 26px;
            background: linear-gradient(180deg, rgba(15,15,15,0.96), rgba(10,10,10,0.96));
            border: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 20px 50px rgba(0,0,0,0.32);
        }

        .search-section-copy h2 {
            font-size: clamp(1.35rem, 2.2vw, 2rem);
            margin-bottom: 8px;
        }
        .search-section-copy p {
            color: var(--text-muted);
            max-width: 56ch;
        }
        .section-kicker {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #ff8f98;
            font-weight: 700;
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
            padding: 0;
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            justify-content: flex-start;
        }
        .chips-row::-webkit-scrollbar { display: none; }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 999px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.72);
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
            color: #fff;
            font-weight: 600;
        }

        /* ══════════════════════════════════════════════════
           SECTION / CAROUSEL
           ══════════════════════════════════════════════════ */
        .section {
            margin-bottom: 34px;
            position: relative;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 0 48px;
            margin-bottom: 18px;
            gap: 16px;
        }

        .section-title {
            font-size: 1.22rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .section-link {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.58);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
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
            gap: 18px;
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
            border-radius: 18px;
            overflow: hidden;
            background: #111;
            scroll-snap-align: start;
            cursor: pointer;
            transition: all 0.3s var(--ease);
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 18px 36px rgba(0,0,0,0.24);
        }
        @media (min-width: 1400px) { .poster-card { width: 100%; } }
        @media (min-width: 1800px) { .poster-card { width: 100%; } }
        .poster-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 45px rgba(0,0,0,0.42);
            border-color: rgba(255,255,255,0.12);
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
            transform: scale(1.04);
        }

        .poster-card-overlay {
            position: absolute;
            inset: auto 0 0 0;
            background: linear-gradient(to top,
                rgba(6,6,6,0.96) 0%,
                rgba(6,6,6,0.72) 62%,
                rgba(6,6,6,0.04) 100%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 40px 14px 14px;
            opacity: 1;
            transition: transform 0.25s var(--ease);
            z-index: 2;
            pointer-events: none;
        }
        .poster-card:hover .poster-card-overlay {
            transform: translateY(-2px);
        }
        .poster-card-overlay .poster-card-title {
            font-size: 0.92rem;
            font-weight: 700;
            margin-bottom: 5px;
            line-height: 1.2;
        }
        .poster-card-overlay .poster-card-meta {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.68);
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
            font-size: 0.92rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .poster-card-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .poster-card-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 10px;
            border-radius: 999px;
            z-index: 2;
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
        }
        .poster-card-badge.accent {
            background: var(--accent);
            color: #fff;
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
            gap: 14px;
            overflow-x: auto;
            padding: 0 24px 8px;
            scrollbar-width: none;
        }
        .cinema-scroll::-webkit-scrollbar { display: none; }

        .cinema-card {
            flex-shrink: 0;
            width: 308px;
            padding: 20px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(17,17,17,0.98) 0%, rgba(10,10,10,0.98) 100%);
            border: 1px solid rgba(255,255,255,0.06);
            transition: all 0.35s var(--ease);
            cursor: pointer;
            scroll-snap-align: start;
        }
        .cinema-card:hover {
            border-color: rgba(229,9,20,0.18);
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.5), 0 0 30px rgba(229,9,20,0.06);
        }

        .cinema-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }
        .cinema-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .cinema-card-chain {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255,255,255,0.5);
            font-weight: 700;
        }
        .cinema-card-name {
            font-size: 1rem;
            font-weight: 700;
            flex: 1;
        }
        .cinema-card-detail {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            min-height: 20px;
        }
        .cinema-card-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 12px;
        }
        .cinema-stat-value {
            display: block;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.25rem;
            line-height: 1;
        }
        .cinema-stat-label {
            display: block;
            margin-top: 4px;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.46);
        }
        .cinema-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.58);
            font-weight: 700;
        }
        .cinema-card-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .mini-badge {
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.58rem;
            font-weight: 700;
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
        }
        .cinema-card-next {
            font-size: 0.68rem;
            color: #ffd2d5;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(229,9,20,0.14);
            border: 1px solid rgba(229,9,20,0.18);
            font-weight: 600;
            white-space: nowrap;
        }

        /* ══════════════════════════════════════════════════
           POSTER WALL (Upcoming)
           ══════════════════════════════════════════════════ */
        .poster-wall {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
            gap: 14px;
            padding: 0 24px;
        }

        .wall-card {
            border-radius: 16px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(17,17,17,0.98), rgba(10,10,10,0.98));
            cursor: pointer;
            transition: all 0.35s var(--ease);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .wall-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.5), 0 0 30px rgba(255,45,111,0.05);
            border-color: rgba(255,255,255,0.12);
        }
        .wall-card img {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            display: block;
            transition: transform 0.5s var(--ease);
        }
        .wall-card:hover img { transform: scale(1.04); }
        .wall-card-fallback {
            width: 100%;
            aspect-ratio: 2/3;
            background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.55);
            font-size: 2rem;
        }
        .wall-card-info {
            padding: 11px 12px 12px;
        }
        .wall-card-title {
            font-size: 0.8rem;
            font-weight: 700;
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

        .detail-info {
            flex: 1;
            min-width: 0;
        }

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

        .detail-cast {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(72px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
            max-width: 640px;
        }
        .detail-cast-member {
            text-align: center;
            min-width: 0;
        }
        .detail-cast-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 6px;
            border: 2px solid var(--border);
            display: block;
        }
        .detail-cast-avatar-fallback {
            background: var(--card);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .detail-cast-name {
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 1.25;
            word-break: break-word;
        }
        .detail-cast-character {
            font-size: 0.6rem;
            color: var(--text-muted);
            line-height: 1.25;
            word-break: break-word;
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
            justify-content: center;
        }
        .genre-strip::-webkit-scrollbar { display: none; }
        @media (max-width: 768px) { .genre-strip { justify-content: flex-start; } }

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
        .ad-home-slot {
            max-width: 1320px;
            padding: 0 48px;
        }

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
            .hero-content { grid-template-columns: 1fr; padding: 92px 24px 44px; }
            .hero-stage { grid-template-columns: 180px minmax(0, 1fr); gap: 22px; padding: 22px; }
            .hero-title { font-size: 2.4rem; }
            .home-discovery-shell { padding: 0 24px 18px; }
            .section-header { padding: 0 24px; }
            .carousel { padding: 0 24px; }
            .footer-inner { grid-template-columns: 1fr 1fr; gap: 32px; }
            .footer { padding: 40px 24px 20px; }
            .ad-home-slot { padding: 0 24px; }
        }

        @media (max-width: 768px) {
            body { padding-bottom: var(--bottom-nav-height); }
            .topnav { height: 56px; padding: 0 16px; }
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .brand { font-size: 1rem; }
            .hero { height: auto; min-height: calc(520px + var(--nav-height)); margin-bottom: 12px; margin-top: calc(var(--nav-height) * -1); }
            .hero-content { grid-template-columns: 1fr; padding: 82px 16px 48px; gap: 18px; }
            .hero-stage { grid-template-columns: 1fr; gap: 18px; padding: 18px; border-radius: 22px; }
            .hero-poster { width: min(220px, 48vw); margin: 0 auto; }
            .hero-info { max-width: none; }
            .hero-title { font-size: 2rem; max-width: none; }
            .hero-tagline { font-size: 0.9rem; max-width: none; }
            .hero-meta { gap: 8px; margin-bottom: 18px; }
            .hero-ctas { width: 100%; }
            .hero-ctas .btn { justify-content: center; flex: 1 1 180px; }
            .hero-queue { grid-auto-flow: column; grid-auto-columns: minmax(220px, 1fr); overflow-x: auto; padding-bottom: 4px; }
            .hero-queue-item { min-width: 220px; }
            .hero-dots { bottom: 12px; }
            .poster-card { width: 160px; }
            .cinema-card { width: 268px; }
            .movie-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; padding: 0 16px; }
            .poster-wall { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); padding: 0 16px; }
            .section-header { padding: 0 16px; }
            .carousel { padding: 0 16px; gap: 6px; }
            .home-discovery-shell { padding: 0 16px 16px; }
            .search-section { padding: 0; }
            .search-section-home { padding: 20px; border-radius: 22px; }
            .chips-row { padding: 0; }
            .detail-hero { padding: 24px 16px; gap: 20px; flex-direction: column; align-items: stretch; }
            .detail-poster { width: min(220px, 56vw); margin: 0 auto; }
            .detail-info { width: 100%; }
            .detail-title { font-size: 1.6rem; }
            .detail-synopsis { max-width: none; }
            .detail-cast { grid-template-columns: repeat(3, minmax(0, 1fr)); max-width: none; }
            .showtimes-wrap { padding: 0 16px 32px; }
            .cinema-detail-hero { padding: 24px 16px; }
            .section { margin-bottom: 24px; }
            .hero-search-fallback { padding: 48px 16px; }
            .hero-search-fallback h1 { font-size: 1.6rem; }
            .footer-inner { grid-template-columns: 1fr 1fr; gap: 24px; }
            .footer { padding: 32px 16px 16px; }
            .ad-home-slot { padding: 0 16px; }
        }

        @media (max-width: 480px) {
            .hero-title { font-size: 1.72rem; }
            .hero-stage { padding: 16px; }
            .hero-poster { width: min(190px, 58vw); }
            .hero-queue-item { min-width: 200px; }
            .poster-card { width: 120px; }
            .movie-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
            .poster-wall { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
            .cinema-card { width: 220px; padding: 16px; }
            .detail-cast { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
            },
            {
                "@type": "SpeakableSpecification",
                "cssSelector": [
                    ".hero-title",
                    ".hero-tagline",
                    ".section-title",
                    ".seo-summary"
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
