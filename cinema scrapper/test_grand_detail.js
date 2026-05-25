const axios = require('axios');
const cheerio = require('cheerio');

async function main() {
  try {
    const res = await axios.get('https://leb.grandcinemasme.com/movie/star-wars-the-mandalorian-and-grogu-gc/en', {
      headers: { 'User-Agent': 'Mozilla/5.0' }, timeout: 15000
    });
    const $ = cheerio.load(res.data);
    console.log('Title:', $('h1').first().text().trim());
    
    const body = $('body').text();
    const dates = body.match(/\d{4}-\d{2}-\d{2}/g) || [];
    console.log('Dates:', dates.length > 0 ? [...new Set(dates)].join(', ') : 'none');
    
    const times = body.match(/\b([0-1]?[0-9]:[0-5][0-9])\b/g) || [];
    console.log('Times:', times.length > 0 ? [...new Set(times)].slice(0, 10).join(', ') : 'none');
    
    // Look for branch selectors
    const branchOptions = [];
    $('select option').each((i, el) => {
      const text = $(el).text().trim();
      if (text && text.length > 2) branchOptions.push(text);
    });
    if (branchOptions.length) console.log('Branch options:', branchOptions.join(', '));
    
    console.log('\nPage structure (first 500 chars):', res.data.substring(0, 500));
  } catch(e) {
    console.log('Error:', e.message);
  }
}
main();
