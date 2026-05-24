# Lebanon Cinema — Showtimes Website

## 🚀 Quick Start

**The website is fully set up and ready to run!**

### Access the Site

Open your browser and go to:
```
http://localhost/lebanoncinema/public/
```

### Available Pages

| Page | URL | Description |
|------|-----|-------------|
| Homepage | `/` | Showtimes by date (today + 6 days) |
| Movies Listing | `/movies` | All movies with genre filtering |
| Movie Detail | `/movies/{slug}` | Full details, synopsis, showtimes, trailer |
| Cinemas Listing | `/cinemas` | All cinemas grouped by chain |
| Cinema Detail | `/cinemas/{slug}` | Cinema info, today's movies & showtimes |

### Example URLs
- `http://localhost/lebanoncinema/public/movies`
- `http://localhost/lebanoncinema/public/movies/the-dark-knight`
- `http://localhost/lebanoncinema/public/cinemas`

---

## 📊 Database

**Status:** ✓ Created and populated with sample data

- **5 movies** (The Dark Knight, Inception, Interstellar, The Matrix, Avatar)
- **86 showtimes** across 12 cinemas
- **6 cinema chains** (VOX, Grand, Empire, CinemaCity, Cinemall, Stargate)

### Database Credentials
```php
DB_HOST: localhost
DB_USER: root
DB_PASS: (empty)
DB_NAME: lebanon_cinema
```

---

## 🎬 Features Implemented

### ✅ Complete
- [x] VOX scraper (scraper/vox_scraper.php)
- [x] TMDB enricher (scraper/tmdb_enricher.php) — fetch posters, synopses, trailers
- [x] Homepage with date-based showtimes
- [x] Movies listing with genre filtering
- [x] Movie detail page with YouTube trailers
- [x] Cinemas listing grouped by chain
- [x] Cinema detail page with showtimes
- [x] Dark theme UI with responsive design
- [x] Clean URL routing (.htaccess)
- [x] Daily cron job (cron/daily.sh)

### Project Structure
```
c:\xampp\htdocs\lebanoncinema\
├── public/                  ← Web-accessible directory
│   ├── index.php           ← Homepage
│   ├── movies.php          ← Movies listing
│   ├── movie.php           ← Movie detail
│   ├── cinemas.php         ← Cinemas listing
│   ├── cinema.php          ← Cinema detail
│   ├── .htaccess           ← URL routing
│   └── includes/
│       ├── header.php      ← HTML header + CSS
│       └── footer.php      ← HTML footer
├── scraper/
│   ├── vox_scraper.php     ← VOX showtimes scraper
│   └── tmdb_enricher.php   ← TMDB metadata enricher
├── cron/
│   ├── daily.sh            ← Daily cron job (6am)
│   └── logs/               ← Cron logs
├── config.php              ← Database & API config
├── schema.sql              ← Database schema
└── README.md               ← This file
```

---

## 🔧 Configuration

### TMDB API Key (Optional)

To fetch movie posters, synopses, and trailers, you need a free TMDB API key:

1. Sign up at https://www.themoviedb.org/settings/api
2. Copy your API key
3. Edit `config.php` and set:
   ```php
   define('TMDB_API_KEY', 'your_key_here');
   ```
4. Run the enricher:
   ```bash
   php scraper/tmdb_enricher.php
   ```

### Cron Job (Linux/macOS)

To run the scraper automatically at 6am daily, add to your crontab:
```bash
0 6 * * * /path/to/cron/daily.sh >> /path/to/cron/logs/cron.log 2>&1
```

---

## 🌙 Dark Theme

The site uses a dark theme with:
- **Background:** `#0d0d0d` (near black)
- **Surfaces:** `#161616` (dark gray)
- **Text:** `#f0f0f0` (light gray)
- **Accent:** `#e63946` (red) — primary action color
- **Accent 2:** `#f4a261` (orange) — secondary accent

All colors use CSS custom properties (`--accent`, `--text`, etc.) for easy theming.

---

## 📱 Responsive Design

The site is fully responsive with:
- Mobile: 2-column movie grid
- Tablet: 3-4 columns
- Desktop: 5+ columns

Tested on all Bootstrap breakpoints (xs, sm, md, lg, xl).

---

## 🚨 Troubleshooting

### Pages not loading?
- Ensure Apache is running: Check XAMPP Control Panel
- Verify `public/` is the document root for your vhost
- Check `config.php` has correct MySQL credentials

### Database issues?
- MySQL must be running (check XAMPP Control Panel)
- Run `php test.php` to diagnose connection issues
- Check that `lebanon_cinema` database exists

### URLs not routing?
- Verify `.htaccess` exists in `public/`
- Ensure Apache's `AllowOverride All` is set
- Check that `mod_rewrite` is enabled in Apache

---

## 📝 Next Steps

1. **Test the site** → Open http://localhost/lebanoncinema/public/
2. **Add TMDB API key** (optional) → Get real movie data
3. **Add other cinema chains** → Build scrapers for Grand, Empire, etc.
4. **Set up production** → Deploy to a real server

---

Made with ❤️ by Claude
