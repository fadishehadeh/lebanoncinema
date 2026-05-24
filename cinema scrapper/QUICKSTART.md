# Cinema Scraper - Quick Start Guide

## Two Versions Available

### 1. **Puppeteer Version** (Recommended for dynamic content)
- **File**: `cinema-scraper.js`
- **Best for**: Websites with JavaScript-rendered content
- **Speed**: Slower (uses headless browser)
- **Features**: Handles dynamic loading, JavaScript execution
- **Run**: `npm run scrape:puppeteer` or `npm start`

### 2. **Cheerio Version** (Lightweight, fast)
- **File**: `cinema-scraper-cheerio.js`
- **Best for**: Static HTML content
- **Speed**: Faster (direct HTTP requests)
- **Features**: Lower memory usage, simpler installation
- **Run**: `npm run scrape:cheerio`

---

## Getting Started

### Step 1: Install Dependencies
```bash
npm install
```

### Step 2: Run the Scraper

**Option A - Puppeteer (recommended first try):**
```bash
npm start
```

**Option B - Cheerio (if Puppeteer is slow):**
```bash
npm run scrape:cheerio
```

### Step 3: Check Results
The scraper outputs `movies.json` in your current directory.

---

## Expected Output Structure

```json
{
  "metadata": {
    "scrapedAt": "2026-05-24T10:30:00.000Z",
    "totalMovies": 42,
    "sources": [
      "https://leb.grandcinemasme.com/",
      "https://lbn.voxcinemas.com/",
      "https://www.cinemacitybeirut.com/browsing/",
      "https://lbn.voxcinemas.com/movies/whatson"
    ]
  },
  "movies": [
    {
      "movie": "Movie Title",
      "date": "2026-05-24",
      "time": "19:30",
      "location": "Beirut Branch",
      "cinema": "Vox Cinemas",
      "url": "https://lbn.voxcinemas.com/"
    }
  ]
}
```

---

## Using the JSON Data

### In Node.js
```javascript
const fs = require('fs');
const data = JSON.parse(fs.readFileSync('movies.json', 'utf8'));

// Get all Vox Cinemas movies
const voxMovies = data.movies.filter(m => m.cinema === 'Vox Cinemas');

// Get movies showing at specific time
const eveningShows = data.movies.filter(m => m.time.startsWith('19') || m.time.startsWith('20'));

console.log(`Total movies: ${data.metadata.totalMovies}`);
```

### In Python
```python
import json

with open('movies.json', 'r') as f:
    data = json.load(f)

# Filter by cinema
grand_cinema = [m for m in data['movies'] if m['cinema'] == 'Grand Cinema']

# Print all movies
for movie in data['movies']:
    print(f"{movie['movie']} - {movie['cinema']} - {movie['time']}")
```

### In JavaScript (Frontend)
```javascript
fetch('movies.json')
  .then(response => response.json())
  .then(data => {
    console.log(`Found ${data.metadata.totalMovies} movies`);
    data.movies.forEach(movie => {
      console.log(`${movie.movie} at ${movie.cinema}`);
    });
  });
```

---

## Common Issues & Solutions

### Issue: "Cannot find module 'puppeteer'"
**Solution**: Run `npm install` to install all dependencies

### Issue: Scraper times out
**Solution**: 
- Try the Cheerio version: `npm run scrape:cheerio`
- Check your internet connection
- Some websites may have rate limiting

### Issue: Empty results (movies.json has no movies)
**Solution**:
1. The website structure may have changed - you may need to update CSS selectors
2. The website may be blocking automated access
3. Content might be loaded dynamically - try Puppeteer instead of Cheerio

### Issue: "Timeout waiting for Chromium to be installed"
**Solution**:
- This is a Puppeteer installation issue
- Try: `npm install puppeteer --no-save` in a separate step
- Or use Cheerio version instead: `npm run scrape:cheerio`

---

## Customizing the Scraper

### Adding a New Cinema

1. Add URL to `URLS` array
2. Create a new scrape function following the pattern
3. Add function call to `runScraper()`

**Example:**
```javascript
async function scrapeNewCinema() {
  console.log('\n🎬 Scraping New Cinema...');
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  
  try {
    await page.goto('https://newcinema.example.com/', { waitUntil: 'networkidle2' });
    
    const movies = await page.evaluate(() => {
      // Extract data...
      return results;
    });
    
    allMovies.push(...movies);
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await browser.close();
  }
}
```

### Updating CSS Selectors

If a website changes its structure, update the selectors:

```javascript
// Old selector
const title = el.querySelector('.movie-title');

// New selector (inspect website to find correct classes)
const title = el.querySelector('.film-name');
```

---

## Data Field Descriptions

| Field | Description | Example |
|-------|-------------|---------|
| movie | Full movie title | "Deadpool & Wolverine" |
| date | Showdate in YYYY-MM-DD | "2026-05-24" |
| time | Showtime in HH:MM format | "19:30" |
| location | Cinema location/branch | "ABC Mall - Beirut" |
| cinema | Cinema chain name | "Vox Cinemas" |
| url | Source website URL | "https://lbn.voxcinemas.com/" |

---

## Performance Tips

- **Puppeteer**: ~2-5 minutes for all sites (uses headless browser)
- **Cheerio**: ~10-30 seconds for all sites (direct HTTP)
- Both remove duplicates automatically
- Data is automatically sorted by cinema and movie name

---

## Schedule Automated Scraping

### On Linux/Mac (using cron):
```bash
# Edit crontab
crontab -e

# Add this line to run daily at 8 AM
0 8 * * * cd /path/to/scraper && npm start
```

### On Windows (using Task Scheduler):
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily at 8:00 AM
4. Set action: Run `node cinema-scraper.js` from your scraper directory

---

## Questions?

Check the README.md for more details or inspect the HTML of the websites to find the correct CSS selectors for your version.
