const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');

const URLS = [
  'https://leb.grandcinemasme.com/',
  'https://lbn.voxcinemas.com/',
  'https://www.cinemacitybeirut.com/browsing/',
  'https://lbn.voxcinemas.com/movies/whatson'
];

let allMovies = [];

const headers = {
  'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
};

// Helper: delay between requests
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

// Normalize time to 24h format (6:30pm → 18:30, 10:05 AM → 10:05)
function normalizeTime(timeStr) {
  if (!timeStr || timeStr.toLowerCase() === 'not specified') return 'Not specified';

  const match = timeStr.match(/(\d{1,2}):(\d{2})\s*(am|pm)?/i);
  if (!match) return timeStr; // Return original if no pattern match

  let [, hours, minutes, period] = match;
  hours = parseInt(hours, 10);

  if (period) {
    period = period.toLowerCase();
    if (period === 'pm' && hours !== 12) hours += 12;
    if (period === 'am' && hours === 12) hours = 0;
  }

  return `${String(hours).padStart(2, '0')}:${minutes}`;
}

// Parse date strings like "Sunday, 24 May 2026" → "2026-05-24"
function parseDate(dateStr) {
  if (!dateStr) return new Date().toISOString().split('T')[0];

  // Remove day name prefix (Sunday, 25 May 2026 → 25 May 2026)
  const cleanDate = dateStr.replace(/^[A-Za-z]+,\s*/, '').trim();
  const parsed = new Date(cleanDate);

  if (isNaN(parsed.getTime())) {
    return new Date().toISOString().split('T')[0];
  }

  return parsed.toISOString().split('T')[0];
}

// Validate movie record
function isValidMovie(record) {
  return (
    record.movie &&
    record.movie.length > 2 &&
    record.movie.length < 200 &&
    record.cinema &&
    record.date &&
    record.url
  );
}

// Get next 7 dates in YYYYMMDD format for query params
function getNextDates(count = 7) {
  const dates = [];
  for (let i = 0; i < count; i++) {
    const d = new Date();
    d.setDate(d.getDate() + i);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    dates.push(`${year}${month}${day}`);
  }
  return dates;
}

// ========== VOX CINEMAS ==========
async function scrapeVoxCinemas() {
  console.log('\n🎬 Scraping Vox Cinemas (lbn.voxcinemas.com)...');
  const baseUrl = 'https://lbn.voxcinemas.com';
  const movies = [];

  try {
    // Step 1: Get movie listing
    console.log('  Step 1: Fetching movie list from /movies/whatson...');
    const listResponse = await axios.get(`${baseUrl}/movies/whatson`, { headers, timeout: 30000 });
    const $ = cheerio.load(listResponse.data);

    const movieSlugs = [];
    $('article.movie-summary').each((i, el) => {
      const slug = $(el).attr('data-slug');
      const title = $(el).attr('data-title') || $(el).find('h3 a').text().trim();
      if (slug && title) {
        movieSlugs.push({ slug, title });
      }
    });

    console.log(`  Found ${movieSlugs.length} movies. Fetching showtimes...`);

    // Step 2: For each movie, fetch detail page and get showtimes for next 7 days
    const nextDates = getNextDates(7);

    for (const { slug, title } of movieSlugs) {
      for (const dateParam of nextDates) {
        await delay(800); // Rate limiting

        try {
          const detailUrl = `${baseUrl}/movies/${slug}?d=${dateParam}`;
          const detailResponse = await axios.get(detailUrl, { headers, timeout: 15000 });
          const $$ = cheerio.load(detailResponse.data);

          // Extract all showtimes for this date
          $$('div.dates').each((i, datesEl) => {
            const location = $$(datesEl).find('h3.highlight').text().trim();

            // Vox Cinemas groups showtimes by screen type (GOLD, Standard, etc)
            $$(datesEl).find('li').each((screenIdx, screenEl) => {
              const screenType = $$(screenEl).find('strong').text().trim();

              $$(screenEl).find('a.action.showtime').each((j, timeEl) => {
                const timeRaw = $$(timeEl).text().trim();
                const timeNorm = normalizeTime(timeRaw);

                // Parse date from dateParam (YYYYMMDD → YYYY-MM-DD)
                const dateStr = `${dateParam.substring(0, 4)}-${dateParam.substring(4, 6)}-${dateParam.substring(6, 8)}`;

                const locationWithScreen = screenType
                  ? `${location || 'City Centre Beirut'} - ${screenType}`
                  : (location || 'City Centre Beirut');

                const movie = {
                  movie: title,
                  date: dateStr,
                  time: timeNorm,
                  location: locationWithScreen,
                  cinema: 'Vox Cinemas',
                  url: detailUrl
                };

                if (isValidMovie(movie)) {
                  movies.push(movie);
                }
              });
            });
          });
        } catch (error) {
          console.warn(`  ⚠ Error fetching ${slug} for date ${dateParam}: ${error.message}`);
        }
      }
    }

    allMovies.push(...movies);
    console.log(`✓ Vox Cinemas: Found ${movies.length} showtimes`);

  } catch (error) {
    console.error('✗ Vox Cinemas scrape failed:', error.message);
  }
}

