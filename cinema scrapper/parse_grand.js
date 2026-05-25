const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');

const html = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'Users', 'Fadi', 'AppData', 'Local', 'Temp', 'opencode', 'grand_html.txt'), 'utf-8');

// Try different path
let $;
try {
  $ = cheerio.load(html);
} catch(e) {
  console.log('Error loading HTML:', e.message);
  process.exit(1);
}

console.log('=== NOW SHOWING ===');
$('#movies_1 .movie').each((i, el) => {
  const title = $(el).find('.movie-hover-title').text().trim();
  const slug = $(el).find('a.thumbnaila').attr('href') || '';
  const cinemas = $(el).attr('data-cinemas') || '';
  const formats = $(el).attr('data-attributes') || '';
  if (title) console.log(title + ' | slug:' + slug.split('/')[2] + ' | cinemas:' + cinemas + ' | formats:' + formats.substring(0, 30));
});

console.log('\n=== COMING SOON ===');
$('#movies_2 .movie').each((i, el) => {
  const title = $(el).find('.movie-hover-title').text().trim();
  const slug = $(el).find('a.thumbnaila').attr('href') || '';
  if (title) console.log(title + ' | slug:' + (slug.split('/')[2] || ''));
});
