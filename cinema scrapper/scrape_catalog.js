const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');

const headers = {
  'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
  Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
};

const ROOT_DIR = path.resolve(__dirname, '..');
const STORAGE_DIR = path.join(ROOT_DIR, 'storage', 'catalog');
const SNAPSHOT_DIR = path.join(STORAGE_DIR, 'snapshots');
const OVERRIDES_PATH = path.join(ROOT_DIR, 'scraper', 'catalog_title_overrides.json');

const titleOverrides = fs.existsSync(OVERRIDES_PATH)
  ? JSON.parse(fs.readFileSync(OVERRIDES_PATH, 'utf8'))
  : {};

const variantSuffixPatterns = [
  /\s*\((vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\)\s*$/i,
  /\s*[-:]\s*(vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\s*$/i,
  /\s+(vip|studio\.?15|3d|imax|4dx|gold|standard|gc)\s*$/i,
];

const sourceRegistry = [
  {
    key: 'vox',
    name: 'VOX Cinemas',
    baseUrl: 'https://lbn.voxcinemas.com',
    type: 'html',
    enabled: true,
    lists: {
      now_showing: {
        url: 'https://lbn.voxcinemas.com/movies/whatson',
        selector: 'article.movie-summary',
      },
      coming_soon: {
        url: 'https://lbn.voxcinemas.com/movies/comingsoon',
        selector: 'article.movie-summary',
      },
    },
    scrapeStatus: scrapeVoxStatus,
  },
  {
    key: 'grand',
    name: 'Grand Cinemas',
    baseUrl: 'https://leb.grandcinemasme.com',
    type: 'html',
    enabled: true,
    lists: {
      now_showing: {
        url: 'https://leb.grandcinemasme.com/en',
        selector: '#movies_1 .movie',
      },
      coming_soon: {
        url: 'https://leb.grandcinemasme.com/en',
        selector: '#movies_2 .movie',
      },
    },
    scrapeStatus: scrapeGrandStatus,
  },
  {
    key: 'cinemacity',
    name: 'Cinema City',
    baseUrl: 'https://www.cinemacitybeirut.com',
    type: 'json',
    enabled: true,
    lists: {
      now_showing: {
        url: 'https://www.cinemacitybeirut.com/Browsing/Home/NowShowing',
      },
      coming_soon: {
        url: 'https://www.cinemacitybeirut.com/Browsing/Home/ComingSoon',
      },
    },
    scrapeStatus: scrapeCinemaCityStatus,
  },
  {
    key: 'cinemall',
    name: 'Cinemall',
    baseUrl: 'https://www.cine-mall.com',
    type: 'html',
    enabled: true,
    lists: {
      now_showing: {
        url: 'https://www.cine-mall.com/',
        selector: 'section.part[data-id="1"] figure.effect-goliath',
      },
      coming_soon: {
        url: 'https://www.cine-mall.com/',
        selector: 'section.soon-part[data-id="2"] figure.effect-goliath',
      },
    },
    scrapeStatus: scrapeCinemallStatus,
  },
  {
    key: 'empire',
    name: 'Empire Cinemas',
    baseUrl: 'https://www.empirecinemas.com.lb',
    type: 'html',
    enabled: false,
    skipReason: 'Source host was not reachable during implementation; keep this disabled until selectors and availability are verified.',
    lists: {
      now_showing: {
        url: 'https://www.empirecinemas.com.lb/choueifat',
        selector: '.movie, .film',
      },
      coming_soon: {
        url: 'https://www.empirecinemas.com.lb/premier',
        selector: '.movie, .film',
      },
    },
    scrapeStatus: async () => [],
  },
];

async function fetchHtml(url) {
  const response = await axios.get(url, {
    headers,
    timeout: 45000,
    maxRedirects: 5,
  });
  return response.data;
}

function ensureStorage() {
  fs.mkdirSync(STORAGE_DIR, { recursive: true });
  fs.mkdirSync(SNAPSHOT_DIR, { recursive: true });
}

function normalizeSourceTitle(title) {
  let normalized = String(title || '').replace(/\s+/g, ' ').trim();

  let changed = true;
  while (changed && normalized) {
    changed = false;
    for (const pattern of variantSuffixPatterns) {
      if (pattern.test(normalized)) {
        normalized = normalized.replace(pattern, '').trim();
        changed = true;
      }
    }
  }

  return normalized;
}

function baseTitleKey(title) {
  const decoded = String(title || '')
    .replace(/&amp;/gi, '&')
    .replace(/&#39;/g, "'")
    .trim();

  const normalized = decoded
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  return normalized;
}

function canonicalizeTitle(title) {
  const cleanTitle = normalizeSourceTitle(title);
  const key = baseTitleKey(cleanTitle);
  const overridden = titleOverrides[key];
  const canonicalTitle = overridden ? String(overridden).trim() : cleanTitle;
  return {
    title: canonicalTitle,
    title_key: baseTitleKey(canonicalTitle),
    raw_title: cleanTitle,
    alias_applied: Boolean(overridden),
  };
}

function absoluteUrl(baseUrl, href) {
  if (!href) {
    return '';
  }
  try {
    return new URL(href, baseUrl).toString();
  } catch (error) {
    return href;
  }
}

function uniqueList(items) {
  return Array.from(new Set(items.filter(Boolean)));
}

function buildRecord(source, status, sourceListUrl, raw) {
  const canonical = canonicalizeTitle(raw.title);
  if (!canonical.title || canonical.title.length < 2) {
    return null;
  }

  return {
    title: canonical.title,
    title_key: canonical.title_key,
    raw_title: canonical.raw_title,
    status,
    chain: source.name,
    chain_key: source.key,
    source_list_url: sourceListUrl,
    source_detail_url: raw.source_detail_url || '',
    poster_url: raw.poster_url || '',
    scraped_at: new Date().toISOString(),
    alias_applied: canonical.alias_applied,
  };
}

async function scrapeVoxStatus(source, status) {
  const config = source.lists[status];
  const html = await fetchHtml(config.url);
  const $ = cheerio.load(html);
  const rows = [];

  $(config.selector).each((_, el) => {
    const title = $(el).attr('data-title') || $(el).find('h3 a, h4 a').first().text().trim();
    const slug = $(el).attr('data-slug') || '';
    const href = $(el).find('a').first().attr('href') || (slug ? `/movies/${slug}` : '');
    const posterUrl = absoluteUrl(source.baseUrl, $(el).find('img').first().attr('src') || $(el).find('img').first().attr('data-src') || '');
    const record = buildRecord(source, status, config.url, {
      title,
      source_detail_url: absoluteUrl(source.baseUrl, href),
      poster_url: posterUrl,
    });
    if (record) {
      rows.push(record);
    }
  });

  return rows;
}

async function scrapeGrandStatus(source, status) {
  const config = source.lists[status];
  const html = await fetchHtml(config.url);
  const $ = cheerio.load(html);
  const rows = [];

  $(config.selector).each((_, el) => {
    const title = $(el).find('.movie-hover-title, .movie-title').first().text().trim();
    const href = $(el).find('a.thumbnaila, a').first().attr('href') || '';
    const posterUrl = absoluteUrl(
      source.baseUrl,
      $(el).find('img').first().attr('data-src') || $(el).find('img').first().attr('src') || ''
    );
    const record = buildRecord(source, status, config.url, {
      title,
      source_detail_url: absoluteUrl(source.baseUrl, href),
      poster_url: posterUrl,
    });
    if (record) {
      rows.push(record);
    }
  });

  return rows;
}

async function scrapeCinemaCityStatus(source, status) {
  const config = source.lists[status];
  const response = await axios.get(config.url, {
    headers: {
      ...headers,
      Accept: 'application/json,text/plain,*/*',
    },
    timeout: 45000,
    maxRedirects: 5,
  });
  const payload = Array.isArray(response.data) ? response.data : [];
  const rows = [];
  const seen = new Set();

  payload.forEach((item) => {
    const movieId = item.Id || item.id;
    const detailUrl = movieId
      ? absoluteUrl(source.baseUrl, `/Browsing/Movies/Details/${movieId}`)
      : '';
    const posterUrl = absoluteUrl(source.baseUrl, item.PosterImageUrl || item.posterImageUrl || '');
    const title = item.Title || item.title || '';
    const record = buildRecord(source, status, config.url, {
      title,
      source_detail_url: detailUrl,
      poster_url: posterUrl,
    });

    if (record && !seen.has(detailUrl || record.title_key)) {
      seen.add(detailUrl || record.title_key);
      rows.push(record);
    }
  });

  return rows;
}

async function scrapeCinemallStatus(source, status) {
  const config = source.lists[status];
  const html = await fetchHtml(config.url);
  const $ = cheerio.load(html);
  const rows = [];

  $(config.selector).each((_, el) => {
    const card = $(el);
    const title = card.find('.title').first().text().replace(/\s+/g, ' ').trim();
    const href = card.find('a[href^="ticket/"]').first().attr('href') || '';
    const posterUrl = absoluteUrl(source.baseUrl, card.find('img').first().attr('src') || '');
    const record = buildRecord(source, status, config.url, {
      title,
      source_detail_url: absoluteUrl(source.baseUrl, href),
      poster_url: posterUrl,
    });

    if (record) {
      rows.push(record);
    }
  });

  return rows;
}

function aggregateRecords(records) {
  const map = new Map();

  records.forEach((record) => {
    if (!map.has(record.title_key)) {
      map.set(record.title_key, {
        title: record.title,
        title_key: record.title_key,
        status: record.status,
        statuses: [record.status],
        chains: [record.chain],
        chain_keys: [record.chain_key],
        source_list_urls: [record.source_list_url],
        source_detail_urls: record.source_detail_url ? [record.source_detail_url] : [],
        poster_url: record.poster_url || '',
        scraped_at: record.scraped_at,
        raw_titles: [record.raw_title],
        source_entries: [record],
      });
      return;
    }

    const current = map.get(record.title_key);
    current.statuses = uniqueList(current.statuses.concat(record.status));
    current.chains = uniqueList(current.chains.concat(record.chain));
    current.chain_keys = uniqueList(current.chain_keys.concat(record.chain_key));
    current.source_list_urls = uniqueList(current.source_list_urls.concat(record.source_list_url));
    current.source_detail_urls = uniqueList(current.source_detail_urls.concat(record.source_detail_url));
    current.raw_titles = uniqueList(current.raw_titles.concat(record.raw_title));
    current.source_entries.push(record);

    if (!current.poster_url && record.poster_url) {
      current.poster_url = record.poster_url;
    }
    if (record.status === 'now_showing') {
      current.status = 'now_showing';
    }
  });

  return Array.from(map.values()).sort((a, b) => {
    if (a.status !== b.status) {
      return a.status === 'now_showing' ? -1 : 1;
    }
    return a.title.localeCompare(b.title);
  });
}

function writeJson(filePath, payload) {
  fs.writeFileSync(filePath, JSON.stringify(payload, null, 2));
}

function rotateLatestSnapshot(latestPath, previousPath) {
  ensureStorage();
  if (!fs.existsSync(latestPath)) {
    return;
  }

  fs.copyFileSync(latestPath, previousPath);
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  fs.copyFileSync(latestPath, path.join(SNAPSHOT_DIR, `catalog-${stamp}.json`));
}

async function runCatalogScrape() {
  ensureStorage();

  const latestPath = path.join(STORAGE_DIR, 'catalog_latest.json');
  const previousPath = path.join(STORAGE_DIR, 'catalog_previous.json');
  rotateLatestSnapshot(latestPath, previousPath);

  const sourceReports = [];
  const allRecords = [];

  for (const source of sourceRegistry) {
    const report = {
      key: source.key,
      name: source.name,
      enabled: source.enabled,
      type: source.type,
      base_url: source.baseUrl,
      lists: source.lists,
      counts: {
        now_showing: 0,
        coming_soon: 0,
      },
      errors: [],
      skip_reason: source.skipReason || '',
    };

    if (!source.enabled) {
      sourceReports.push(report);
      continue;
    }

    for (const status of ['now_showing', 'coming_soon']) {
      try {
        const rows = await source.scrapeStatus(source, status);
        report.counts[status] = rows.length;
        allRecords.push(...rows);
      } catch (error) {
        report.errors.push(`${status}: ${error.message}`);
      }
    }

    sourceReports.push(report);
  }

  const items = aggregateRecords(allRecords);
  const scrapedAt = new Date().toISOString();
  const metadata = {
    scrapedAt,
    totalItems: items.length,
    totalNowShowing: items.filter((item) => item.status === 'now_showing').length,
    totalComingSoon: items.filter((item) => item.status === 'coming_soon').length,
    sourceCount: sourceReports.length,
    previousSnapshot: fs.existsSync(previousPath) ? previousPath : '',
  };

  const combinedPayload = { metadata, sources: sourceReports, items };
  const playingPayload = {
    metadata: { ...metadata, status: 'now_showing', totalItems: metadata.totalNowShowing },
    items: items.filter((item) => item.status === 'now_showing'),
  };
  const comingPayload = {
    metadata: { ...metadata, status: 'coming_soon', totalItems: metadata.totalComingSoon },
    items: items.filter((item) => item.status === 'coming_soon'),
  };

  writeJson(latestPath, combinedPayload);
  writeJson(path.join(STORAGE_DIR, 'playing_now.json'), playingPayload);
  writeJson(path.join(STORAGE_DIR, 'coming_soon.json'), comingPayload);

  console.log('Weekly catalog scrape complete');
  console.log(`Latest catalog: ${latestPath}`);
  console.log(`Now showing: ${metadata.totalNowShowing}`);
  console.log(`Coming soon: ${metadata.totalComingSoon}`);
  console.log('\nSource summary:');
  sourceReports.forEach((report) => {
    const status = report.enabled ? 'enabled' : `disabled (${report.skip_reason})`;
    console.log(`- ${report.name}: ${status}`);
    if (report.enabled) {
      console.log(`  now_showing=${report.counts.now_showing} coming_soon=${report.counts.coming_soon}`);
    }
    if (report.errors.length) {
      report.errors.forEach((error) => console.log(`  error: ${error}`));
    }
  });
}

runCatalogScrape().catch((error) => {
  console.error('Catalog scrape failed:', error);
  process.exit(1);
});
