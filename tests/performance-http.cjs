const assert = require('node:assert/strict');
const products = require('../catalog/products.json');
const base = new URL(process.env.MV_TEST_URL || 'http://127.0.0.1:8087').origin;
assert.match(base, /^http:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?$/);
const product = new URL(products.find(item => item.id === 12556).permalink).pathname;

const visit = (path, headers = {}) => fetch(base + path, {
    headers: { Accept: 'text/html', 'Accept-Encoding': 'gzip', ...headers },
    redirect: 'manual',
});
const hit = response => response.headers.get('x-wp-super-cache') || '';

(async () => {
    for (const [path, expected] of [
        ['/', /data-hero/],
        ['/shop/', /woocommerce-ordering/],
        ['/blog/', /class="guide-grid"/],
        ['/futsal-court-sole-guide/', /"BlogPosting"/],
        [product, /"Product"/],
    ]) {
        const first = await visit(path);
        assert.equal(first.status, 200, path);
        await first.text();
        const warm = await visit(path);
        assert.equal(warm.status, 200, path);
        assert.match(hit(warm), /Served .*cache/i, path);
        assert.equal(warm.headers.get('content-encoding'), 'gzip');
        assert.match(await warm.text(), expected, path);
    }
    for (const path of ['/cart/', '/checkout/', '/my-account/', '/my-account/?mode=otp', '/shop/?orderby=price', '/wp-json/']) {
        const response = await visit(path);
        assert.ok([200, 301, 302, 303].includes(response.status), path);
        assert.equal(hit(response), '', path);
    }
    for (const token of ['woocommerce_items_in_cart=1', 'wp_woocommerce_session_test=1', 'mv_account_session=1']) {
        assert.equal(hit(await visit('/', { Cookie: token })), '', token);
    }
    const prefetch = await visit('/my-account/?return=cart', { 'Sec-Purpose': 'prefetch' });
    assert.equal(prefetch.status, 204);
    assert.ok(!prefetch.headers.getSetCookie().some(cookie => cookie.startsWith('mv_account_session=')));

    const cssPath = '/wp-content/themes/moalem-varzesh/style.css?ver=mv-perf-test';
    const style = await visit(cssPath);
    assert.equal(style.status, 200);
    assert.equal(style.headers.get('content-encoding'), 'gzip');
    assert.match(style.headers.get('cache-control'), /immutable/);
    const etag = style.headers.get('etag');
    assert.ok(etag);
    assert.equal((await visit(cssPath, { 'If-None-Match': etag })).status, 304);

    const archive = await (await visit('/blog/')).text();
    assert.doesNotMatch(archive, /\/woocommerce\/assets\/js\/frontend\/(?:add-to-cart|woocommerce)(?:\.min)?\.js/);
    assert.doesNotMatch(archive, /\/wp-includes\/css\/dist\/block-library\/style/);
    const home = await (await visit('/')).text();
    assert.match(home, /"@type":"WebSite"/);
    assert.match(home, /<meta property="og:image" content="[^"]+\.webp"/);
    const catalog = await (await visit('/shop/?filter_pa_color=blue')).text();
    assert.match(catalog, /<meta name=['"]robots['"] content=['"][^'"]*noindex/);
    console.log('Verified public page cache and gzip, private checkout and sessions, static revalidation, and rendered SEO.');
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
