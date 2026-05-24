# 🎬 How to Run the Cinema Dashboard

## Quick Start (Recommended)

### Step 1: Install Dependencies
```bash
npm install
```

### Step 2: Start the Server
```bash
npm run server
```

Or:
```bash
npm run dashboard
```

Output:
```
📊 Cinema Dashboard Server
========================================
🌐 Open in browser: http://localhost:3000
========================================
```

### Step 3: Open Dashboard
**Click this link or copy-paste to your browser:**

```
http://localhost:3000
```

---

## 🎯 Using the Dashboard

### Refresh Data Button
A **"🔄 Refresh Data"** button appears in the top right of the dashboard.

Click it to:
1. Run the scraper automatically
2. Generate fresh `movies.json`
3. Auto-reload movies in the dashboard
4. Shows success message when done

### Why Use the Server?
- ✅ One-click data refresh from browser
- ✅ No need to open terminal
- ✅ Auto-reloads dashboard after scraping
- ✅ Shows progress while scraping
- ✅ Error messages if something goes wrong

---

## 📍 Access URLs

### From Your Computer
```
http://localhost:3000
```

### From Another Computer on Network
First, find your computer's IP:

**Windows:**
```bash
ipconfig
# Look for IPv4 Address (192.168.x.x or similar)
```

Then use:
```
http://192.168.1.XXX:3000
```
(Replace XXX with your IP)

---

## ⚡ Alternative: Without Server

If you don't want to run the server, you can still use the dashboard:

### Step 1: Generate Data Manually
```bash
npm run scrape:cheerio
```

### Step 2: Open Dashboard
Double-click `dashboard.html` OR go to:
```
http://localhost/cinema%20scrapper/dashboard.html
```
(if using XAMPP)

**Note:** Without the server, you won't have the "Refresh Data" button. You'll need to manually run the scraper from terminal.

---

## 🔧 Troubleshooting

### "Cannot connect to server" error
- Make sure you ran `npm run server`
- Check that no other app is using port 3000
- Try a different port by editing `server.js` (change `const PORT = 3000`)

### Port 3000 already in use
Edit `server.js` and change:
```javascript
const PORT = 3000;  // Change to 3001, 3002, etc.
```

Then restart the server.

### Movies not loading
1. Run the scraper first: `npm run scrape:cheerio`
2. Refresh the browser (Ctrl+R or Cmd+R)
3. Check console for errors (F12 → Console tab)

### Dashboard looks broken
- Clear browser cache (Ctrl+Shift+Delete)
- Try a different browser
- Make sure you're on `http://localhost:3000` not `file://`

---

## 📊 What Happens When You Click "Refresh Data"

1. Dashboard shows "Scraping..." with spinner
2. Server runs `cinema-scraper-cheerio.js` in background
3. Scraper fetches from all 4 cinema websites
4. `movies.json` is updated
5. Dashboard auto-loads new data
6. Success message appears
7. Button becomes clickable again

Typical time: **2-3 minutes** ⏱️

---

## 🚀 Keyboard Shortcuts

- **F5** - Refresh page
- **Ctrl+F** - Search movies on page
- **Ctrl+Shift+Delete** - Clear cache (if dashboard looks broken)

---

## 💾 Automatic Daily Updates

Want the scraper to run automatically every morning?

### Windows Task Scheduler
1. Open **Task Scheduler**
2. **Create Basic Task**
3. **Trigger:** Daily at 6:00 AM
4. **Action:** Run `node cinema-scraper-cheerio.js` from project folder
5. Now dashboard always has fresh data!

### Linux/Mac Cron
```bash
crontab -e
# Add: 0 6 * * * cd /path/to/scraper && npm run scrape:cheerio
```

---

## 📝 Notes

- Server runs on **port 3000** by default
- Scraping takes **2-3 minutes**
- Dashboard refreshes automatically when done
- All data saved to `movies.json`
- Click "View Details" on any showtime to book tickets

---

## 🎬 Summary

| Method | How to Access | Refresh Button | Ease |
|--------|---------------|---|---|
| **Server (Recommended)** | `http://localhost:3000` | ✅ Yes | Easy |
| **Static (No Server)** | `file://dashboard.html` | ❌ No | Very Easy |
| **XAMPP** | `http://localhost/cinema%20scrapper/` | ❌ No | Easy |

---

**Start the server now:**
```bash
npm run server
```

Then open: **http://localhost:3000**

Enjoy! 🎬🍿
