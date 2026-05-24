<?php
/**
 * Reusable ad placement component.
 * Renders dark-themed AdSense-ready containers.
 *
 * Usage: renderAd('leaderboard')   // 728x90 leaderboard
 *        renderAd('rectangle')     // 300x250 rectangle
 *        renderAd('card')          // Inline sponsored card
 *        renderAd('leaderboard', 'mt-6 mb-6') // with extra spacing
 */
function renderAd(string $type = 'leaderboard', string $classes = ''): void {
    $ads = [
        'leaderboard' => [
            'class' => 'ad-leaderboard',
            'label' => 'Advertisement',
            'height_desk' => 90,
            'height_mob' => 80,
        ],
        'rectangle' => [
            'class' => 'ad-rectangle',
            'label' => 'Sponsored',
            'height_desk' => 250,
            'height_mob' => 200,
        ],
        'card' => [
            'class' => 'ad-card',
            'label' => 'Sponsored',
            'height_desk' => 200,
            'height_mob' => 160,
        ],
    ];

    $a = $ads[$type] ?? $ads['leaderboard'];
    $id = 'ad-' . uniqid();
?>
<div class="ad-container <?= htmlspecialchars($classes) ?>" id="<?= $id ?>">
    <div class="ad-inner ad-<?= $type ?>">
        <span class="ad-label"><?= htmlspecialchars($a['label']) ?></span>
        <div class="ad-placeholder">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
            <span>Ad Space Available</span>
        </div>
    </div>
</div>

<style>
    #<?= $id ?> {
        margin: 0 auto;
    }
    #<?= $id ?> .ad-inner {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
        background: var(--card);
        border: 1px solid var(--border);
        overflow: hidden;
        transition: border-color 0.2s;
    }
    #<?= $id ?> .ad-inner:hover {
        border-color: var(--border-strong);
    }
    #<?= $id ?> .ad-label {
        position: absolute;
        top: 6px;
        left: 10px;
        font-size: 0.55rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--text-muted);
        opacity: 0.5;
        font-weight: 600;
    }
    #<?= $id ?> .ad-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        color: var(--text-muted);
        opacity: 0.3;
        font-size: 0.75rem;
    }
    #<?= $id ?> .ad-placeholder svg {
        opacity: 0.5;
    }

    /* Leaderboard */
    #<?= $id ?> .ad-leaderboard {
        width: 100%;
        min-height: 90px;
        padding: 24px;
    }
    @media (max-width: 768px) {
        #<?= $id ?> .ad-leaderboard { min-height: 80px; padding: 16px; }
    }

    /* Rectangle */
    #<?= $id ?> .ad-rectangle {
        width: 100%;
        max-width: 336px;
        min-height: 250px;
        padding: 24px;
        margin: 0 auto;
    }
    @media (max-width: 768px) {
        #<?= $id ?> .ad-rectangle { min-height: 200px; padding: 16px; max-width: 100%; }
    }

    /* Card */
    #<?= $id ?> .ad-card {
        width: 160px;
        height: 240px;
        padding: 16px;
        flex-shrink: 0;
        scroll-snap-align: start;
    }
    @media (max-width: 768px) {
        #<?= $id ?> .ad-card { width: 140px; height: 210px; }
    }
</style>
<?php
}
