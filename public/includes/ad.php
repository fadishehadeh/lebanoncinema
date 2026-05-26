<?php
/**
 * Premium AdSense placement system.
 *
 * Usage:
 *   renderAd('leaderboard')           // 728x90 responsive
 *   renderAd('rectangle')             // 300x250 / 336x280
 *   renderAd('skyscraper')            // 300x600 (desktop only)
 *   renderAd('mobile-leaderboard')    // 320x50 (mobile only)
 *   renderAd('in-card')               // Inline carousel card
 *   renderAd('leaderboard', ['class' => 'mt-6 mb-4'])
 *
 * Options:
 *   class    — extra CSS classes
 *   slot     — AdSense ad slot ID (overrides default)
 *   sticky   — enable sticky mode for skyscraper
 *   lazy     — delay rendering until viewport
 */

// Default AdSense slot IDs — override per placement
$AD_SLOTS = [
    'leaderboard'       => '',
    'mobile-leaderboard'=> '',
    'rectangle'         => '',
    'large-rectangle'   => '',
    'skyscraper'        => '',
    'in-card'           => '',
];

function renderAd(string $type = 'leaderboard', $options = []): void {
    // Backward compatibility: if $options is a string, treat it as class names
    if (is_string($options)) {
        $options = ['class' => $options];
    }
    if (!defined('ADSENSE_ENABLED') || !ADSENSE_ENABLED) {
        renderAdPlaceholder($type, $options);
        return;
    }
    renderAdsenseAd($type, $options);
}

