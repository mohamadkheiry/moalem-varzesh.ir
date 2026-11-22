const { readFileSync } = require('node:fs');
const assert = require('node:assert/strict');
const report = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const endpoint = new URL(process.env.MV_TEST_URL || 'http://127.0.0.1:8087');
assert.ok(endpoint.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(endpoint.hostname));
const counters = { product: 0, post: 0, links: 0 };
const issues = [];
const titles = new Set();
const descriptions = new Set();
const audited = new Set();

const property = (html, key) => [...html.matchAll(new RegExp('<meta (?:name|property)="' + key + '" content="([^"]+)"', 'gi'))].map(match => match[1]);
const relativeKey = url => decodeURIComponent(new URL(url, endpoint).pathname).replace(/\/$/, '');
function hasType(node, type) {
    if (!node || typeof node !== 'object') return false;
    return node['@type'] === type || Object.values(node).some(value => hasType(value, type));
}

async function inspect({ kind, url, hero, alt }) {
    const home = new URL(url);
    assert.equal(home.origin, endpoint.origin, 'Audit only executes against the local store.');
    const response = await fetch(url, { signal: AbortSignal.timeout(55000) });
    assert.equal(response.status, 200, url);
    if (kind === 'links') {
        counters.links++;
        return;
    }
    const html = await response.text();
    const title = html.match(/<title>([^<]+)<\/title>/i)?.[1];
    const description = property(html, 'description');
    const canonical = [...html.matchAll(/<link rel="canonical" href="([^"]+)"/gi)];
    assert.ok(title && !titles.has(title), 'Missing or duplicate page title');
    assert.ok(description.length === 1 && description[0].length > 25, 'Missing or duplicated meta description');
    assert.equal(canonical.length, 1, 'Page should have one canonical');
    assert.equal(relativeKey(canonical[0][1]), relativeKey(url), 'Canonical points to another route');
    assert.ok(property(html, 'og:image').length === 1, 'No social image');
    const blocks = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/gi)]
        .map(match => JSON.parse(match[1]));
    if (kind === 'product') {
        assert.ok(blocks.some(block => hasType(block, 'Product')), 'WooCommerce Product schema is missing');
        assert.ok(html.includes('mv-product-page') && html.includes('mv-product-stock') && html.includes('mv-product-buy')
            && html.includes('woocommerce-product-gallery'), 'Product overview, gallery or purchase area is missing');
        assert.ok(!JSON.stringify(blocks).includes('"priceCurrency":"IRT"'), 'Non-ISO currency used in product schema');
        assert.ok(!descriptions.has(description[0]), 'Duplicate product description');
        assert.ok(html.includes('mv-related-guides'), 'No product-to-guide navigation');
        descriptions.add(description[0]);
    } else {
        assert.ok(blocks.some(block => hasType(block, 'BlogPosting')), 'BlogPosting schema is missing');
        assert.ok(html.includes('guide-hero-image') && html.includes('guide-product-links'), 'Article has no photo or product links');
        const cover = html.match(/<figure class="guide-hero-image">([\s\S]*?)<\/figure>/)?.[1] || '';
        assert.ok(cover.includes('mv-guide-photograph') && cover.includes('srcset=') && cover.includes(`alt="${alt}"`),
            'Missing responsive article photo or its description');
        assert.ok(blocks.some(block => block['@type'] === 'BlogPosting' && block.image?.includes(hero)), 'Article schema has the wrong cover');
        const media = await fetch(hero, { signal: AbortSignal.timeout(55000) });
        assert.equal(media.status, 200, hero);
        assert.match(media.headers.get('content-type'), /^image\/webp/);
        assert.ok((await media.arrayBuffer()).byteLength <= 225 * 1024, 'Article cover exceeds its image budget');
    }
    titles.add(title);
    audited.add(relativeKey(url));
    counters[kind]++;
    if ((counters.post + counters.product) % 30 === 0) {
        console.log('Checked pages', counters.post + counters.product, 'of', report.pages.length);
    }
}

(async () => {
    const queue = [...report.pages];
    async function worker() {
        for (let item = queue.shift(); item; item = queue.shift()) {
            try { await inspect(item); }
            catch (error) { issues.push(item.url + ' ' + error.message); }
        }
    }
    await Promise.all(Array.from({ length: 4 }, worker));
    queue.push(...report.legacy_links.filter(url => !audited.has(relativeKey(url))).map(url => ({ kind: 'links', url })));
    await Promise.all(Array.from({ length: 4 }, worker));
    if (issues.length) {
        issues.slice(0, 30).forEach(issue => console.error(issue));
        process.exitCode = 1;
    }
    console.log(JSON.stringify({ ...counters, failures: issues.length }));
})().catch(error => { console.error(error.message); process.exitCode = 1; });
