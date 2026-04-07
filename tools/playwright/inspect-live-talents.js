const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://app.topdoctorchannel.us/talents/', { waitUntil: 'networkidle', timeout: 30000 });

  // Get store-content HTML for first 6 cards
  const cards = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('.dokan-single-seller')).slice(0, 6).map(el => {
      const content = el.querySelector('.store-content');
      return content ? content.innerHTML.replace(/\s+/g, ' ').trim() : '(no store-content)';
    });
  });
  cards.forEach((c, i) => {
    console.log('--- Card ' + (i + 1) + ' ---');
    console.log(c);
    console.log('');
  });

  // Also check what CSS file is loaded and its URL
  const cssLinks = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
      .map(l => l.href)
      .filter(h => h.includes('vendor-store') || h.includes('tm-store'));
  });
  console.log('--- CSS files ---');
  cssLinks.forEach(l => console.log(l));

  // Check the plugin version loaded
  const ver = await page.evaluate(() => {
    const link = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
      .find(l => l.href.includes('vendor-store'));
    return link ? link.href : 'not found';
  });
  console.log('vendor-store.css URL:', ver);

  await browser.close();
})();
