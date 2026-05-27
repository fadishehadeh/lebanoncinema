const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const ROOT_DIR = path.resolve(__dirname, '..');
const SNAPSHOT_PATH = path.join(__dirname, 'movies.json');
const DB_EXPORT_PATH = path.join(ROOT_DIR, 'scraper', 'export_showtime_audit_db.php');
const REPORT_DIR = path.join(ROOT_DIR, 'storage', 'audits');
const REPORT_JSON_PATH = path.join(REPORT_DIR, 'showtime_audit_latest.json');
const REPORT_MD_PATH = path.join(REPORT_DIR, 'showtime_audit_latest.md');

const HTTP_HEADERS = {
  'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
  Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
};

const CHAIN_INFO = {
  vox: { name: 'VOX Cinemas', display: 'VOX' },
  cinemacity: { name: 'Cinema City', display: 'Cinema City' },
  grand: { name: 'Grand Cinema', display: 'Grand' },
};

const VARIANT_SUFFIX_PATTERNS = [
  /\s*\((vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\)\s*$/i,
  /\s*[-:]\s*(vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\s*$/i,
  /\s+(vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\s*$/i,
];

const CATEGORY_PRIORITY = [
  'wrong_date_in_db',
  'stale_in_db',
  'missing_in_scrape',
  'missing_in_db',
  'wrong_cinema_mapping',
  'format_mismatch',
  'extra_in_scrape',
  'title_match_risk',
];

const TITLE_OVERRIDES = new Map([
  ['mortal kombat 2', 'Mortal Kombat II'],
  ['mortal kombat ii', 'Mortal Kombat II'],
  ['billie eilish: hit me hard and soft', 'Billie Eilish - Hit Me Hard and Soft: The Tour'],
  ['billie eilish: hit me hard and soft (3d)', 'Billie Eilish - Hit Me Hard and Soft: The Tour'],
  ['billie eilish hit me hard and soft', 'Billie Eilish - Hit Me Hard and Soft: The Tour'],
  ['billie eilish - hit me hard and soft: the tour', 'Billie Eilish - Hit Me Hard and Soft: The Tour'],
  ['billie eilish hit me hard and soft the tour', 'Billie Eilish - Hit Me Hard and Soft: The Tour'],
  ['palestine 36', 'Palestine 36'],
  ["palestine '36", 'Palestine 36'],
]);

function getAuditWindow() {
  const start = getBeirutDateString();
  const [year, month, day] = start.split('-').map(Number);
  const end = new Date(Date.UTC(year, month - 1, day + 6));
  return {
    start,
    end: end.toISOString().slice(0, 10),
  };
}

function normalizeTitle(title) {
  let value = String(title || '').replace(/\s+/g, ' ').trim();
  let changed = true;
  while (changed && value) {
    changed = false;
    for (const pattern of VARIANT_SUFFIX_PATTERNS) {
      if (pattern.test(value)) {
        value = value.replace(pattern, '').trim();
        changed = true;
      }
    }
  }
  const overrideKey = value
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9' ]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return TITLE_OVERRIDES.get(overrideKey) || value;
}

function baseKey(value) {
  return normalizeTitle(String(value || ''))
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizeCinemaName(value) {
  const compact = String(value || '').replace(/\s+/g, ' ').trim();
  return compact
    .replace(/^Grand Cinemas?\s+/i, '')
    .replace(/^Grand\s+/i, '')
    .replace(/^VOX\s+/i, '')
    .replace(/^CinemaCity\s+/i, 'Cinema City ')
    .trim();
}

function cinemaKey(value) {
  return baseKey(normalizeCinemaName(value));
}

function normalizeFormat(value) {
  const format = String(value || 'Standard').trim().toUpperCase();
  if (!format || format === 'STD') {
    return 'Standard';
  }
  if (format === 'OFFLINE') {
    return 'Standard';
  }
  if (format === 'STANDARD') {
    return 'Standard';
  }
  return format;
}

function inferFormatFromTitle(title) {
  const match = String(title || '').match(/\((vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\)\s*$/i);
  return normalizeFormat(match ? match[1] : 'Standard');
}

function parseLocation(location, chainKey) {
  const raw = String(location || '').replace(/\s+/g, ' ').trim();
  if (chainKey === 'cinemacity') {
    return { cinemaName: 'CinemaCity Souks', format: 'Standard' };
  }

  const match = raw.match(/^(.*?)(?:\s*-\s*(STD|STANDARD|VIP|IMAX|4DX|GOLD|OFFLINE))?$/i);
  const cinemaName = normalizeCinemaName(match ? match[1] : raw);
  const format = normalizeFormat(match && match[2] ? match[2] : 'Standard');
  return { cinemaName, format };
}

function detectChainFromCinema(cinema) {
  const value = String(cinema || '').toLowerCase();
  if (value.includes('vox')) return 'vox';
  if (value.includes('grand')) return 'grand';
  if (value.includes('cinema city')) return 'cinemacity';
  return '';
}

function token(record) {
  return `${record.show_time}|${record.format}`;
}

function groupKey(record) {
  return [
    record.chain_key,
    record.movie_key,
    record.cinema_key,
    record.show_date,
  ].join('|');
}

function titleScopeKey(record) {
  return [
    record.chain_key,
    record.cinema_key,
    record.show_date,
    record.show_time,
  ].join('|');
}

function timeScopeKey(record) {
  return [
    record.chain_key,
    record.movie_key,
    record.cinema_key,
    record.show_time,
  ].join('|');
}

function cinemaScopeKey(record) {
  return [
    record.chain_key,
    record.movie_key,
    record.show_date,
    record.show_time,
  ].join('|');
}

function ensureDir(dirPath) {
  fs.mkdirSync(dirPath, { recursive: true });
}

function mapChainNameToKey(name) {
  const value = String(name || '').toLowerCase();
  if (value.includes('vox')) return 'vox';
  if (value.includes('cinema city') || value.includes('cinemacity')) return 'cinemacity';
  if (value.includes('grand')) return 'grand';
  return baseKey(name);
}

function normalizeRecord(input) {
  const movieTitle = normalizeTitle(input.movie_title || input.movie || input.title);
  const chainKey = input.chain_key || detectChainFromCinema(input.cinema) || mapChainNameToKey(input.chain_name);
  const chainName = input.chain_name || CHAIN_INFO[chainKey]?.name || input.cinema || '';
  const cinemaName = normalizeCinemaName(input.cinema_name || input.location || input.cinema || '');
  const format = normalizeFormat(input.format);
  const record = {
    chain_key: chainKey,
    chain_name: chainName,
    movie_title: movieTitle,
    movie_key: baseKey(movieTitle),
    cinema_name: cinemaName,
    cinema_key: cinemaKey(cinemaName),
    show_date: input.show_date || input.date,
    show_time: String(input.show_time || input.time || '').slice(0, 5),
    format,
    source_detail_url: input.source_detail_url || input.url || '',
  };
  return record;
}

function addGroupedRecord(map, rawRecord, origin) {
  if (!rawRecord.movie_title || !rawRecord.show_date || !rawRecord.show_time || !rawRecord.chain_key) {
    return;
  }
  const key = groupKey(rawRecord);
  if (!map.has(key)) {
    map.set(key, {
      chain_key: rawRecord.chain_key,
      chain_name: rawRecord.chain_name,
      movie_title: rawRecord.movie_title,
      movie_key: rawRecord.movie_key,
      cinema_name: rawRecord.cinema_name,
      cinema_key: rawRecord.cinema_key,
      show_date: rawRecord.show_date,
      source_detail_url: rawRecord.source_detail_url || '',
      official: [],
      scraped: [],
      db: [],
      official_set: new Set(),
      scraped_set: new Set(),
      db_set: new Set(),
      db_titles: new Set(),
    });
  }
  const group = map.get(key);
  const field = `${origin}_set`;
  const listField = origin;
  const valueToken = token(rawRecord);
  if (!group[field].has(valueToken)) {
    group[field].add(valueToken);
    group[listField].push({
      show_time: rawRecord.show_time,
      format: rawRecord.format,
    });
  }
  if (origin === 'db' && rawRecord.raw_db_title) {
    group.db_titles.add(rawRecord.raw_db_title);
  }
}

function sortTimes(items) {
  return [...items].sort((a, b) => {
    if (a.show_time !== b.show_time) return a.show_time.localeCompare(b.show_time);
    return a.format.localeCompare(b.format);
  });
}

function timesLabel(items) {
  return sortTimes(items).map(item => item.format === 'Standard' ? item.show_time : `${item.show_time} (${item.format})`);
}

function loadSnapshot() {
  const payload = JSON.parse(fs.readFileSync(SNAPSHOT_PATH, 'utf8'));
  return payload.movies || [];
}

function buildSnapshotRecords(rows) {
  const window = getAuditWindow();
  return rows.map(row => {
    const chainKey = detectChainFromCinema(row.cinema);
    const parsed = parseLocation(row.location, chainKey);
    const titleFormat = inferFormatFromTitle(row.movie);
    return normalizeRecord({
      chain_key: chainKey,
      chain_name: CHAIN_INFO[chainKey]?.name || row.cinema,
      movie_title: row.movie,
      cinema_name: parsed.cinemaName,
      show_date: row.date,
      show_time: row.time,
      format: parsed.format === 'Standard' ? titleFormat : parsed.format,
      source_detail_url: row.url,
    });
  }).filter(row => row.chain_key && row.movie_key && row.show_date >= window.start && row.show_date <= window.end);
}

function loadDbRows() {
  const result = spawnSync('php', [DB_EXPORT_PATH], {
    cwd: ROOT_DIR,
    encoding: 'utf8',
  });

  if (result.status !== 0) {
    throw new Error(`DB export failed: ${result.stderr || result.stdout}`.trim());
  }

  const payload = JSON.parse(result.stdout.trim() || '{}');
  if (payload.error) {
    throw new Error(payload.message || 'Unknown DB export error');
  }

  return (payload.rows || []).map(row => normalizeRecord({
    chain_key: mapChainNameToKey(row.chain_name),
    chain_name: row.chain_name,
    movie_title: row.movie_title,
    cinema_name: row.cinema_name,
    show_date: row.show_date,
    show_time: row.show_time,
    format: row.format,
    raw_db_title: row.movie_title,
  }));
}

async function fetchHtml(url) {
  const response = await axios.get(url, {
    headers: HTTP_HEADERS,
    timeout: 30000,
    maxRedirects: 5,
  });
  return response.data;
}

function collectSnapshotUrlMap(rows, chainKey) {
  const map = new Map();
  for (const row of rows) {
    const key = detectChainFromCinema(row.cinema);
    if (key !== chainKey) continue;
    if (!row.url) continue;
    if (!map.has(row.url)) {
      map.set(row.url, row.movie);
    }
  }
  return map;
}

async function fetchOfficialCinemaCity(snapshotRows) {
  const urlMap = collectSnapshotUrlMap(snapshotRows, 'cinemacity');
  const records = [];
  for (const [url, snapshotTitle] of urlMap.entries()) {
    const html = await fetchHtml(url);
    const $ = cheerio.load(html);
    const movieTitle = snapshotTitle || $('h3.boxout-title').first().text().trim();
    const format = inferFormatFromTitle(movieTitle);
    $('div.session, div.future.session').each((_, el) => {
      const dateText = $(el).find('h4.session-date').first().text().trim();
      const date = parseCinemaCityDate(dateText);
      $(el).find('a.session-time time').each((__, timeEl) => {
        const timeAttr = $(timeEl).attr('datetime') || '';
        const time = timeAttr.includes('T')
          ? timeAttr.split('T')[1].slice(0, 5)
          : normalizeClock($(timeEl).text().trim());
        records.push(normalizeRecord({
          chain_key: 'cinemacity',
          chain_name: CHAIN_INFO.cinemacity.name,
          movie_title: movieTitle,
          cinema_name: 'CinemaCity Souks',
          show_date: date,
          show_time: time,
          format,
          source_detail_url: url,
        }));
      });
    });
  }
  const window = getAuditWindow();
  return dedupeRecords(records).filter(record => record.show_date >= window.start && record.show_date <= window.end);
}

function parseCinemaCityDate(value) {
  const clean = String(value || '').replace(/^[A-Za-z]+,\s*/, '').trim();
  const parsed = new Date(clean);
  const year = parsed.getFullYear();
  const month = String(parsed.getMonth() + 1).padStart(2, '0');
  const day = String(parsed.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function normalizeClock(value) {
  const match = String(value || '').match(/(\d{1,2}):(\d{2})\s*(am|pm)?/i);
  if (!match) return String(value || '').slice(0, 5);
  let hours = parseInt(match[1], 10);
  const minutes = match[2];
  const period = match[3] ? match[3].toLowerCase() : '';
  if (period === 'pm' && hours !== 12) hours += 12;
  if (period === 'am' && hours === 12) hours = 0;
  return `${String(hours).padStart(2, '0')}:${minutes}`;
}

function getBeirutDateString() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Beirut',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const values = {};
  for (const part of parts) {
    if (part.type !== 'literal') {
      values[part.type] = part.value;
    }
  }
  return `${values.year}-${values.month}-${values.day}`;
}

async function fetchOfficialVox(snapshotRows) {
  const urlMap = collectSnapshotUrlMap(snapshotRows, 'vox');
  const records = [];
  for (const [url] of urlMap.entries()) {
    const html = await fetchHtml(url);
    const $ = cheerio.load(html);
    const movieTitle = $('h1').first().text().trim() || $('title').first().text().trim();
    const parsedUrl = new URL(url);
    const dateParam = parsedUrl.searchParams.get('d') || '';
    const showDate = dateParam
      ? `${dateParam.slice(0, 4)}-${dateParam.slice(4, 6)}-${dateParam.slice(6, 8)}`
      : '';

    $('div.dates').each((_, datesEl) => {
      const location = normalizeCinemaName($(datesEl).find('h3.highlight').first().text().trim() || 'City Centre Beirut');
      $(datesEl).find('ol.showtimes > li').each((__, screenEl) => {
        const screenType = normalizeFormat($(screenEl).find('strong').first().text().trim() || 'Standard');
        $(screenEl).find('a.action.showtime').each((___, timeEl) => {
          const time = normalizeClock($(timeEl).text().trim());
          records.push(normalizeRecord({
            chain_key: 'vox',
            chain_name: CHAIN_INFO.vox.name,
            movie_title: movieTitle,
            cinema_name: `VOX ${location}`.replace(/\s+/g, ' ').trim(),
            show_date: showDate,
            show_time: time,
            format: screenType,
            source_detail_url: url,
          }));
        });
      });
    });
  }
  const window = getAuditWindow();
  return dedupeRecords(records).filter(record => record.show_date >= window.start && record.show_date <= window.end);
}

async function fetchOfficialGrand(snapshotRows) {
  const urlMap = collectSnapshotUrlMap(snapshotRows, 'grand');
  const records = [];
  for (const [url] of urlMap.entries()) {
    const html = await fetchHtml(url);
    const $ = cheerio.load(html);
    const movieTitle = $('.movietitle').first().text().trim() || $('title').first().text().trim();

    $('.search-div').each((_, rowEl) => {
      const dateLabel = $(rowEl).find('.search-item').first().text().replace(/\s+/g, ' ').trim();
      const showDate = dateLabel.toLowerCase() === 'today' ? getBeirutDateString() : dateLabel;
      const cinemaName = normalizeCinemaName($(rowEl).find('.search-item.col.l4.s6 .leftonmobile').first().text().trim());
      if (!cinemaName || !showDate) return;

      let currentFormat = 'Standard';
      $(rowEl).find('.experience-search-div').each((__, expEl) => {
        const expAttr = $(expEl).attr('data-experience') || '';
        const ticketLabel = $(expEl).find('.rightonmobile').first().text().trim();
        if ($(expEl).hasClass('ticketname')) {
          currentFormat = normalizeFormat(ticketLabel || expAttr || 'Standard');
          return;
        }

        const expFormat = normalizeFormat(expAttr || currentFormat || 'Standard');
        $(expEl).find('a.btn-time.time-search-element').each((___, timeEl) => {
          const time = normalizeClock($(timeEl).attr('data-time') || $(timeEl).text().trim());
          records.push(normalizeRecord({
            chain_key: 'grand',
            chain_name: CHAIN_INFO.grand.name,
            movie_title: movieTitle,
            cinema_name: `Grand Cinemas ${cinemaName}`.replace(/\s+/g, ' ').trim(),
            show_date: showDate,
            show_time: time,
            format: expFormat,
            source_detail_url: url,
          }));
        });
      });
    });
  }
  const window = getAuditWindow();
  return dedupeRecords(records).filter(record => record.show_date >= window.start && record.show_date <= window.end);
}

function uniqueMatches(value, regex) {
  const matches = String(value || '').match(regex) || [];
  return [...new Set(matches)];
}

function dedupeRecords(records) {
  return [...new Map(records.map(record => [`${groupKey(record)}|${token(record)}`, record])).values()];
}

function buildIndexes(records) {
  const grouped = new Map();
  const sameTimeDifferentDate = new Map();
  const sameTimeDifferentCinema = new Map();
  const sameSlotDifferentTitle = new Map();
  const titleKeysByCanonical = new Map();

  for (const record of records) {
    addGroupedRecord(grouped, record, 'db');
    const timeKey = timeScopeKey(record);
    if (!sameTimeDifferentDate.has(timeKey)) sameTimeDifferentDate.set(timeKey, new Set());
    sameTimeDifferentDate.get(timeKey).add(record.show_date);

    const cinemaKeyValue = cinemaScopeKey(record);
    if (!sameTimeDifferentCinema.has(cinemaKeyValue)) sameTimeDifferentCinema.set(cinemaKeyValue, new Set());
    sameTimeDifferentCinema.get(cinemaKeyValue).add(record.cinema_key);

    const titleKeyValue = titleScopeKey(record);
    if (!sameSlotDifferentTitle.has(titleKeyValue)) sameSlotDifferentTitle.set(titleKeyValue, new Set());
    sameSlotDifferentTitle.get(titleKeyValue).add(record.movie_key);

    const canonical = `${record.chain_key}|${record.movie_key}`;
    if (!titleKeysByCanonical.has(canonical)) titleKeysByCanonical.set(canonical, new Set());
    titleKeysByCanonical.get(canonical).add(record.movie_title);
  }

  return {
    grouped,
    sameTimeDifferentDate,
    sameTimeDifferentCinema,
    sameSlotDifferentTitle,
    titleKeysByCanonical,
  };
}

function addOrigins(grouped, records, origin) {
  for (const record of records) {
    addGroupedRecord(grouped, record, origin);
  }
}

function hasDifferentDate(indexes, record) {
  const dates = indexes.sameTimeDifferentDate.get(timeScopeKey(record));
  return dates ? [...dates].some(date => date !== record.show_date) : false;
}

function hasDifferentCinema(indexes, record) {
  const cinemas = indexes.sameTimeDifferentCinema.get(cinemaScopeKey(record));
  return cinemas ? [...cinemas].some(value => value !== record.cinema_key) : false;
}

function hasTimeCollision(tokenSet, tokenValue) {
  const [showTime] = String(tokenValue || '').split('|');
  for (const tokenValueInSet of tokenSet) {
    const [candidateTime] = String(tokenValueInSet || '').split('|');
    if (candidateTime === showTime) {
      return true;
    }
  }
  return false;
}

function likelyRootCause(category) {
  switch (category) {
    case 'wrong_date_in_db':
      return 'DB contains a session time under the wrong date, likely from stale import residue or shifted source parsing.';
    case 'stale_in_db':
      return 'DB kept an obsolete session that no longer exists in the scrape or official source.';
    case 'missing_in_scrape':
      return 'Live source exposes a session that the scraper failed to capture.';
    case 'missing_in_db':
      return 'Scraper captured the session, but import/rendered DB rows are missing it.';
    case 'wrong_cinema_mapping':
      return 'Session time exists but appears attached to the wrong cinema mapping.';
    case 'format_mismatch':
      return 'Session exists, but the stored format differs from the live source.';
    case 'extra_in_scrape':
      return 'Scraper contains a session not visible on the live source page.';
    case 'title_match_risk':
      return 'A session appears to be attached to the wrong canonical movie row.';
    default:
      return 'Manual review required.';
  }
}

function compareGroups(grouped, dbIndexes) {
  const mismatches = [];
  const totalsByChain = {};

  for (const group of grouped.values()) {
    const officialTimes = timesLabel(group.official);
    const scrapedTimes = timesLabel(group.scraped);
    const dbTimes = timesLabel(group.db);
    const chainKey = group.chain_key;
    if (!totalsByChain[chainKey]) {
      totalsByChain[chainKey] = { groups: 0, affected_movies: new Set(), categories: {} };
    }
    totalsByChain[chainKey].groups++;

    const formatMismatch = detectFormatMismatch(group);

    const officialOnly = [...group.official_set].filter(value =>
      !group.scraped_set.has(value) && !hasTimeCollision(group.scraped_set, value)
    );
    if (officialOnly.length) {
      mismatches.push(buildMismatchEntry(group, 'missing_in_scrape', officialTimes, scrapedTimes, dbTimes));
    }

    const scrapeOnly = [...group.scraped_set].filter(value =>
      !group.official_set.has(value) && !hasTimeCollision(group.official_set, value)
    );
    if (scrapeOnly.length) {
      mismatches.push(buildMismatchEntry(group, 'extra_in_scrape', officialTimes, scrapedTimes, dbTimes));
    }

    const missingDb = [...group.official_set].filter(value =>
      !group.db_set.has(value) && !hasTimeCollision(group.db_set, value)
    );
    if (missingDb.length) {
      const category = classifyDbAbsence(group, missingDb, dbIndexes);
      mismatches.push(buildMismatchEntry(group, category, officialTimes, scrapedTimes, dbTimes));
    }

    const staleDb = [...group.db_set].filter(value =>
      !group.official_set.has(value) &&
      !group.scraped_set.has(value) &&
      !hasTimeCollision(group.official_set, value) &&
      !hasTimeCollision(group.scraped_set, value)
    );
    if (staleDb.length) {
      const category = classifyDbExtra(group, staleDb, dbIndexes);
      mismatches.push(buildMismatchEntry(group, category, officialTimes, scrapedTimes, dbTimes));
    }

    if (formatMismatch) {
      mismatches.push(buildMismatchEntry(group, 'format_mismatch', officialTimes, scrapedTimes, dbTimes));
    }
  }

  const deduped = dedupeMismatchEntries(mismatches);
  for (const entry of deduped) {
    const bucket = totalsByChain[entry.chain] || (totalsByChain[entry.chain] = { groups: 0, affected_movies: new Set(), categories: {} });
    bucket.affected_movies.add(entry.movie_title);
    bucket.categories[entry.category] = (bucket.categories[entry.category] || 0) + 1;
  }

  for (const chainKey of Object.keys(totalsByChain)) {
    totalsByChain[chainKey].affected_movies_count = totalsByChain[chainKey].affected_movies.size;
    delete totalsByChain[chainKey].affected_movies;
  }

  deduped.sort((a, b) => {
    const priorityDiff = CATEGORY_PRIORITY.indexOf(a.category) - CATEGORY_PRIORITY.indexOf(b.category);
    if (priorityDiff !== 0) return priorityDiff;
    if (a.chain !== b.chain) return a.chain.localeCompare(b.chain);
    if (a.movie_title !== b.movie_title) return a.movie_title.localeCompare(b.movie_title);
    if (a.cinema_name !== b.cinema_name) return a.cinema_name.localeCompare(b.cinema_name);
    return a.show_date.localeCompare(b.show_date);
  });

  return { mismatches: deduped, totalsByChain };
}

function classifyDbAbsence(group, missingDbTokens, dbIndexes) {
  const records = missingDbTokens.map(value => {
    const [show_time, format] = value.split('|');
    return {
      chain_key: group.chain_key,
      movie_key: group.movie_key,
      cinema_key: group.cinema_key,
      show_date: group.show_date,
      show_time,
      format,
    };
  });

  if (records.some(record => hasDifferentDate(dbIndexes, record))) return 'wrong_date_in_db';
  if (records.some(record => hasDifferentCinema(dbIndexes, record))) return 'wrong_cinema_mapping';
  return 'missing_in_db';
}

function classifyDbExtra(group, staleDbTokens, dbIndexes) {
  const records = staleDbTokens.map(value => {
    const [show_time, format] = value.split('|');
    return {
      chain_key: group.chain_key,
      movie_key: group.movie_key,
      cinema_key: group.cinema_key,
      show_date: group.show_date,
      show_time,
      format,
    };
  });

  if (records.some(record => hasDifferentDate(dbIndexes, record))) return 'wrong_date_in_db';
  if (records.some(record => hasDifferentCinema(dbIndexes, record))) return 'wrong_cinema_mapping';
  return 'stale_in_db';
}

function detectFormatMismatch(group) {
  const officialByTime = new Map(sortTimes(group.official).map(item => [item.show_time, item.format]));
  const scrapeByTime = new Map(sortTimes(group.scraped).map(item => [item.show_time, item.format]));
  const dbByTime = new Map(sortTimes(group.db).map(item => [item.show_time, item.format]));
  for (const [time, format] of officialByTime.entries()) {
    if (scrapeByTime.has(time) && scrapeByTime.get(time) !== format) return true;
    if (dbByTime.has(time) && dbByTime.get(time) !== format) return true;
  }
  return false;
}

function buildMismatchEntry(group, category, officialTimes, scrapedTimes, dbTimes) {
  return {
    chain: group.chain_key,
    chain_name: CHAIN_INFO[group.chain_key]?.display || group.chain_name,
    movie_title: group.movie_title,
    movie_key: group.movie_key,
    cinema_name: group.cinema_name,
    show_date: group.show_date,
    official_times: officialTimes,
    scraped_times: scrapedTimes,
    db_times: dbTimes,
    category,
    likely_root_cause: likelyRootCause(category),
    source_detail_url: group.source_detail_url,
  };
}

function dedupeMismatchEntries(entries) {
  const map = new Map();
  for (const entry of entries) {
    const key = [
      entry.chain,
      entry.movie_key,
      entry.cinema_name,
      entry.show_date,
      entry.category,
    ].join('|');
    if (!map.has(key)) {
      map.set(key, entry);
    }
  }
  return [...map.values()];
}

function buildReport({ officialRecords, snapshotRecords, dbRecords, mismatches, totalsByChain }) {
  const totals = {
    official_rows: officialRecords.length,
    scraped_rows: snapshotRecords.length,
    db_rows: dbRecords.length,
    mismatch_count: mismatches.length,
    by_chain: {},
    by_category: {},
  };

  for (const [chain, info] of Object.entries(totalsByChain)) {
    totals.by_chain[chain] = {
      chain_name: CHAIN_INFO[chain]?.display || chain,
      groups_audited: info.groups,
      affected_movies_count: info.affected_movies_count,
      category_counts: info.categories,
    };
  }

  for (const entry of mismatches) {
    totals.by_category[entry.category] = (totals.by_category[entry.category] || 0) + 1;
  }

  const affectedMovies = {};
  for (const entry of mismatches) {
    if (!affectedMovies[entry.chain]) affectedMovies[entry.chain] = new Set();
    affectedMovies[entry.chain].add(entry.movie_title);
  }

  return {
    generated_at: new Date().toISOString(),
    chains: Object.entries(CHAIN_INFO).map(([key, info]) => ({ key, name: info.display })),
    totals,
    mismatches,
    affected_movies: Object.fromEntries(
      Object.entries(affectedMovies).map(([chain, titles]) => [chain, [...titles].sort()])
    ),
  };
}

function renderMarkdown(report) {
  const lines = [];
  lines.push('# Showtime Audit Report');
  lines.push('');
  lines.push(`Generated at: ${report.generated_at}`);
  lines.push('');
  lines.push('## Totals');
  lines.push('');
  lines.push(`- Official rows: ${report.totals.official_rows}`);
  lines.push(`- Scraped rows: ${report.totals.scraped_rows}`);
  lines.push(`- DB rows: ${report.totals.db_rows}`);
  lines.push(`- Mismatches: ${report.totals.mismatch_count}`);
  lines.push('');
  lines.push('## By Chain');
  lines.push('');
  for (const [chain, info] of Object.entries(report.totals.by_chain)) {
    lines.push(`- ${info.chain_name}: ${info.groups_audited} groups audited, ${info.affected_movies_count} affected movies`);
    for (const category of CATEGORY_PRIORITY) {
      if (info.category_counts[category]) {
        lines.push(`  - ${category}: ${info.category_counts[category]}`);
      }
    }
  }
  lines.push('');
  lines.push('## Mismatches');
  lines.push('');
  if (!report.mismatches.length) {
    lines.push('No mismatches detected.');
  } else {
    for (const entry of report.mismatches) {
      lines.push(`### ${entry.chain_name} | ${entry.movie_title} | ${entry.cinema_name} | ${entry.show_date}`);
      lines.push(`- Category: ${entry.category}`);
      lines.push(`- Official: ${entry.official_times.join(', ') || '(none)'}`);
      lines.push(`- Scraped: ${entry.scraped_times.join(', ') || '(none)'}`);
      lines.push(`- DB: ${entry.db_times.join(', ') || '(none)'}`);
      lines.push(`- Likely root cause: ${entry.likely_root_cause}`);
      lines.push(`- Source: ${entry.source_detail_url || '(none)'}`);
      lines.push('');
    }
  }
  return lines.join('\n');
}

async function main() {
  ensureDir(REPORT_DIR);

  const snapshotRows = loadSnapshot();
  const snapshotRecords = buildSnapshotRecords(snapshotRows);
  const dbRecords = loadDbRows();

  const officialRecords = dedupeRecords([
    ...(await fetchOfficialVox(snapshotRows)),
    ...(await fetchOfficialCinemaCity(snapshotRows)),
    ...(await fetchOfficialGrand(snapshotRows)),
  ]);

  const grouped = new Map();
  addOrigins(grouped, officialRecords, 'official');
  addOrigins(grouped, snapshotRecords, 'scraped');
  addOrigins(grouped, dbRecords.map(row => ({ ...row, raw_db_title: row.movie_title })), 'db');

  const dbIndexes = buildIndexes(dbRecords.map(row => ({ ...row, raw_db_title: row.movie_title })));
  const comparison = compareGroups(grouped, dbIndexes);
  const report = buildReport({
    officialRecords,
    snapshotRecords,
    dbRecords,
    mismatches: comparison.mismatches,
    totalsByChain: comparison.totalsByChain,
  });

  fs.writeFileSync(REPORT_JSON_PATH, JSON.stringify(report, null, 2));
  fs.writeFileSync(REPORT_MD_PATH, renderMarkdown(report));

  console.log(`Audit complete. JSON: ${REPORT_JSON_PATH}`);
  console.log(`Markdown: ${REPORT_MD_PATH}`);
  console.log(`Mismatches: ${report.totals.mismatch_count}`);
}

main().catch(error => {
  console.error(error);
  process.exit(1);
});
