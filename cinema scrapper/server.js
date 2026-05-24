const express = require('express');
const path = require('path');
const { spawn } = require('child_process');
const fs = require('fs');

const app = express();
const PORT = 5000;

let scrapingInProgress = false;
let lastScrapedAt = null;

// Serve static files
app.use(express.static(path.join(__dirname)));

// Health check
app.get('/api/status', (req, res) => {
  res.json({
    scraping: scrapingInProgress,
    lastScrapedAt: lastScrapedAt,
    moviesFileExists: fs.existsSync(path.join(__dirname, 'movies.json'))
  });
});

// Start scraping
app.get('/api/scrape', (req, res) => {
  if (scrapingInProgress) {
    return res.status(400).json({ error: 'Scraping already in progress' });
  }

  scrapingInProgress = true;
  console.log('🎬 Starting scrape from web request...');

  const scraper = spawn('node', ['cinema-scraper-cheerio.js'], {
    cwd: __dirname,
    stdio: 'pipe'
  });

  let output = '';

  scraper.stdout.on('data', (data) => {
    const message = data.toString();
    output += message;
    console.log(message);
  });

  scraper.stderr.on('data', (data) => {
    const message = data.toString();
    output += message;
    console.error(message);
  });

  scraper.on('close', (code) => {
    scrapingInProgress = false;
    lastScrapedAt = new Date().toISOString();

    if (code === 0) {
      console.log('✅ Scraping completed successfully');
      res.json({
        success: true,
        message: 'Scraping completed',
        scrapedAt: lastScrapedAt
      });
    } else {
      console.error('❌ Scraping failed with code:', code);
      res.status(500).json({
        success: false,
        message: 'Scraping failed',
        output: output
      });
    }
  });
});

// Serve dashboard as default
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'dashboard.html'));
});

app.listen(PORT, () => {
  console.log(`\n📊 Cinema Dashboard Server`);
  console.log(`========================================`);
  console.log(`🌐 Open in browser: http://localhost:${PORT}`);
  console.log(`========================================\n`);
});
