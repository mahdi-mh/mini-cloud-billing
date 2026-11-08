// Stale search results, in a real browser.
//
// The answer for an old search is made slow on purpose. It must arrive after the answer
// for the newer search and still must not replace it.
//
// Needs the app running with the demo data:
//   docker compose exec laravel.test php artisan migrate:fresh --seed --class=DemoSeeder   (or db:seed --class=DemoSeeder)
//   npm run test:browser
import puppeteer from 'puppeteer-core';

const BASE = process.env.BASE_URL ?? 'http://localhost:8085';
const CHROME = process.env.CHROME_PATH ?? '/usr/bin/google-chrome';
const SLOW_MS = 3000;

const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1300, height: 900 });
let failed = false;

const check = (ok, message) => {
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${message}`);
    failed ||= !ok;
};

try {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.type('input[type=email]', 'viewer@minicloud.test');
    await page.type('input[type=password]', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    await page.goto(`${BASE}/servers`, { waitUntil: 'networkidle0' });

    // the search for "alpha" is slow, everything else is fast
    let slowRequests = 0;
    await page.setRequestInterception(true);
    page.on('request', (request) => {
        const isAlphaSearch = request.url().includes('/livewire') && request.method() === 'POST' && (request.postData() ?? '').includes('alpha');

        if (isAlphaSearch) {
            slowRequests++;
            setTimeout(() => request.continue().catch(() => {}), SLOW_MS);
        } else {
            request.continue().catch(() => {});
        }
    });

    const search = 'input[placeholder^="Search"]';
    // what typing does: change the value and fire "input" (headless Chrome drops real key presses here)
    const type = (text) => page.$eval(search, (el, value) => {
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }, text);

    await type('alpha');
    await new Promise((r) => setTimeout(r, 600)); // debounce passed: the slow request is on its way

    await type('beta'); // the newer search, answered at once
    await new Promise((r) => setTimeout(r, SLOW_MS + 2500)); // long enough for the old answer to arrive too

    const names = await page.$$eval('table tbody tr td:first-child', (cells) => cells.map((c) => c.textContent.trim()));
    const typed = await page.$eval(search, (el) => el.value);

    check(slowRequests > 0, 'the search for "alpha" really was slow');
    check(typed === 'beta', `the box still says "beta" (got "${typed}")`);
    check(names.length > 0 && names.every((n) => n.startsWith('beta')), `the list shows only beta servers (got ${JSON.stringify(names)})`);
    check(page.url().includes('q=beta'), `the url says q=beta (got ${page.url()})`);
} finally {
    await browser.close();
}

process.exit(failed ? 1 : 0);
