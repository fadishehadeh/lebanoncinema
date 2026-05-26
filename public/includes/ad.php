<?php
/**
 * AdSense placement system with placement-aware runtime config.
 */

function getAdConfig(string $type): array {
    $sizes = [
        'leaderboard'        => ['label' => 'Ads', 'desk' => [728, 90], 'mob' => [320, 50]],
        'mobile-leaderboard' => ['label' => 'Ads', 'desk' => [320, 50], 'mob' => [320, 50]],
        'rectangle'          => ['label' => 'Ads', 'desk' => [336, 280], 'mob' => [300, 250]],
        'large-rectangle'    => ['label' => 'Ads', 'desk' => [336, 280], 'mob' => [300, 250]],
        'skyscraper'         => ['label' => 'Ads', 'desk' => [300, 600], 'mob' => null],
        'in-card'            => ['label' => 'Ads', 'desk' => [160, 240], 'mob' => [120, 200]],
    ];

    return $sizes[$type] ?? $sizes['rectangle'];
}

function getAdPlacementDefinitions(): array {
    return [
        'generic_leaderboard' => ['type' => 'leaderboard', 'label' => 'Generic Leaderboard', 'templates' => 'Fallback / legacy', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => ''],
        'generic_rectangle' => ['type' => 'rectangle', 'label' => 'Generic Rectangle', 'templates' => 'Fallback / legacy', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => ''],
        'generic_skyscraper' => ['type' => 'skyscraper', 'label' => 'Generic Skyscraper', 'templates' => 'Fallback / legacy', 'desktop' => 1, 'mobile' => 0, 'sticky' => 1, 'class' => ''],
        'generic_mobile_leaderboard' => ['type' => 'mobile-leaderboard', 'label' => 'Generic Mobile Leaderboard', 'templates' => 'Fallback / legacy', 'desktop' => 0, 'mobile' => 1, 'sticky' => 0, 'class' => ''],
        'generic_in_card' => ['type' => 'in-card', 'label' => 'Generic In-card', 'templates' => 'Fallback / legacy', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => ''],
        'homepage_top' => ['type' => 'leaderboard', 'label' => 'Homepage Top Leaderboard', 'templates' => 'Homepage below discovery block', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-home-slot ad-mt-2 ad-mb-6'],
        'homepage_trending_inline' => ['type' => 'large-rectangle', 'label' => 'Homepage Trending Inline', 'templates' => 'Homepage after trending grid', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-home-slot ad-inline-break ad-mt-2 ad-mb-4'],
        'homepage_cinemas_break' => ['type' => 'leaderboard', 'label' => 'Homepage Cinemas Break', 'templates' => 'Homepage before cinemas section', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-home-slot ad-mt-2 ad-mb-4'],
        'homepage_footer' => ['type' => 'leaderboard', 'label' => 'Homepage Footer Leaderboard', 'templates' => 'Homepage above footer', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-home-slot ad-mt-6 ad-mb-2'],
        'homepage_grid_inline' => ['type' => 'large-rectangle', 'label' => 'Homepage Grid Inline', 'templates' => 'Homepage trending/showing soon grid insert', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-grid-break'],
        'movie_hero_inline' => ['type' => 'leaderboard', 'label' => 'Movie Above Showtimes', 'templates' => 'Movie detail below hero', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-4'],
        'movie_showtimes_inline' => ['type' => 'rectangle', 'label' => 'Movie Below Showtimes', 'templates' => 'Movie detail below showtimes', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-4 ad-mb-4'],
        'movie_sidebar_sticky' => ['type' => 'skyscraper', 'label' => 'Movie Sticky Rail', 'templates' => 'Movie detail desktop rail', 'desktop' => 1, 'mobile' => 0, 'sticky' => 1, 'class' => 'ad-rail-slot'],
        'cinema_inline' => ['type' => 'leaderboard', 'label' => 'Cinema Mid-page', 'templates' => 'Cinema detail between showtimes and SEO', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-4'],
        'cinema_lower' => ['type' => 'rectangle', 'label' => 'Cinema Lower Inline', 'templates' => 'Cinema detail before nearby cinemas', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-4 ad-mb-2'],
        'movies_grid_inline' => ['type' => 'large-rectangle', 'label' => 'Movies Grid Inline', 'templates' => 'Movies listing grid interval', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-grid-break'],
        'cinemas_chain_inline' => ['type' => 'rectangle', 'label' => 'Cinemas Listing Inline', 'templates' => 'Cinemas listing between chains', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-2'],
        'search_results_inline' => ['type' => 'rectangle', 'label' => 'Search Mid Results', 'templates' => 'Search between movie and cinema groups', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mb-4'],
        'search_lower_inline' => ['type' => 'leaderboard', 'label' => 'Search Lower Inline', 'templates' => 'Search lower results break', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-4'],
        'city_top' => ['type' => 'leaderboard', 'label' => 'City Landing Top', 'templates' => 'City landing after cinema grid', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-4'],
        'city_lower' => ['type' => 'leaderboard', 'label' => 'City Landing Lower', 'templates' => 'City landing above footer', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-4 ad-mb-2'],
        'genre_top' => ['type' => 'leaderboard', 'label' => 'Genre Landing Top', 'templates' => 'Genre landing after movie grid', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-4'],
        'genre_lower' => ['type' => 'leaderboard', 'label' => 'Genre Landing Lower', 'templates' => 'Genre landing above footer', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-4 ad-mb-2'],
        'showtimes_top' => ['type' => 'leaderboard', 'label' => 'Showtimes Top', 'templates' => 'City showtimes after header', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-4'],
        'showtimes_lower' => ['type' => 'rectangle', 'label' => 'Showtimes Lower', 'templates' => 'City showtimes above SEO block', 'desktop' => 1, 'mobile' => 1, 'sticky' => 0, 'class' => 'ad-inline-break ad-mt-2 ad-mb-4'],
        'global_mobile_anchor' => ['type' => 'mobile-leaderboard', 'label' => 'Global Mobile Anchor', 'templates' => 'All public pages above bottom nav', 'desktop' => 0, 'mobile' => 1, 'sticky' => 1, 'class' => 'ad-anchor-slot'],
    ];
}

function getAdConfigFilePath(): string {
    return __DIR__ . '/../admin/ads_config.json';
}

function getAdSettings(): array {
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $definitions = getAdPlacementDefinitions();
    $defaults = [
        'adsense_enabled' => defined('ADSENSE_ENABLED') ? (ADSENSE_ENABLED ? 1 : 0) : 1,
        'adsense_client' => defined('ADSENSE_CLIENT') ? ADSENSE_CLIENT : '',
        'debug_labels' => 0,
        'auto_ads_enabled' => 1,
        'placements' => [],
    ];

    foreach ($definitions as $key => $definition) {
        $defaults['placements'][$key] = [
            'enabled' => 1,
            'slot' => '',
            'desktop' => $definition['desktop'],
            'mobile' => $definition['mobile'],
            'sticky' => $definition['sticky'],
        ];
    }

    $settings = $defaults;
    $configFile = getAdConfigFilePath();
    if (!file_exists($configFile)) {
        return $settings;
    }

    $raw = json_decode(file_get_contents($configFile), true);
    if (!is_array($raw)) {
        return $settings;
    }

    $settings['adsense_enabled'] = isset($raw['adsense_enabled']) ? (int) $raw['adsense_enabled'] : $defaults['adsense_enabled'];
    $settings['adsense_client'] = trim((string) ($raw['adsense_client'] ?? $defaults['adsense_client']));
    $settings['debug_labels'] = isset($raw['debug_labels']) ? (int) $raw['debug_labels'] : 0;
    $settings['auto_ads_enabled'] = isset($raw['auto_ads_enabled']) ? (int) $raw['auto_ads_enabled'] : 1;

    if (isset($raw['placements']) && is_array($raw['placements'])) {
        foreach ($raw['placements'] as $key => $placementConfig) {
            if (!isset($settings['placements'][$key]) || !is_array($placementConfig)) {
                continue;
            }
            $settings['placements'][$key]['enabled'] = isset($placementConfig['enabled']) ? (int) $placementConfig['enabled'] : $settings['placements'][$key]['enabled'];
            $settings['placements'][$key]['slot'] = trim((string) ($placementConfig['slot'] ?? $settings['placements'][$key]['slot']));
            $settings['placements'][$key]['desktop'] = isset($placementConfig['desktop']) ? (int) $placementConfig['desktop'] : $settings['placements'][$key]['desktop'];
            $settings['placements'][$key]['mobile'] = isset($placementConfig['mobile']) ? (int) $placementConfig['mobile'] : $settings['placements'][$key]['mobile'];
            $settings['placements'][$key]['sticky'] = isset($placementConfig['sticky']) ? (int) $placementConfig['sticky'] : $settings['placements'][$key]['sticky'];
        }
    }

    foreach ($definitions as $key => $definition) {
        $legacySlotKey = 'slot_' . $definition['type'];
        if (empty($settings['placements'][$key]['slot']) && !empty($raw[$legacySlotKey])) {
            $settings['placements'][$key]['slot'] = trim((string) $raw[$legacySlotKey]);
        }
    }

    return $settings;
}

function canShowAdDebugLabels(): bool {
    static $allowed = null;
    if ($allowed !== null) {
        return $allowed;
    }

    $settings = getAdSettings();
    if (!empty($settings['debug_labels'])) {
        $allowed = true;
        return $allowed;
    }

    if (empty($_GET['ad_debug'])) {
        $allowed = false;
        return $allowed;
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $allowed = !empty($_SESSION['admin_logged_in']);
    return $allowed;
}

function getAdPlacementRuntime(string $type, array $options): array {
    $definitions = getAdPlacementDefinitions();
    $settings = getAdSettings();
    $placementKey = trim((string) ($options['placement'] ?? ''));

    if ($placementKey === '') {
        $fallbackMap = [
            'leaderboard' => 'generic_leaderboard',
            'rectangle' => 'generic_rectangle',
            'large-rectangle' => 'generic_rectangle',
            'skyscraper' => 'generic_skyscraper',
            'mobile-leaderboard' => 'generic_mobile_leaderboard',
            'in-card' => 'generic_in_card',
        ];
        $placementKey = $fallbackMap[$type] ?? 'generic_rectangle';
    }

    if (!isset($definitions[$placementKey])) {
        $placementKey = 'generic_rectangle';
    }

    $definition = $definitions[$placementKey];
    $placementConfig = $settings['placements'][$placementKey] ?? [];
    $config = getAdConfig($definition['type']);

    $slot = trim((string) ($options['slot'] ?? ($placementConfig['slot'] ?? '')));
    if ($slot === '') {
        $legacySlotKey = 'slot_' . $definition['type'];
        $slot = trim((string) ($settings[$legacySlotKey] ?? ''));
    }

    return [
        'placement' => $placementKey,
        'type' => $definition['type'],
        'label' => $definition['label'],
        'templates' => $definition['templates'],
        'desktop' => isset($options['desktop']) ? (int) $options['desktop'] : (int) ($placementConfig['desktop'] ?? $definition['desktop']),
        'mobile' => isset($options['mobile']) ? (int) $options['mobile'] : (int) ($placementConfig['mobile'] ?? $definition['mobile']),
        'sticky' => isset($options['sticky']) ? (int) $options['sticky'] : (int) ($placementConfig['sticky'] ?? $definition['sticky']),
        'enabled' => isset($options['enabled']) ? (int) $options['enabled'] : (int) ($placementConfig['enabled'] ?? 1),
        'class' => trim(($definition['class'] ?? '') . ' ' . ($options['class'] ?? '')),
        'slot' => $slot,
        'client' => trim((string) ($settings['adsense_client'] ?? (defined('ADSENSE_CLIENT') ? ADSENSE_CLIENT : ''))),
        'globally_enabled' => !empty($settings['adsense_enabled']) && (!defined('ADSENSE_ENABLED') || ADSENSE_ENABLED),
        'debug' => canShowAdDebugLabels(),
        'config' => $config,
    ];
}

function renderAdAssetsOnce(): void {
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
    ?>
<style>
    .ad-slot { margin: 0 auto; position: relative; }
    .ad-slot.is-hidden { display: none !important; }
    .ad-slot.ad-home-slot { max-width: 1320px; padding: 0 48px; }
    .ad-slot.ad-inline-break { max-width: 1200px; padding: 0 24px; }
    .ad-slot.ad-grid-break { grid-column: 1 / -1; max-width: none; width: 100%; }
    .ad-slot.ad-rail-slot { width: 300px; justify-self: center; }
    .ad-slot.ad-anchor-slot {
        position: fixed;
        left: 50%;
        transform: translateX(-50%);
        bottom: calc(var(--bottom-nav-height) + 10px);
        z-index: 980;
        width: calc(100% - 20px);
        max-width: 340px;
        padding: 0;
    }
    .ad-slot.ad-anchor-slot .ad-frame { border-radius: 16px; box-shadow: 0 14px 30px rgba(0,0,0,0.35); }
    .ad-frame {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        width: 100%;
        min-height: 50px;
        border-radius: 18px;
        overflow: hidden;
        background: transparent;
    }
    .ad-frame.is-shell {
        border: 1px solid rgba(255,255,255,0.06);
        background: linear-gradient(180deg, rgba(12,12,12,0.96), rgba(8,8,8,0.96));
    }
    .ad-frame.is-sticky {
        position: sticky;
        top: 84px;
    }
    .ad-frame.is-lazy:not(.is-loaded)::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(255,255,255,0.03), rgba(255,255,255,0.06), rgba(255,255,255,0.03));
        opacity: 0.35;
        pointer-events: none;
    }
    .ad-frame .adsbygoogle {
        display: block;
        margin: 0 auto;
    }
    .ad-layout-rail {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 24px 32px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        gap: 24px;
        align-items: start;
    }
    .ad-layout-main > .showtimes-wrap,
    .ad-layout-main > .seo-summary {
        max-width: none;
        margin-left: 0;
        margin-right: 0;
        padding-left: 0;
        padding-right: 0;
    }
    .ad-debug-label {
        position: absolute;
        top: 6px;
        left: 8px;
        z-index: 3;
        padding: 4px 8px;
        border-radius: 999px;
        background: rgba(0,0,0,0.75);
        color: #ff8f98;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        pointer-events: none;
    }
    .ad-placeholder-copy {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        color: rgba(255,255,255,0.35);
        font-size: 0.74rem;
        font-weight: 600;
    }
    .ad-size-leaderboard { width: 728px; max-width: 100%; min-height: 90px; height: 90px; }
    .ad-size-mobile-leaderboard { width: 320px; max-width: 100%; min-height: 50px; height: 50px; }
    .ad-size-rectangle, .ad-size-large-rectangle { width: 336px; max-width: 100%; min-height: 280px; }
    .ad-size-skyscraper { width: 300px; max-width: 100%; min-height: 600px; }
    .ad-size-in-card { width: 160px; max-width: 100%; min-height: 240px; }
    @media (max-width: 900px) {
        .ad-slot.ad-home-slot { padding: 0 24px; }
        .ad-layout-rail { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .ad-slot[data-mobile="0"] { display: none !important; }
        .ad-slot.ad-home-slot, .ad-slot.ad-inline-break { padding: 0 16px; }
        .ad-slot.ad-rail-slot { display: none !important; }
        .ad-layout-rail { padding: 0 16px 24px; }
        .ad-size-leaderboard { width: 320px; height: 50px; min-height: 50px; }
        .ad-size-rectangle, .ad-size-large-rectangle { width: 300px; min-height: 250px; }
        .ad-size-skyscraper { display: none; }
        .ad-size-in-card { width: 120px; min-height: 200px; }
    }
    @media (min-width: 769px) {
        .ad-slot[data-desktop="0"] { display: none !important; }
        .ad-slot.ad-anchor-slot { display: none !important; }
    }
</style>
<script>
(() => {
    if (window.__lcAdsInit) {
        return;
    }
    window.__lcAdsInit = true;

    const loadAd = (slot) => {
        if (!slot || slot.dataset.adLoaded === '1') {
            return;
        }
        const ins = slot.querySelector('.adsbygoogle');
        if (!ins) {
            return;
        }
        try {
            (window.adsbygoogle = window.adsbygoogle || []).push({});
            slot.dataset.adLoaded = '1';
            const frame = slot.querySelector('.ad-frame');
            if (frame) {
                frame.classList.add('is-loaded');
            }
        } catch (error) {
            console.error('Ad init failed', error);
        }
    };

    const registerAds = () => {
        const slots = Array.from(document.querySelectorAll('[data-ad-slot-root="1"]'));
        if (!('IntersectionObserver' in window)) {
            slots.forEach(loadAd);
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }
                loadAd(entry.target);
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '220px 0px' });
        slots.forEach((slot) => observer.observe(slot));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerAds);
    } else {
        registerAds();
    }
})();
</script>
    <?php
}

function renderAdPlaceholder(array $runtime, string $reason = 'preview'): void {
    $placement = htmlspecialchars($runtime['placement']);
    $type = htmlspecialchars($runtime['type']);
    $classes = htmlspecialchars(trim('ad-slot ' . $runtime['class']));
    $sizeClass = 'ad-size-' . $type;
    renderAdAssetsOnce();
    ?>
<div class="<?= $classes ?>" data-ad-placement="<?= $placement ?>" data-ad-type="<?= $type ?>">
    <div class="ad-frame is-shell <?= $sizeClass ?>">
        <?php if ($runtime['debug']): ?>
        <span class="ad-debug-label"><?= $placement ?><?= $reason ? ' · ' . htmlspecialchars($reason) : '' ?></span>
        <?php endif; ?>
        <div class="ad-placeholder-copy">
            <span>Ad Space</span>
            <span><?= htmlspecialchars($runtime['label']) ?></span>
        </div>
    </div>
</div>
    <?php
}

function renderAdsenseAd(array $runtime): void {
    $type = $runtime['type'];
    $config = $runtime['config'];
    $classes = trim('ad-slot ' . $runtime['class']);
    $sizeClass = 'ad-size-' . $type;
    $deskWidth = $config['desk'][0] ?? null;
    $deskHeight = $config['desk'][1] ?? null;
    $inlineStyle = '';
    if ($deskWidth && $deskHeight) {
        $inlineStyle = 'width:' . (int) $deskWidth . 'px;height:' . (int) $deskHeight . 'px;';
    }

    renderAdAssetsOnce();
    ?>
<div class="<?= htmlspecialchars($classes) ?>"
     data-ad-placement="<?= htmlspecialchars($runtime['placement']) ?>"
     data-ad-type="<?= htmlspecialchars($type) ?>"
     data-desktop="<?= (int) $runtime['desktop'] ?>"
     data-mobile="<?= (int) $runtime['mobile'] ?>"
     data-ad-slot-root="1"
     <?= !$runtime['mobile'] ? 'data-no-mobile="1"' : '' ?>>
    <div class="ad-frame is-lazy <?= $runtime['sticky'] ? 'is-sticky' : '' ?> <?= $sizeClass ?>">
        <?php if ($runtime['debug']): ?>
        <span class="ad-debug-label"><?= htmlspecialchars($runtime['placement']) ?></span>
        <?php endif; ?>
        <ins class="adsbygoogle"
             style="<?= htmlspecialchars($inlineStyle) ?>"
             data-ad-client="<?= htmlspecialchars($runtime['client']) ?>"
             data-ad-slot="<?= htmlspecialchars($runtime['slot']) ?>"
             data-ad-format=""
             data-full-width-responsive="false"></ins>
    </div>
</div>
    <?php
}

function renderAd(string $type = 'leaderboard', $options = []): void {
    if (is_string($options)) {
        $options = ['class' => $options];
    }

    $runtime = getAdPlacementRuntime($type, $options);
    $isGloballyEnabled = !empty($runtime['globally_enabled']);
    $hasSlot = $runtime['slot'] !== '';

    if (!$runtime['enabled']) {
        if ($runtime['debug']) {
            renderAdPlaceholder($runtime, 'disabled');
        }
        return;
    }

    if (!$runtime['desktop'] && !$runtime['mobile']) {
        return;
    }

    if (!$isGloballyEnabled) {
        renderAdPlaceholder($runtime, 'ads-off');
        return;
    }

    if (!$hasSlot) {
        renderAdPlaceholder($runtime, 'no-slot');
        return;
    }

    renderAdsenseAd($runtime);
}
