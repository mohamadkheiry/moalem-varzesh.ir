const { chromium } = require('playwright');
const fs = require('node:fs');
const product = require('../catalog/products.json').find(item => item.id === 12556);
const assert = require('node:assert/strict');
const base = new URL(process.env.MV_TEST_URL || 'http://127.0.0.1:8087').origin;
assert.match(base, /^http:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?$/);
const paths = {
    home: '/', shop: '/shop/', product: new URL(product.permalink).pathname,
    guides: '/blog/', article: '/futsal-court-sole-guide/',
};

(async () => {
    const browser = await chromium.launch({ executablePath: process.env.MV_CHROME || undefined });
    try {
        const output = {};
        for (const [name, path] of Object.entries(paths)) {
            if (process.env.MV_WARM_CACHE === '1') {
                const warmup = await fetch(base + path, { headers: { Accept: 'text/html' } });
                assert.equal(warmup.status, 200, path);
                await warmup.text();
            }
            output[name] = [];
            for (let sample = 0; sample < 3; sample++) {
                const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, serviceWorkers: 'block' });
                try {
                    const page = await context.newPage();
                    await page.addInitScript(() => {
                        window.mvMeasuredLcp = 0;
                        new PerformanceObserver(list => {
                            window.mvMeasuredLcp = list.getEntries().at(-1)?.startTime || window.mvMeasuredLcp;
                        }).observe({ type: 'largest-contentful-paint', buffered: true });
                    });
                    const response = await page.goto(base + path, { waitUntil: 'load' });
                    if (response.status() !== 200) throw new Error(name + ' returned ' + response.status());
                    await page.waitForFunction(() => window.mvMeasuredLcp > 0);
                    const timing = await page.evaluate(() => {
                        const navigation = performance.getEntriesByType('navigation')[0];
                        const resources = performance.getEntriesByType('resource');
                        return {
                            ttfb: Math.round(navigation.responseStart), lcp: Math.round(window.mvMeasuredLcp),
                            transferredKiB: Math.round((navigation.transferSize + resources.reduce((sum, resource) => sum + resource.transferSize, 0)) / 1024),
                            requests: resources.length + 1,
                            scripts: resources.filter(item => item.initiatorType === 'script').map(item => new URL(item.name).pathname),
                            images: resources.filter(item => item.initiatorType === 'img').map(item => [new URL(item.name).pathname, item.transferSize]),
                        };
                    });
                    output[name].push({ ...timing, cache: response.headers()['x-wp-super-cache'] || '' });
                } finally {
                    await context.close();
                }
            }
            console.log(name, output[name].map(({ ttfb, lcp, transferredKiB, requests, cache }) => ({ ttfb, lcp, transferredKiB, requests, cache })));
        }
        if (process.argv[2]) fs.writeFileSync(process.argv[2], JSON.stringify(output, null, 2));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
