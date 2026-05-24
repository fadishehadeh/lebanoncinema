const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');

const headers = {
  'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
};

async function fetchComingSoon() {
  const results = [];
  
  // VOX Coming Soon
  try {
    console.log('Fetching VOX coming soon...');
    const res = await axios.get('https://lbn.voxcinemas.com/movies/comingsoon', { headers, timeout: 20000 });
    const $ = cheerio.load(res.data);
    $('article.movie-summary').each((i, el) => {
      const title = $(el).attr('data-title') || $(el).find('h3 a').text().trim();
      const slug = $(el).attr('data-slug');
      if (title && slug) {
        results.push({ movie: title, source: 'Vox Cinemas', slug: slug });
      }
    });
    console.log('  Found ' + $('article.movie-summary').length + ' movies');
  } catch(e) { console.log('  Error: ' + e.message); }

  // Deduplicate
  const unique = [];
  const seen = new Set();
  results.forEach(r => {
    const key = r.movie.toLowerCase().trim();
    if (!seen.has(key)) { seen.add(key); unique.push(r); }
  });

  console.log('\n=== Coming Soon Movies ===');
  unique.forEach(r => console.log('  ' + r.movie + ' (' + r.source + ')'));
  
  const output = { metadata: { scrapedAt: new Date().toISOString(), totalMovies: unique.length, source: 'coming_soon' }, movies: unique };
  const outPath = path.join(__dirname, 'coming_soon.json');
  fs.writeFileSync(outPath, JSON.stringify(output, null, 2));
  console.log('\nSaved to ' + outPath);
}

fetchComingSoon();
