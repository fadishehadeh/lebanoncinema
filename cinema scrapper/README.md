# Lebanese Cinema Scraper 🎬

A Node.js web scraper that extracts movie data (title, date, time, location, cinema) from Lebanese cinema websites and exports to JSON format.

## Supported Cinemas

- **Grand Cinema** - leb.grandcinemasme.com
- **Vox Cinemas** - lbn.voxcinemas.com
- **Cinema City** - cinemacitybeirut.com

## Prerequisites

- Node.js (v14 or higher)
- npm

## Installation

1. Clone or download this repository
2. Install dependencies:

```bash
npm install
```

This will install Puppeteer, which handles the web scraping with a headless browser.

## Usage

Run the scraper:

```bash
npm start
```

Or directly:

```bash
node cinema-scraper.js
```

## Output

The scraper generates a `movies.json` file in the current directory with the following structure:

```json
{
  "metadata": {
    "scrapedAt": "2026-05-24T10:30:00.000Z",
    "totalMovies": 42,
    "sources": [
      "https://leb.grandcinemasme.com/",
      "https://lbn.voxcinemas.com/",
      "https://www.cinemacitybeirut.com/browsing/",
      "https://lbn.voxcinemas.com/movies/whatson"
    ]
  },
  "movies": [
    {
      "movie": "The Shawshank Redemption",
      "date": "2026-05-24",
      "time": "19:30",
      "location": "Beirut - ABC",
      "cinema": "Vox Cinemas",
      "url": "https://lbn.voxcinemas.com/"
    },
    ...
  ]
}
```

## Data Fields

- **movie**: Movie title
- **date**: Date in YYYY-MM-DD format
- **time**: Showtime (when available)
- **location**: Cinema location/branch
- **cinema**: Cinema chain name
- **url**: Source URL where the data was scraped

## Features

- ✅ Scrapes multiple cinema websites
- ✅ Removes duplicate entries
- ✅ Sorts data by cinema and movie name
- ✅ Handles network errors gracefully
- ✅ Exports to clean JSON format
- ✅ Includes metadata about scrape results
- ✅ Progress indicators during scraping

## Customization

To modify the scraper:

1. **Add new sites**: Add URLs to the `URLS` array at the top of `cinema-scraper.js`
2. **Adjust selectors**: Modify the CSS selectors in each scrape function to match the actual website structure
3. **Change output path**: Modify the `outputPath` variable in the `runScraper()` function

## Troubleshooting

- **"Cannot find module 'puppeteer'"**: Run `npm install` to install dependencies
- **Empty results**: The CSS selectors may need adjustment based on actual website structure changes
- **Timeout errors**: Increase the `timeout` parameter in the `page.goto()` calls

## Notes

- The scraper uses a headless browser (Puppeteer) to handle dynamic content loading
- Network connections to scraped websites are required
- Scraping is subject to the terms of service of each website
- Some data fields may show "Not specified" if not available on the source website

## License

MIT
