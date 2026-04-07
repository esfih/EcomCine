const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage();
  const allErrors = [];
  const allLogs = [];
  const allScripts = [];

  page.on('console', m => allLogs.push('['+m.type()+'] '+m.text().slice(0, 200)));
  page.on('pageerror', e => allErrors.push('PAGEERROR: '+String(e).slice(0, 300)));
  page.on('request', r => { if(r.resourceType()==='script') allScripts.push(r.url()); });

  console.log('Loading agnieszka profile...');
  await page.goto('https://app.topdoctorchannel.us/person/agnieszka/', {waitUntil:'domcontentloaded', timeout:30000});
  
  // wait a bit for scripts to execute
  await page.waitForTimeout(4000);

  const state = await page.evaluate(() => ({
    tmPlayerMode: window.tmPlayerMode,
    vendorStoreData: window.vendorStoreData ? {
      playerMode: window.vendorStoreData.playerMode,
      userId: window.vendorStoreData.userId,
      ajaxurl: !!window.vendorStoreData.ajaxurl,
    } : null,
    tmShowcaseIds: window.tmShowcaseIds,
    tmVendorStoreRestUrl: window.tmVendorStoreRestUrl,
    vendorMedia: window.vendorMedia ? Object.keys(window.vendorMedia) : null,
  }));

  console.log('\n=== JS GLOBALS ===');
  console.log(JSON.stringify(state, null, 2));

  console.log('\n=== SCRIPTS LOADED ('+allScripts.length+') ===');
  allScripts.forEach(u=>console.log('  '+u));

  console.log('\n=== CONSOLE ('+allLogs.length+') ===');
  allLogs.forEach(l=>console.log('  '+l));

  if (allErrors.length) {
    console.log('\n=== PAGE ERRORS ===');
    allErrors.forEach(e=>console.log('  '+e));
  }

  // Get tab elements
  const tabs = await page.evaluate(() => {
    const all = document.querySelectorAll('[class*="tab"]');
    return Array.from(all).slice(0, 15).map(el => ({
      tag: el.tagName,
      cls: (el.className || '').toString().slice(0, 100),
      txt: (el.textContent || '').slice(0, 60).trim(),
    }));
  });
  console.log('\n=== TAB ELEMENTS ===');
  console.log(JSON.stringify(tabs, null, 2));

  // Get the page title and key structural elements
  const structure = await page.evaluate(() => {
    return {
      title: document.title,
      hasPlayerWrap: !!document.querySelector('#tm-player-wrap, .tm-player-wrap, [id*="player"]'),
      hasBody: !!document.querySelector('body'),
      bodyClasses: document.body.className.split(' ').slice(0, 15).join(' '),
    };
  });
  console.log('\n=== PAGE STRUCTURE ===');
  console.log(JSON.stringify(structure, null, 2));

  await browser.close();
  process.exit(0);
})();
