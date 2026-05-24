const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const URLS = [
  'https://leb.grandcinemasme.com/',
  'https://lbn.voxcinemas.com/',
  'https://www.cinemacitybeirut.com/browsing/',
  'https://lbn.voxcinemas.com/movies/whatson'
];

let allMovies = [];
let browser = null;

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

// Normalize time to 24h format
function normalizeTime(timeStr) {
  if (!timeStr || timeStr.toLowerCase() === 'not specified') return 'Not specified';

  const match = timeStr.match(/(\d{1,2}):(\d{2})\s*(am|pm)?/i);
  if (!match) return timeStr;

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

// Get next 7 dates in YYYYMMDD format
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
async function scrapeVoxCinemas(browser) {
  console.log('\n🎬 Scraping Vox Cinemas (lbn.voxcinemas.com)...');
  const baseUrl = 'https://lbn.voxcinemas.com';
  const movies = [];

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1024 });

    // Step 1: Get movie listing
    console.log('  Step 1: Fetching movie list from /movies/whatson...');
    await page.goto(`${baseUrl}/movies/whatson`, {
      waitUntil: 'networkidle2',
      timeout: 30000
    });

    const movieSlugs = await page.evaluate(() => {
      const results = [];
      document.querySelectorAll('article.movie-summary').forEach(el => {
        const slug = el.getAttribute('data-slug');
        const title = el.getAttribute('data-title') || el.querySelector('h3 a')?.textContent?.trim();
        if (slug && title) {
          results.push({ slug, title });
        }
      });
      return results;
    });

    console.log(`  Found ${movieSlugs.length} movies. Fetching showtimes...`);

    // Step 2: For each movie, fetch detail page and get showtimes
    const nextDates = getNextDates(7);

    for (const { slug, title } of movieSlugs) {
      for (const dateParam of nextDates) {
        await delay(800);

        try {
          const detailUrl = `${baseUrl}/movies/${slug}?d=${dateParam}`;
          await page.goto(detailUrl, {
            waitUntil: 'networkidle2',
            timeout: 30000
          });

          const showtimes = await page.evaluate((dateParam) => {
            const results = [];
            document.querySelectorAll('div.dates').forEach(datesEl => {
              const location = datesEl.querySelector('h3.highlight')?.textContent?.trim();

              // Vox Cinemas groups times by screen type (GOLD, Standard, etc)
              datesEl.querySelectorAll('li').forEach(screenEl => {
                const screenType = screenEl.querySelector('strong')?.textContent?.trim();

                screenEl.querySelectorAll('a.action.showtime').forEach(timeEl => {
                  const timeRaw = timeEl.textContent?.trim();
                  const locationWithScreen = screenType
                    ? `${location || 'City Centre Beirut'} - ${screenType}`
                    : (location || 'City Centre Beirut');

                  results.push({
                    time: timeRaw,
                    location: locationWithScreen
                  });
                });
              });
            });
            return results;
          }, dateParam);

          showtimes.forEach(showtime => {
            const dateStr = `${dateParam.substring(0, 4)}-${dateParam.substring(4, 6)}-${dateParam.substring(6, 8)}`;
            const timeNorm = normalizeTime(showtime.time);

            const movie = {
              movie: title,
              date: dateStr,
              time: timeNorm,
              location: showtime.location,
              cinema: 'Vox Cinemas',
              url: detailUrl
            };

            if (isValidMovie(movie)) {
              movies.push(movie);
            }
          });

        } catch (error) {
          console.warn(`  ⚠ Error fetching ${slug} for date ${dateParam}: ${error.message}`);
        }
      }
    }

    await page.close();
    allMovies.push(...movies);
    console.log(`✓ Vox Cinemas: Found ${movies.length} showtimes`);

  } catch (error) {
    console.error('✗ Vox Cinemas scrape failed:', error.message);
  }
}

