# 🎬 Cinema Dashboard User Guide

## Quick Start

### 1. Generate Movie Data
```bash
npm run scrape:cheerio
```
This creates `movies.json` with all showtimes.

### 2. Open Dashboard
Open `dashboard.html` in your browser:
- **Local:** Double-click `dashboard.html` or drag to browser
- **Web Server:** `http://localhost/cinema-scrapper/dashboard.html`
- **XAMPP:** `http://localhost/cinema%20scrapper/dashboard.html`

---

## Dashboard Features

### 📊 Statistics Panel
Shows at a glance:
- **Total Showtimes** - All available showtimes
- **Cinemas** - Number of cinema chains
- **Unique Movies** - How many different films
- **Days** - Date range available

### 🔍 Filters
Filter by:
- **Cinema** - Select a specific cinema (Vox, Cinema City, Grand Cinema)
- **Date** - Pick a specific date
- **Movie** - Search by movie name (real-time search)
- **Time** - Morning (6AM-12PM), Afternoon (12PM-6PM), Evening (6PM-12AM)

Click **🔍 Filter** to apply or **↺ Reset** to clear all filters.

### 🎞️ Movie Cards
Each card shows:
- **Movie Title** - Film name
- **Cinema** - Which cinema chain
- **Date** - When it's showing (with day of week)
- **Time** - Showtime in 24h format (with badge)
- **Location** - Branch/theater name + **Screen Type** (GOLD, Standard, VIP, 4DX)
- **View Details** - Link to cinema's website

### 🏷️ Screen Type Badges
Different colors for different experiences:
- **Standard** - Blue badge
- **GOLD** - Yellow badge (premium)
- **VIP** - Green badge (luxury seating)
- **4DX** - Purple badge (4D experience)

---

## How to Use

### View All Movies
1. Open `dashboard.html`
2. All 774+ showtimes load automatically

### Find Movies Tomorrow
1. Select tomorrow's date from **Date** filter
2. Click **🔍 Filter**

### Find Evening Shows Today
1. Select today's date from **Date** filter
2. Select "Evening (18:00-23:59)" from **Time** filter
3. Click **🔍 Filter**

### Search for a Specific Movie
1. Type movie name in **Movie** search box
2. Click **🔍 Filter**
3. Results update as you type (if using search only)

### Find GOLD Screen Shows at Vox
1. Select "Vox Cinemas" from **Cinema** filter
2. Look for yellow "GOLD" badges in results
3. Click **🔍 Filter**

### Check What's Available This Weekend
1. Select Saturday from **Date** filter
2. Click **🔍 Filter**
3. Scroll and see all options
4. Repeat for Sunday

---

## Features Explained

### Last Updated
Shows when the data was scraped. Refresh the page to reload latest data.

### Link to Cinema Website
Click **View Details →** on any showtime to book tickets directly on the cinema's website.

### Mobile Friendly
Dashboard works on phones and tablets - cards stack automatically on small screens.

### Fast Filtering
- **Cinema filter** - Narrows to one chain
- **Date filter** - Shows specific day
- **Movie search** - Real-time text search
- **Time filter** - Groups by time of day
- **Combine filters** - Use multiple filters together

---

## Tips & Tricks

### 💡 Pro Tips

1. **Multiple filters work together** - Select Cinema + Date to see what's on at a specific theater today

2. **Movie search is case-insensitive** - Type "deadpool" and it finds "Deadpool & Wolverine"

3. **Times are in 24-hour format** - 13:30 = 1:30 PM, 19:00 = 7:00 PM

4. **Hover over cards** - They lift up when you hover for better interactivity

5. **Refresh to get latest** - If you scrape new data, refresh the page to see it

6. **Open links in new tab** - Right-click "View Details →" to open cinema's booking page in new tab

---

## Troubleshooting

### Dashboard shows "Error loading movies"
- Make sure `movies.json` exists in the same folder as `dashboard.html`
- Run `npm run scrape:cheerio` first to generate the data

### No results found
- Check your filters - you might be filtering too strictly
- Click **↺ Reset** to clear all filters
- Make sure you ran the scraper recently

### Dashboard looks weird
- Clear browser cache (Ctrl+Shift+Delete / Cmd+Shift+Delete)
- Try a different browser
- Make sure you're using a modern browser (Chrome, Firefox, Safari, Edge)

### Last updated shows wrong time
- This is when the `movies.json` was generated
- Run `npm run scrape:cheerio` again to get fresh data

---

## Scheduling Daily Updates

### Automatic Updates Every Morning

**Windows Task Scheduler:**
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily at 6:00 AM
4. Set action: Run `node cinema-scraper.js` from project folder
5. Now dashboard always has fresh data!

**Linux/Mac Cron:**
```bash
crontab -e
# Add: 0 6 * * * cd /path/to/scraper && npm run scrape:cheerio
```

Then just open dashboard.html and refresh to see latest data.

---

## Customizing the Dashboard

### Change Colors
Edit the `style` section in `dashboard.html`:
```css
/* Change primary color from purple to blue */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
/* to: */
background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
```

### Add More Filters
Add a new filter group before the button:
```html
<div class="filter-group">
    <label for="screenFilter">Screen Type</label>
    <select id="screenFilter">
        <option value="">All Screens</option>
        <option value="GOLD">GOLD</option>
        <option value="Standard">Standard</option>
    </select>
</div>
```

### Change Card Layout
Edit grid in CSS:
```css
/* 3 columns instead of responsive */
grid-template-columns: repeat(3, 1fr);
```

---

## Questions?

Check the `QUICKSTART.md` file for scraper questions or see `README.md` for technical details.
