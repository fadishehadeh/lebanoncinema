
<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a class="bottom-nav-item" href="<?= _link('/') ?>">
        <i data-lucide="calendar"></i>
        <span>Today</span>
    </a>
    <a class="bottom-nav-item" href="<?= _link('/movies') ?>">
        <i data-lucide="film"></i>
        <span>Movies</span>
    </a>
    <a class="bottom-nav-item" href="<?= _link('/cinemas') ?>">
        <i data-lucide="map-pin"></i>
        <span>Cinemas</span>
    </a>
    <button class="bottom-nav-item" id="bottom-search" style="font-family:inherit;">
        <i data-lucide="search"></i>
        <span>Search</span>
    </button>
</nav>

<!-- Footer -->
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <h3>Lebanon<span style="color:var(--accent);">Cinema</span></h3>
            <p>Discover what's playing at cinemas across Lebanon. Showtimes updated daily at 6am.</p>
        </div>
        <div>
            <h4>Browse</h4>
            <a href="<?= _link('/') ?>">Today's Showtimes</a>
            <a href="<?= _link('/movies') ?>">All Movies</a>
            <a href="<?= _link('/cinemas') ?>">All Cinemas</a>
            <a href="<?= _link('/coming-soon') ?>">Coming Soon</a>
        </div>
        <div>
            <h4>Cinema Chains</h4>
            <a href="<?= _link('/cinemas') ?>">VOX Cinemas</a>
            <a href="<?= _link('/cinemas') ?>">Grand Cinemas</a>
            <a href="<?= _link('/cinemas') ?>">Empire Cinemas</a>
            <a href="<?= _link('/cinemas') ?>">CinemaCity</a>
            <a href="<?= _link('/cinemas') ?>">Cinemall</a>
            <a href="<?= _link('/cinemas') ?>">Stargate</a>
        </div>
        <div>
            <h4>Genres</h4>
            <a href="<?= _link('/movies?genre=Action') ?>">Action</a>
            <a href="<?= _link('/movies?genre=Comedy') ?>">Comedy</a>
            <a href="<?= _link('/movies?genre=Drama') ?>">Drama</a>
            <a href="<?= _link('/movies?genre=Horror') ?>">Horror</a>
            <a href="<?= _link('/movies?genre=Family') ?>">Family</a>
            <a href="<?= _link('/movies?genre=Animation') ?>">Animation</a>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
    </div>
</footer>

<?php if (isset($showSkeleton) && $showSkeleton): ?>
<script>
// Show skeleton while data loads
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-skeleton]').forEach(el => {
        el.innerHTML = '';
        for (let i = 0; i < (parseInt(el.dataset.skeleton) || 6); i++) {
            const div = document.createElement('div');
            div.className = 'skel skel-poster';
            el.appendChild(div);
        }
    });
});
</script>
<?php endif; ?>

<script>
// Navbar scroll effect
const nav = document.querySelector('.topnav');
if (nav) {
    window.addEventListener('scroll', () => {
        nav.classList.toggle('scrolled', window.scrollY > 100);
    });
    if (window.scrollY > 100) nav.classList.add('scrolled');
}

// Lucide icons
lucide.createIcons();

// Theme toggle
const themeToggle = document.getElementById('theme-toggle');
const html = document.documentElement;
const themeIconLight = document.querySelector('.theme-icon-light');
const themeIconDark = document.querySelector('.theme-icon-dark');

const savedTheme = localStorage.getItem('theme') || 'dark';
if (savedTheme === 'light') {
    html.classList.add('light-theme');
    themeIconLight.style.display = 'none';
    themeIconDark.style.display = 'block';
}

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const isLight = html.classList.toggle('light-theme');
        localStorage.setItem('theme', isLight ? 'light' : 'dark');
        if (isLight) {
            themeIconLight.style.display = 'none';
            themeIconDark.style.display = 'block';
        } else {
            themeIconLight.style.display = 'block';
            themeIconDark.style.display = 'none';
        }
        setTimeout(() => lucide.createIcons(), 0);
    });
}