// ========== CINEMA CITY ==========
async function scrapeCinemaCity(browser) {
  console.log('\n🎪 Scraping Cinema City (cinemacitybeirut.com)...');
  const baseUrl = 'https://www.cinemacitybeirut.com';
  const movies = [];

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1024 });

    // Step 1: Get movie listing
    console.log('  Step 1: Fetching movie list from /Browsing/Movies/NowShowing...');
    await page.goto(`${baseUrl}/Browsing/Movies/NowShowing`, {
      waitUntil: 'networkidle2',
      timeout: 30000
    });

    const movieIds = await page.evaluate(() => {
      const ids = new Set();
      document.querySelectorAll('a[href*="/Browsing/Movies/Details/"]').forEach(el => {
        const href = el.getAttribute('href');
        const match = href.match(/h-HO\d+/);
        if (match) ids.add(match[0]);
      });
      return Array.from(ids);
    });

    console.log(`  Found ${movieIds.length} movies. Fetching details...`);

    // Step 2: For each movie, fetch detail page
    for (const movieId of movieIds) {
      await delay(1000);

      try {
        const detailUrl = `${baseUrl}/Browsing/Movies/Details/${movieId}`;
        await page.goto(detailUrl, {
          waitUntil: 'networkidle2',
          timeout: 30000
        });

        const movieData = await page.evaluate(() => {
          // Get movie title from h3.boxout-title (correct selector for Cinema City)
          const title = document.querySelector('h3.boxout-title')?.textContent?.trim();
          const showtimes = [];

          // Parse date sections and showtimes
          // Structure: <div class="session"><h4 class="session-date">Date</h4><a class="session-time"><time>Time</time></a></div>
          document.querySelectorAll('div.session, div.future.session').forEach(sessionEl => {
            const dateText = sessionEl.querySelector('h4.session-date')?.textContent?.trim();

            sessionEl.querySelectorAll('a.session-time').forEach(timeEl => {
              const timeAttr = timeEl.querySelector('time')?.getAttribute('datetime');
              const timeDisplay = timeEl.querySelector('time')?.textContent?.trim();

              showtimes.push({
                dateText: dateText,
                timeAttr: timeAttr, // ISO format: "2026-05-24T19:50:00"
                time: timeDisplay  // Display format: "07:50 PM"
              });
            });
          });

          return { title, showtimes };
        });

        if (movieData.title) {
          movieData.showtimes.forEach(showtime => {
            const dateStr = parseDate(showtime.dateText);

            // Use datetime attribute if available (more reliable)
            let timeNorm;
            if (showtime.timeAttr) {
              const timePart = showtime.timeAttr.split('T')[1].substring(0, 5);
              timeNorm = timePart;
            } else {
              timeNorm = normalizeTime(showtime.time);
            }

            const movie = {
              movie: movieData.title,
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
        }

      } catch (error) {
        console.warn(`  ⚠ Error fetching ${movieId}: ${error.message}`);
      }
    }

    await page.close();
    allMovies.push(...movies);
    console.log(`✓ Cinema City: Found ${movies.length} showtimes`);

  } catch (error) {
    console.error('✗ Cinema City scrape failed:', error.message);
  }
}

// ========== GRAND CINEMA ==========
async function scrapeGrandCinema(browser) {
  console.log('\n📺 Scraping Grand Cinema (leb.grandcinemasme.com)...');
  const baseUrl = 'https://leb.grandcinemasme.com';
  const movies = [];

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1024 });

    // Step 1: Get movie listing
    console.log('  Step 1: Fetching movie list from /en...');
    await page.goto(`${baseUrl}/en`, {
      waitUntil: 'networkidle2',
      timeout: 30000
    });

    const movieSlugs = await page.evaluate(() => {
      const slugs = new Set();
      document.querySelectorAll('a[href*="/movie/"]').forEach(el => {
        const href = el.getAttribute('href');
        const match = href.match(/\/movie\/([^\/]+)\/en/);
        if (match && match[1]) slugs.add(match[1]);
      });
      return Array.from(slugs);
    });

    console.log(`  Found ${movieSlugs.length} movies. Fetching details...`);

    // Step 2: For each movie, fetch detail page
    for (const slug of movieSlugs) {
      await delay(1000);

      try {
        const detailUrl = `${baseUrl}/movie/${slug}/en`;
        await page.goto(detailUrl, {
          waitUntil: 'networkidle2',
          timeout: 30000
        });

        const movieData = await page.evaluate((slug) => {
          const title = document.querySelector('h1')?.textContent?.trim() || slug.replace(/-/g, ' ');
          const showtimes = [];

          // Try to extract showtimes by branch
          document.querySelectorAll('[class*="branch"], .cinema-times, [id*="branch"]').forEach(branchEl => {
            const branchName = branchEl.querySelector('h2, h3, .branch-name, strong')?.textContent?.trim();

            branchEl.querySelectorAll('[class*="date"], .tab, .date-tab').forEach(dateEl => {
              const dateText = dateEl.textContent?.trim();

              dateEl.querySelectorAll('a[href*="sessions"], .time, a[class*="time"]').forEach(timeEl => {
                const timeRaw = timeEl.textContent?.trim();
                if (timeRaw && timeRaw.match(/\d{1,2}:\d{2}/)) {
                  showtimes.push({
                    branch: branchName || 'Not specified',
                    dateText: dateText,
                    time: timeRaw
                  });
                }
              });
            });
          });

          // Fallback: look for any time patterns
          if (showtimes.length === 0) {
            document.querySelectorAll('span, p, a').forEach(el => {
              const text = el.textContent?.trim();
              if (text && text.match(/\d{1,2}:\d{2}/)) {
                showtimes.push({
                  branch: 'Not specified',
                  dateText: null,
                  time: text
                });
              }
            });
          }

          return { title, showtimes };
        }, slug);

        if (movieData.title) {
          movieData.showtimes.forEach(showtime => {
            const dateStr = parseDate(showtime.dateText);
            const timeNorm = normalizeTime(showtime.time);

            const movie = {
              movie: movieData.title,
              date: dateStr,
              time: timeNorm,
              location: showtime.branch,
              cinema: 'Grand Cinema',
              url: detailUrl
            };

            if (isValidMovie(movie)) {
              movies.push(movie);
            }
          });
        }

      } catch (error) {
        console.warn(`  ⚠ Error fetching ${slug}: ${error.message}`);
      }
    }

    await page.close();
    allMovies.push(...movies);
    console.log(`✓ Grand Cinema: Found ${movies.length} showtimes`);

  } catch (error) {
    console.error('✗ Grand Cinema scrape failed:', error.message);
  }
}

async function runScraper() {
  console.log('🎬 Starting Cinema Scraper (Puppeteer Version)...');
  console.log('═'.repeat(60));
  console.log('Sites to scrape:');
  URLS.forEach((url, i) => console.log(`  ${i + 1}. ${url}`));
  console.log('═'.repeat(60));

  try {
    // Launch single shared browser instance
    browser = await puppeteer.launch({ headless: 'new' });

    await scrapeVoxCinemas(browser);
    await scrapeCinemaCity(browser);
    await scrapeGrandCinema(browser);

    // Remove duplicates
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
      if (a.date !== b.date) return b.date.localeCompare(a.date);
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
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

runScraper();
