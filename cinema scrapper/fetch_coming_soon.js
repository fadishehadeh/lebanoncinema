const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');

const headers = {
  'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
};

async function main() {
  const results = [];
  
  // 1. VOX Coming Soon
  try {
    console.log('=== VOX Cinemas Coming Soon ===');
    const res = await axios.get('https://lbn.voxcinemas.com/movies/comingsoon', { headers, timeout: 20000 });
    const $ = cheerio.load(res.data);
    $('article.movie-summary').each((i, el) => {
      const title = $(el).attr('data-title') || $(el).find('h3 a').text().trim();
      if (title) results.push({ movie: title, source: 'Vox Cinemas' });
    });
    console.log('  Found: ' + results.filter(r => r.source === 'Vox Cinemas').length);
  } catch(e) { console.log('  Error: ' + e.message); }
  
  // 2. Grand Cinema Coming Soon (from #movies_2 tab)
  try {
    console.log('\n=== Grand Cinema Coming Soon ===');
    const res = await axios.get('https://leb.grandcinemasme.com/en', { headers, timeout: 20000 });
    const $ = cheerio.load(res.data);
    $('#movies_2 .movie').each((i, el) => {
      const title = $(el).find('.movie-hover-title').text().trim();
      const href = $(el).find('a.thumbnaila').attr('href') || '';
      if (title && title.length > 2 && !results.some(r => r.movie.toLowerCase() === title.toLowerCase())) {
        results.push({ movie: title, source: 'Grand Cinema', slug: href.split('/')[2] || '' });
      }
    });
    console.log('  Found: ' + results.filter(r => r.source === 'Grand Cinema').length);
  } catch(e) { console.log('  Error: ' + e.message); }
  
  // 3. Cinema City Coming Soon
  try {
    console.log('\n=== Cinema City Coming Soon ===');
    const res = await axios.get('https://www.cinemacitybeirut.com/Browsing/Movies/ComingSoon', { headers, timeout: 20000 });
    const $ = cheerio.load(res.data);
    $('a[href*="/Browsing/Movies/Details/"]').each((i, el) => {
      const title = $(el).text().trim();
      if (title && title.length > 2 && !results.some(r => r.movie.toLowerCase() === title.toLowerCase())) {
        results.push({ movie: title, source: 'Cinema City' });
      }
    });
    // Also try to extract from boxout-title
    $('h3.boxout-title').each((i, el) => {
      const title = $(el).text().trim();
      if (title && title.length > 2 && !results.some(r => r.movie.toLowerCase() === title.toLowerCase())) {
        results.push({ movie: title, source: 'Cinema City' });
      }
    });
    console.log('  Found: ' + results.filter(r => r.source === 'Cinema City').length);
  } catch(e) { console.log('  Error: ' + e.message); }
  
  // Deduplicate by title
  const seen = new Set();
  const unique = results.filter(r => {
    const key = r.movie.toLowerCase().trim();
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
  
  console.log('\n=== All Coming Soon Movies (' + unique.length + ') ===');
  unique.forEach(r => console.log('  ' + r.movie + ' (' + r.source + ')'));
  
  const output = { metadata: { scrapedAt: new Date().toISOString(), totalMovies: unique.length }, movies: unique };
  const outPath = path.join(__dirname, 'coming_soon.json');
  fs.writeFileSync(outPath, JSON.stringify(output, null, 2));
  console.log('\nSaved to ' + outPath);
}

main();
