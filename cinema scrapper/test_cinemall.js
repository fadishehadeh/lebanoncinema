const axios = require('axios');
const cheerio = require('cheerio');

async function test() {
  try {
    const res = await axios.get('https://www.cine-mall.com/', { 
      headers: {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}, 
      timeout: 30000 
    });
    const $ = cheerio.load(res.data);
    console.log('Page length:', res.data.length);
    
    // Check for now showing section
    const nsTitles = [];
    $('section.part[data-id="1"] .title').each((i, el) => {
      const t = $(el).text().trim();
      if (t) nsTitles.push(t);
    });
    console.log('Now Showing titles found:', nsTitles.length);
    nsTitles.slice(0, 5).forEach(t => console.log('  NS:', t));
    
    // Check for coming soon section
    const csTitles = [];
    $('section.soon-part .title, section.part[data-id="2"] .title').each((i, el) => {
      const t = $(el).text().trim();
      if (t) csTitles.push(t);
    });
    console.log('Coming Soon titles found:', csTitles.length);
    csTitles.slice(0, 5).forEach(t => console.log('  CS:', t));
    
    // Debug: look for any figure elements
    console.log('Figure elements:', $('figure').length);
    console.log('Hover-desc elements:', $('.hover-desc').length);
    console.log('Sections with soon-part:', $('.soon-part').length);
    
  } catch(e) {
    console.log('Error:', e.message);
  }
}
test();