function renderAdPlaceholder(string $type, array $options): void {
    $config = getAdConfig($type);
    $id = 'ad-' . uniqid();
    $classes = $options['class'] ?? '';
?>
<div class="ad-wrap <?= htmlspecialchars($classes) ?>" id="<?= $id ?>" data-ad-type="<?= $type ?>">
    <div class="ad-inner ad-<?= $type ?>">
        <span class="ad-label">Ads</span>
        <div class="ad-skelly">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <span>Ad Space</span>
        </div>
    </div>
</div>
<style>
    #<?= $id ?> { margin:0 auto; }
    #<?= $id ?> .ad-inner { position:relative; display:flex; flex-direction:column; align-items:center; justify-content:center; border-radius:var(--radius-lg);     background:#E50914; border:1px solid rgba(229,9,20,0.1); overflow:hidden; transition:border-color .35s var(--ease), box-shadow .35s var(--ease); }
    #<?= $id ?> .ad-inner:hover { border-color:rgba(229,9,20,0.2); box-shadow:0 0 30px rgba(229,9,20,0.08); }
    #<?= $id ?> .ad-label { position:absolute; top:6px; left:10px; font-size:.5rem; text-transform:uppercase; letter-spacing:.12em; color:var(--accent); opacity:.5; font-weight:700; font-family:'Inter',sans-serif; }
    #<?= $id ?> .ad-skelly { display:flex; flex-direction:column; align-items:center; gap:6px; color:#fff; opacity:.25; font-size:.7rem; }
    #<?= $id ?> .ad-skelly svg { opacity:.4; }
    #<?= $id ?> .ad-leaderboard { width:728px; max-width:100%; height:90px; padding:4px 24px; margin:0 auto; }
    #<?= $id ?> .ad-mobile-leaderboard { width:100%; max-width:320px; min-height:50px; padding:16px; margin:0 auto; display:block; }
    #<?= $id ?> .ad-rectangle { width:100%; max-width:336px; min-height:250px; padding:24px; margin:0 auto; }
    #<?= $id ?> .ad-large-rectangle { width:100%; max-width:336px; min-height:280px; padding:24px; margin:0 auto; }
    #<?= $id ?> .ad-skyscraper { width:100%; max-width:300px; min-height:600px; padding:24px; margin:0 auto; }
    #<?= $id ?> .ad-in-card { width:160px; height:240px; padding:16px; flex-shrink:0; scroll-snap-align:start; }
    @media (max-width:768px) {
        #<?= $id ?> .ad-leaderboard { min-height:50px; padding:12px; }
        #<?= $id ?> .ad-rectangle, #<?= $id ?> .ad-large-rectangle { min-height:200px; padding:16px; max-width:100%; }
        #<?= $id ?> .ad-skyscraper { display:none; }
        #<?= $id ?> .ad-in-card { width:120px; height:200px; }
    }
    @media (min-width:769px) { #<?= $id ?> .ad-mobile-leaderboard { display:none; } }
    <?php if (!empty($options['sticky'])): ?>
    @media (min-width:1400px) { #<?= $id ?> .ad-inner { position:sticky; top:80px; } }
    <?php endif; ?>
</style>
<?php
}

function renderAdsenseAd(string $type, array $options): void {
    global $AD_SLOTS;
    $config = getAdConfig($type);
    $slot = $options['slot'] ?? ($AD_SLOTS[$type] ?? '');
    $client = defined('ADSENSE_CLIENT') ? ADSENSE_CLIENT : '';
    $id = 'ad-' . uniqid();
    $classes = $options['class'] ?? '';
?>
<div class="ad-wrap <?= htmlspecialchars($classes) ?>" id="<?= $id ?>-wrap">
    <div class="ad-inner ad-<?= $type ?>">
        <span class="ad-label">Ads</span>
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="<?= htmlspecialchars($client) ?>"
             data-ad-slot="<?= htmlspecialchars($slot) ?>"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>
</div>
<style>
    #<?= $id ?>-wrap { margin:0 auto; }
    #<?= $id ?>-wrap .ad-inner { position:relative; display:flex; flex-direction:column; align-items:center; justify-content:center; border-radius:var(--radius-lg);     background:#E50914; border:1px solid rgba(229,9,20,0.1); overflow:hidden; min-height:50px; transition:border-color .35s var(--ease), box-shadow .35s var(--ease); }
    #<?= $id ?>-wrap .ad-inner:hover { border-color:rgba(229,9,20,0.2); box-shadow:0 0 30px rgba(229,9,20,0.08); }
    #<?= $id ?>-wrap .ad-label { position:absolute; top:4px; left:8px; font-size:.5rem; text-transform:uppercase; letter-spacing:.12em; color:var(--accent); opacity:.45; font-weight:700; z-index:1; font-family:'Inter',sans-serif; }
    .ad-leaderboard { width:728px; max-width:100%; height:90px; margin:0 auto; }
    .ad-mobile-leaderboard { width:100%; max-width:320px; min-height:50px; margin:0 auto; }
    .ad-rectangle { width:100%; max-width:336px; min-height:250px; margin:0 auto; }
    .ad-large-rectangle { width:100%; max-width:336px; min-height:280px; margin:0 auto; }
    .ad-skyscraper { width:100%; max-width:300px; min-height:600px; margin:0 auto; }
    .ad-in-card { width:160px; height:240px; flex-shrink:0; scroll-snap-align:start; }
    .ad-wrap .adsbygoogle { width:100%; height:100%; min-height:inherit; }
    @media (max-width:768px) {
        .ad-leaderboard { min-height:50px; }
        .ad-rectangle, .ad-large-rectangle { max-width:100%; min-height:200px; }
        .ad-skyscraper { display:none; }
        .ad-in-card { width:120px; height:200px; }
    }
    @media (min-width:769px) { .ad-mobile-leaderboard { display:none; } }
    <?php if (!empty($options['sticky'])): ?>
    @media (min-width:1400px) { .ad-skyscraper { position:sticky; top:80px; } }
    <?php endif; ?>
</style>
<?php
}

function getAdConfig(string $type): array {
    $sizes = [
        'leaderboard'        => ['label'=>'Ads', 'desk'=>[728,90], 'mob'=>[320,50]],
        'mobile-leaderboard' => ['label'=>'Ads', 'desk'=>[320,50], 'mob'=>[320,50]],
        'rectangle'          => ['label'=>'Ads', 'desk'=>[336,280], 'mob'=>[300,250]],
        'large-rectangle'    => ['label'=>'Ads', 'desk'=>[336,280], 'mob'=>[300,250]],
        'skyscraper'         => ['label'=>'Ads', 'desk'=>[300,600], 'mob'=>null],
        'in-card'            => ['label'=>'Ads', 'desk'=>[160,240], 'mob'=>[120,200]],
    ];
    return $sizes[$type] ?? $sizes['rectangle'];
}