// ========== CINEMA CITY ==========
async function scrapeCinemaCity() {
  console.log('\n🎪 Scraping Cinema City (cinemacitybeirut.com)...');
  const baseUrl = 'https://www.cinemacitybeirut.com';
  const movies = [];

  try {
    // Step 1: Get movie listing
    console.log('  Step 1: Fetching movie list from /Browsing/Movies/NowShowing...');
    const listResponse = await axios.get(`${baseUrl}/Browsing/Movies/NowShowing`, { headers, timeout: 15000 });
    const $ = cheerio.load(listResponse.data);

    const movieIds = new Set();
    $('a[href*="/Browsing/Movies/Details/"]').each((i, el) => {
      const href = $(el).attr('href');
      const match = href.match(/h-HO\d+/);
      if (match) movieIds.add(match[0]);
    });

    console.log(`  Found ${movieIds.size} movies. Fetching details...`);

    // Step 2: For each movie, fetch detail page and get all showtimes
    for (const movieId of movieIds) {
      await delay(1000); // Rate limiting

      try {
        const detailUrl = `${baseUrl}/Browsing/Movies/Details/${movieId}`;
        const detailResponse = await axios.get(detailUrl, { headers, timeout: 15000 });
        const $$ = cheerio.load(detailResponse.data);

        // Get movie title from h3.boxout-title (the correct selector)
        const title = $$('h3.boxout-title').text().trim();
        if (!title) continue;

        // Parse date sections and showtimes
        // Structure: <div class="session"><h4 class="session-date">Date</h4><a class="session-time"><time>Time</time></a></div>

        $$('div.session, div.future.session').each((i, sessionEl) => {
          const dateText = $$(sessionEl).find('h4.session-date').text().trim();
          const dateStr = parseDate(dateText);

          // Find all showtimes in this session
          $$(sessionEl).find('a.session-time').each((j, timeEl) => {
            const timeEl$ = $$(timeEl);
            const timeAttr = timeEl$.find('time').attr('datetime'); // "2026-05-24T19:50:00"
            const timeDisplay = timeEl$.find('time').text().trim(); // "07:50 PM"

            let timeNorm;
            if (timeAttr) {
              // Extract time from datetime attribute (more reliable)
              const timePart = timeAttr.split('T')[1].substring(0, 5); // "19:50"
              timeNorm = timePart;
            } else {
              timeNorm = normalizeTime(timeDisplay);
            }

            const movie = {
              movie: title,
              date: dateStr,
              time: timeNorm,
              location: 'Beirut Souks Cinemacity',
              cinema: 'Cinema City',
              url: detailUrl
            };

            if (isValidMovie(movie)) {
              movies.push(movie);
            }
          });
        });

      } catch (error) {
        console.warn(`  ⚠ Error fetching ${movieId}: ${error.message}`);
      }
    }

    allMovies.push(...movies);
    console.log(`✓ Cinema City: Found ${movies.length} showtimes`);

  } catch (error) {
    console.error('✗ Cinema City scrape failed:', error.message);
  }
}