// Active nav — compare just the last path segment
const currentPath = location.pathname;
document.querySelectorAll('.nav-link').forEach(el => {
    const href = el.getAttribute('href');
    if (href && currentPath.endsWith(href)) {
        el.classList.add('active');
    }
});
document.querySelectorAll('.bottom-nav-item').forEach(el => {
    const href = el.getAttribute('href');
    if (href && currentPath.endsWith(href)) {
        el.classList.add('active');
    }
});

// Hamburger menu
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobile-menu');
if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        mobileMenu.classList.toggle('open');
        document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
    });
    mobileMenu.querySelectorAll('[data-mobile-link]').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            mobileMenu.classList.remove('open');
            document.body.style.overflow = '';
        });
    });
}

// Search trigger (works from header button, bottom nav, and mobile menu)
function triggerSearch() {
    const input = document.getElementById('hero-search');
    if (input) {
        input.focus();
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        // If not on homepage, navigate to search page
        window.location.href = '<?= _link('/search') ?>';
    }
}

document.getElementById('search-btn')?.addEventListener('click', triggerSearch);
document.getElementById('bottom-search')?.addEventListener('click', triggerSearch);

// ========== HERO SEARCH (debounced AJAX) ==========
const heroSearch = document.getElementById('hero-search');
const dropdown = document.getElementById('search-dropdown');
let searchTimer;