// ========== GRAND CINEMA ==========
async function scrapeGrandCinema() {
  console.log('\n📺 Scraping Grand Cinema (leb.grandcinemasme.com)...');
  const baseUrl = 'https://leb.grandcinemasme.com';
  const movies = [];
  const branchNames = ['ABC Achrafieh', 'Grand ABC Dbayeh', 'Grand ABC Verdun', 'The Spot Saida', 'Las salinas'];

  try {
    // Step 1: Get movie listing
    console.log('  Step 1: Fetching movie list from /en...');
    const listResponse = await axios.get(`${baseUrl}/en`, { headers, timeout: 15000 });
    const $ = cheerio.load(listResponse.data);

    const movieSlugs = new Set();
    $('a[href*="/movie/"]').each((i, el) => {
      const href = $(el).attr('href');
      const match = href.match(/\/movie\/([^\/]+)\/en/);
      if (match && match[1]) movieSlugs.add(match[1]);
    });

    console.log(`  Found ${movieSlugs.size} movies. Fetching details...`);

    // Step 2: For each movie, fetch detail page
    for (const slug of movieSlugs) {
      await delay(1000); // Rate limiting

      try {
        const detailUrl = `${baseUrl}/movie/${slug}/en`;
        const detailResponse = await axios.get(detailUrl, { headers, timeout: 15000 });
        const $$ = cheerio.load(detailResponse.data);

        // Get movie title
        const title = $$('h1').first().text().trim() || slug.replace(/-/g, ' ');
        if (!title || title.length < 2) continue;

        // Grand Cinema: extract times and associate with branches/screen types
        const pageText = $$('body').html() || '';

        // Check which branches are available on this page
        const detectedBranches = [];
        for (const branch of branchNames) {
          if (pageText.includes(branch)) {
            detectedBranches.push(branch);
          }
        }

        // If no specific branch found, use generic
        if (detectedBranches.length === 0) {
          detectedBranches.push('Not specified');
        }

        // Extract times using regex - look for HH:MM patterns
        const timeMatches = pageText.match(/\b([0-1]?[0-9]:[0-5][0-9])\b/g) || [];
        const uniqueTimes = [...new Set(timeMatches)];

        // Check for screen types (VIP, STD)
        const hasVIP = pageText.includes('VIP');
        const screenTypes = [];
        if (hasVIP) {
          screenTypes.push('VIP');
        }
        screenTypes.push('STD'); // Standard is always available

        // Generate records for each detected time + branch + screen type combination
        if (uniqueTimes.length > 0) {
          uniqueTimes.forEach(timeStr => {
            const timeNorm = normalizeTime(timeStr);

            detectedBranches.forEach(branch => {
              screenTypes.forEach(screenType => {
                const locationWithScreen = `${branch} - ${screenType}`;

                const movie = {
                  movie: title,
                  date: new Date().toISOString().split('T')[0],
                  time: timeNorm,
                  location: locationWithScreen,
                  cinema: 'Grand Cinema',
                  url: detailUrl
                };

                if (isValidMovie(movie)) {
                  movies.push(movie);
                }
              });
            });
          });
        }

      } catch (error) {
        console.warn(`  ⚠ Error fetching ${slug}: ${error.message}`);
      }
    }

    allMovies.push(...movies);
    console.log(`✓ Grand Cinema: Found ${movies.length} showtimes`);

  } catch (error) {
    console.error('✗ Grand Cinema scrape failed:', error.message);
  }
}

async function runScraper() {
  console.log('🎬 Starting Cinema Scraper (Cheerio Version)...');
  console.log('═'.repeat(60));
  console.log('Sites to scrape:');
  URLS.forEach((url, i) => console.log(`  ${i + 1}. ${url}`));
  console.log('═'.repeat(60));

  try {
    await scrapeVoxCinemas();
    await scrapeCinemaCity();
    await scrapeGrandCinema();

    // Remove duplicates (key: movie|date|time|location|cinema)
    const uniqueMovies = Array.from(
      new Map(
        allMovies.map(movie => [
          `${movie.movie}|${movie.date}|${movie.time}|${movie.location}|${movie.cinema}`,
          movie
        ])
      ).values()
    );

    // Sort by cinema, then date (desc), then time, then movie name
    uniqueMovies.sort((a, b) => {
      if (a.cinema !== b.cinema) return a.cinema.localeCompare(b.cinema);
      if (a.date !== b.date) return b.date.localeCompare(a.date); // Newest first
      if (a.time !== b.time) return a.time.localeCompare(b.time);
      return a.movie.localeCompare(b.movie);
    });

    // Save to JSON
    const outputPath = path.join(process.cwd(), 'movies.json');
    fs.writeFileSync(
      outputPath,
      JSON.stringify({
        metadata: {
          scrapedAt: new Date().toISOString(),
          totalMovies: uniqueMovies.length,
          sources: URLS
        },
        movies: uniqueMovies
      }, null, 2)
    );

    console.log('\n' + '═'.repeat(60));
    console.log('✅ SCRAPING COMPLETE');
    console.log('═'.repeat(60));
    console.log(`📊 Total unique showtimes: ${uniqueMovies.length}`);
    console.log(`📁 Saved to: ${outputPath}`);

    console.log('\n📋 Summary by Cinema:');
    const summary = {};
    uniqueMovies.forEach(movie => {
      summary[movie.cinema] = (summary[movie.cinema] || 0) + 1;
    });

    Object.entries(summary).sort().forEach(([cinema, count]) => {
      console.log(`   • ${cinema}: ${count} showtimes`);
    });

    if (uniqueMovies.length > 0) {
      console.log('\n📋 Sample data (first 5 showtimes):');
      uniqueMovies.slice(0, 5).forEach(movie => {
        console.log(`   ${movie.movie} | ${movie.date} ${movie.time} | ${movie.location} | ${movie.cinema}`);
      });
    }

  } catch (error) {
    console.error('Fatal error:', error);
    process.exit(1);
  }
}

runScraper();