if (heroSearch) {
    heroSearch.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = heroSearch.value.trim();
        if (q.length < 2) {
            dropdown.classList.remove('open');
            return;
        }
        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch(`<?= _link('/search.php') ?>?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                renderSearch(data);
            } catch (e) {
                console.error('Search error:', e);
            }
        }, 300);
    });

    // Keyboard: Enter navigates to search page
    heroSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const q = heroSearch.value.trim();
            if (q.length > 0) {
                window.location.href = '<?= _link('/search') ?>?q=' + encodeURIComponent(q);
            }
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrap')) {
            dropdown?.classList.remove('open');
        }
    });
}

function renderSearch(data) {
    const hasMovies = data.movies && data.movies.length > 0;
    const hasCinemas = data.cinemas && data.cinemas.length > 0;

    if (!hasMovies && !hasCinemas) {
        dropdown.classList.remove('open');
        return;
    }

    let html = '';

    if (hasMovies) {
        html += '<div class="search-group-label">Movies</div>';
        data.movies.forEach(m => {
            const initial = m.title ? m.title.charAt(0).toUpperCase() : '?';
            html += `
                <a href="/movies/${encodeURIComponent(m.slug)}" class="search-item">
                    ${m.poster_url
                        ? `<img src="${m.poster_url}" alt="" class="search-item-poster">`
                        : `<div class="search-item-poster" style="display:flex;align-items:center;justify-content:center;background:var(--surface);color:var(--text-muted);font-size:1rem;">${initial}</div>`
                    }
                    <div class="search-item-info">
                        <div class="search-item-title">${m.title}</div>
                        <div class="search-item-meta">${m.genres || ''}</div>
                    </div>
                    <i data-lucide="arrow-right"></i>
                </a>
            `;
        });
        var searchQ = heroSearch.value.trim();
        html += '<a href="<?= _link('/search') ?>?q=' + encodeURIComponent(searchQ) + '" class="search-item" style="border-top:1px solid var(--border);">
                    <i data-lucide="search" style="color:var(--text-muted);"></i>
                    <span style="font-size:0.85rem;color:var(--text-muted);">See all results</span>
                    <i data-lucide="arrow-right"></i>
                </a>';
    }

    if (hasCinemas) {
        html += '<div class="search-group-label">Cinemas</div>';
        data.cinemas.forEach(c => {
            html += `
                <a href="/cinemas/${encodeURIComponent(c.slug)}" class="search-item">
                    <div style="width:10px;height:10px;border-radius:50%;background:${c.color_hex};flex-shrink:0;"></div>
                    <div class="search-item-info">
                        <div class="search-item-title">${c.name}</div>
                        <div class="search-item-meta">${c.chain_name || ''}${c.movies_today ? ' · ' + c.movies_today + ' movies' : ''}</div>
                    </div>
                    <i data-lucide="arrow-right"></i>
                </a>
            `;
        });
    }

    dropdown.innerHTML = html;
    dropdown.classList.add('open');
    lucide.createIcons();
}

// ========== WATCHLIST (localStorage) ==========
function toggleWatchlist(slug) {
    const list = JSON.parse(localStorage.getItem('watchlist') || '[]');
    const idx = list.indexOf(slug);
    if (idx === -1) { list.push(slug); } else { list.splice(idx, 1); }
    localStorage.setItem('watchlist', JSON.stringify(list));
    updateWatchlistButtons(slug, list.includes(slug));
}

function updateWatchlistButtons(slug, saved) {
    document.querySelectorAll(`.watchlist-btn[data-slug="${slug}"]`).forEach(btn => {
        btn.classList.toggle('saved', saved);
    });
}

const wl = JSON.parse(localStorage.getItem('watchlist') || '[]');
document.querySelectorAll('.watchlist-btn').forEach(btn => {
    const slug = btn.dataset.slug;
    btn.classList.toggle('saved', wl.includes(slug));
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleWatchlist(slug);
    });
});

// ========== SHARE BUTTON ==========
const shareBtn = document.getElementById('share-btn');
if (shareBtn) {
    shareBtn.addEventListener('click', async () => {
        const text = document.querySelector('h1')?.textContent || 'Check this out!';
        const url = window.location.href;
        if (navigator.share) {
            try { await navigator.share({ title: text, text: 'Check out this movie!', url: url }); }
            catch (e) { /* cancelled */ }
        } else {
            await navigator.clipboard.writeText(url);
            // Show brief toast
            const toast = document.createElement('div');
            toast.textContent = 'Link copied!';
            Object.assign(toast.style, {
                position:'fixed', bottom:'80px', left:'50%', transform:'translateX(-50%)',
                background:'var(--accent)', color:'#000', padding:'10px 20px',
                borderRadius:'12px', fontWeight:'600', fontSize:'0.85rem',
                zIndex:'9999', animation:'fadeUp 0.3s var(--ease)'
            });
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        }
    });
}

// ========== CHIP FILTERING ==========
document.querySelectorAll('.chip[data-filter]').forEach(chip => {
    chip.addEventListener('click', () => {
        const parent = chip.closest('.chips-row') || chip.parentElement;
        parent.querySelectorAll('.chip[data-filter]').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        filterMovies(chip.dataset.filter);
    });
});

function filterMovies(filter) {
    document.querySelectorAll('[data-genres]').forEach(card => {
        const genres = (card.dataset.genres || '').toLowerCase();
        const formats = (card.dataset.formats || '').toLowerCase();
        const urgency = parseInt(card.dataset.urgency || '-1');

        let show = false;
        switch (filter) {
            case 'all': show = true; break;
            case 'soon': show = urgency >= 0 && urgency <= 90; break;
            case 'vip': show = formats.includes('vip') || formats.includes('imax'); break;
            case 'family': show = genres.includes('family') || genres.includes('animation'); break;
            case 'action': show = genres.includes('action'); break;
            case 'horror': show = genres.includes('horror'); break;
            case 'comedy': show = genres.includes('comedy'); break;
            default: show = true;
        }
        const container = card.closest('[data-movie-item]') || card;
        if (container) container.style.display = show ? '' : 'none';
    });
}

// ========== SMOOTH SCROLL ==========
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href === '#') return;
        e.preventDefault();
        document.querySelector(href)?.scrollIntoView({ behavior: 'smooth' });
    });
});

setTimeout(() => lucide.createIcons(), 100);
</script>

</body>
</html>
