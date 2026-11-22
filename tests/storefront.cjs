const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const { mkdirSync } = require('node:fs');
const { resolve } = require('node:path');
const source = require('../catalog/products.json');
const variants = require('../catalog/variations.json');
const base = new URL(process.env.MV_TEST_URL || 'http://127.0.0.1:8087').origin;
assert.ok(/^http:\/\/(localhost|127\.0\.0\.1)(:|$)/.test(base), 'Tests only run against a loopback storefront');

const evidence = resolve(process.env.MV_QA_OUTPUT || 'test-results');
mkdirSync(evidence, { recursive: true });
const pathOf = row => new URL(row.permalink).pathname;
const decodePath = pathname => decodeURIComponent(pathname).replace(/\/$/, '');
const sourceByPath = new Map(source.map(row => [decodePath(pathOf(row)), row]));
const clean = str => str.replace(/[۰-۹]/g, x => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(x))).replace(/\D/g, '');
const messi = source.find(p => p.id === 12556);
const socks = source.find(p => p.type === 'simple' && p.is_in_stock);
const soldOut = variants.find(p => !p.is_in_stock);
const soldOutParent = source.find(p => p.id === soldOut.parent);

async function go(page, path, status = 200) {
    const response = await page.goto(base + path, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), status, path);
    assert.ok(await page.locator('main').count(), path);
    return response;
}

async function putSimpleInCart(page) {
    await go(page, pathOf(socks));
    const form = page.locator('form.cart');
    const quantity = form.locator('input.qty');
    if (await quantity.isVisible()) await quantity.fill('1');
    await form.locator('button.single_add_to_cart_button').click();
    await page.locator('.woocommerce-message').waitFor({ state: 'visible' });
    await go(page, '/cart/');
    assert.ok((await page.locator('.cart_item .product-name').innerText()).includes(socks.name));
}

const cases = [
    ['01', 'صفحهٔ اول، زبان فارسی و فایل‌های قالب', async page => {
        await go(page, '/');
        assert.match(await page.title(), /معلم ورزش/);
        assert.deepEqual(await page.locator('html').evaluate(e => [e.lang, e.dir]), ['fa-IR', 'rtl']);
        assert.equal((await page.locator('h1').innerText()).replace(/\s+/g, ' '), 'لباس و تجهیزات ورزشی');
        await page.locator('.hero img').evaluate(img => img.decode());
        const hero = new URL(await page.locator('.hero img').evaluate(img => img.currentSrc));
        assert.equal(hero.origin, base);
        assert.ok(hero.pathname.endsWith('/hero.webp'));
        const asset = await page.request.get(hero.href);
        assert.ok((await asset.body()).length < 240000);
        await page.screenshot({ path: resolve(evidence, 'storefront-desktop.png'), fullPage: true });
    }],
    ['02', 'چیدمان واکنش‌گرا و مسیر ورود به فروشگاه', async page => {
        for (const width of [375, 414, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await go(page, '/');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Overflow at width ' + width);
            assert.ok(await page.getByRole('button', { name: 'جست‌وجو', exact: true }).boundingBox().then(box => box.width >= 44));
            if (width === 375) {
                const mobileHero = await page.locator('.hero img').evaluate(async img => {
                    await img.decode();
                    return img.currentSrc;
                });
                assert.ok(mobileHero.endsWith('/hero-mobile.webp'));
                await page.screenshot({ path: resolve(evidence, 'storefront-mobile.png'), fullPage: true });
            }
        }
        await page.getByRole('link', { name: 'مشاهده فروشگاه' }).click();
        await page.waitForURL('**/shop/');
        assert.ok(await page.locator('ul.products li.product').count() > 0);
    }],
    ['04', 'دسته‌بندی، مرتب‌سازی و صفحه‌بندی فروشگاه', async page => {
        await go(page, '/shop/');
        const first = await page.locator('ul.products li.product a.woocommerce-LoopProduct-link').first().getAttribute('href');
        const links = page.locator('.mv-shop-cats a');
        assert.ok(await links.count() >= 4);
        await links.first().click();
        await page.waitForURL('**/product-category/**');
        assert.ok(await page.locator('ul.products li.product').count() > 0);
        await go(page, '/shop/');
        await page.locator('select.orderby').selectOption('price');
        await page.waitForURL(/orderby=price/);
        const sortedLinks = await page.locator('ul.products li.product a.woocommerce-LoopProduct-link')
            .evaluateAll(links => links.map(link => new URL(link.href).pathname));
        assert.equal(sortedLinks.length, 16);
        const prices = sortedLinks.map(path => Number(sourceByPath.get(decodePath(path)).prices.price));
        assert.ok(prices.every((price, index) => !index || price >= prices[index - 1]), 'Prices should be sorted ascending');
        await go(page, '/shop/page/2/');
        assert.notEqual(await page.locator('ul.products li.product a.woocommerce-LoopProduct-link').first().getAttribute('href'), first);
        assert.ok(await page.locator('link[rel=canonical]').getAttribute('href').then(x => x.endsWith('/shop/page/2/')));
    }],
    ['05', 'جست‌وجوی فارسی و پیام نتیجهٔ خالی', async page => {
        await go(page, '/');
        const input = page.getByRole('searchbox', { name: 'جست‌وجوی کالا' });
        await input.fill('مسی');
        await page.getByRole('button', { name: 'جست‌وجو', exact: true }).click();
        await page.waitForURL(url => url.searchParams.get('s') === 'مسی');
        const result = page.locator('ul.products a.woocommerce-LoopProduct-link').filter({ hasText: messi.name });
        await result.waitFor({ state: 'visible' });
        assert.ok(await page.locator('main').evaluate(element => element.clientWidth > window.innerWidth * 0.9));
        const layout = await page.locator('main').evaluate(element => {
            const header = document.querySelector('.site-header');
            return element.getBoundingClientRect().top >= header.getBoundingClientRect().bottom;
        });
        assert.ok(layout, 'Search results should appear below the header');
        await page.getByRole('searchbox', { name: 'جست‌وجوی کالا' }).fill('هیچکالا۰۰۰ZZ');
        await page.getByRole('button', { name: 'جست‌وجو', exact: true }).click();
        await page.waitForURL(url => url.searchParams.get('s') === 'هیچکالا۰۰۰ZZ');
        await page.locator('main .woocommerce-info').waitFor({ state: 'visible' });
        assert.equal(await page.locator('ul.products li.product').count(), 0);
    }],
    ['06', 'گالری و قیمت گزینهٔ سفارشی محصول متغیر', async page => {
        await go(page, pathOf(messi));
        assert.equal(await page.locator('h1').innerText(), messi.name);
        await page.locator('.woocommerce-product-gallery__image img').first().evaluate(img => img.decode());
        const image = await page.locator('.woocommerce-product-gallery__image img').first().getAttribute('src');
        assert.equal(new URL(image).origin, base);
        const choice = page.locator('form.variations_form .variations select').first();
        await choice.selectOption('6 (2XL) با نام و شماره دلخواه');
        const form = page.locator('form.variations_form');
        await form.locator('.woocommerce-variation-price .amount').waitFor({ state: 'visible' });
        assert.ok(clean(await form.locator('.woocommerce-variation-price .amount').first().innerText()).includes('1490000'));
        assert.notEqual(await form.locator('[name=variation_id]').inputValue(), '0');
        assert.ok(await form.locator('button.single_add_to_cart_button').isEnabled());
        await page.locator('#mv-print-name').fill('آزمون');
        await page.locator('#mv-print-number').fill('۱۰');
        await page.screenshot({ path: resolve(evidence, 'storefront-product.png') });
        await form.locator('button.single_add_to_cart_button').click();
        await page.locator('.woocommerce-message').waitFor({ state: 'visible' });
        await go(page, '/cart/');
        const details = await page.locator('tr.cart_item .product-name').innerText();
        assert.match(details, /آزمون/);
        assert.match(details, /10/);
    }],
    ['07', 'گزینهٔ ناموجود و ادارهٔ مسیر نامعتبر', async page => {
        await go(page, pathOf(soldOutParent));
        const form = page.locator('form.variations_form');
        const data = JSON.parse(await form.getAttribute('data-product_variations'));
        assert.ok(Array.isArray(data));
        const unavailable = data.find(v => v.is_in_stock === false);
        assert.ok(unavailable, 'Expected at least one out-of-stock option in the variation payload');
        for (const [key, value] of Object.entries(unavailable.attributes)) {
            if (value) await form.locator('select[name="' + key + '"]').selectOption(value);
        }
        const add = form.locator('button.single_add_to_cart_button');
        assert.ok(await add.evaluate(e => e.classList.contains('wc-variation-is-unavailable') || e.classList.contains('disabled') || e.disabled));
        await go(page, '/missing-for-storefront-qa/', 404);
        assert.match(await page.locator('h1').innerText(), /پیدا نشد/);
    }],
    ['08', 'افزودن، تغییر تعداد و حذف کالا از سبد خرید', async page => {
        await putSimpleInCart(page);
        const row = page.locator('tr.cart_item');
        const input = row.locator('input.qty');
        await input.fill('2');
        await page.locator('button[name=update_cart]').click();
        await page.locator('.woocommerce-message').waitFor({ state: 'visible' });
        assert.equal(await row.locator('input.qty').inputValue(), '2');
        await row.locator('a.remove').click();
        await page.locator('.cart-empty').waitFor({ state: 'visible' });
        assert.equal(await page.locator('tr.cart_item').count(), 0);
    }],
    ['09', 'صفحهٔ تسویه‌حساب و حساب کاربری', async page => {
        await putSimpleInCart(page);
        await page.locator('a.checkout-button').click();
        await page.waitForURL('**/checkout/');
        assert.ok(await page.locator('form.woocommerce-checkout').isVisible());
        assert.ok(await page.locator('#billing_email').isVisible());
        assert.ok(await page.locator('#billing_first_name').isVisible());
        assert.ok(await page.locator('.woocommerce-checkout-review-order-table').innerText().then(text => text.includes(socks.name)));
        for (const [id, value] of Object.entries({
            billing_first_name: 'آزمون', billing_last_name: 'محلی', billing_city: 'تهران',
            billing_address_1: 'نشانی آزمایشی', billing_postcode: '1234567890',
            billing_phone: '09120000000', billing_email: 'checkout-test@example.test',
        })) {
            const field = page.locator('#' + id);
            if (await field.isVisible()) await field.fill(value);
        }
        const state = page.locator('#billing_state');
        if (await state.count()) {
            await state.evaluate(el => {
                el.value = 'THR';
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
        await page.locator('.woocommerce-checkout-payment').getByText('پرداخت آزمایشی', { exact: true }).waitFor({ state: 'visible' });
        assert.ok(await page.locator('#place_order').isVisible());
        await go(page, '/my-account/');
        assert.ok(await page.locator('form.woocommerce-form-login').isVisible());
    }],
    ['10', 'سئوی صفحهٔ کالا، پیوند اینماد و صفحات راهنما', async page => {
        await go(page, pathOf(messi));
        assert.equal(await page.locator('h1').count(), 1);
        assert.equal(await page.locator('link[rel=canonical]').count(), 1);
        const canonical = await page.locator('link[rel=canonical]').getAttribute('href');
        assert.equal(decodeURIComponent(canonical.toLowerCase()), decodeURIComponent((base + pathOf(messi)).toLowerCase()));
        assert.ok((await page.locator('meta[name=description]').getAttribute('content')).length > 25);
        assert.match(await page.locator('meta[name=robots]').getAttribute('content'), /noindex/);
        assert.ok((await page.locator('script[type="application/ld+json"]').allTextContents()).some(text => /"Product"/.test(text)));
        const trust = page.getByRole('link', { name: 'استعلام اینماد دامنه moalem-varzesh.ir' });
        const href = new URL(await trust.getAttribute('href'));
        assert.equal(href.hostname, 'trustseal.enamad.ir');
        assert.equal(href.searchParams.get('id'), '647991');
        await trust.locator('img').evaluate(img => { img.loading = 'eager'; return img.decode(); });
        for (const path of ['/contact-us/', '/about-us/', '/terms-and-conditions/', '/refund_returns-2/']) {
            await go(page, path);
            assert.ok((await page.locator('main article').innerText()).trim().length > 30);
        }
    }],
];

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: process.env.MV_CHROME || undefined,
    });
    let failed = 0;
    try {
        const selected = process.env.MV_TEST_CASES?.split(',');
        for (const [number, name, test] of cases) {
            if (selected && !selected.includes(number)) continue;
            const context = await browser.newContext({ viewport: { width: 1536, height: 1024 }, locale: 'fa-IR' });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            page.on('response', response => {
                if (response.url().startsWith(base) && response.status() >= 500) errors.push(response.status() + ' ' + response.url());
            });
            try {
                await test(page);
                assert.deepEqual(errors, []);
                console.log('PASS ' + number + ' ' + name);
            } catch (error) {
                failed++;
                console.error('FAIL ' + number + ' ' + name + '\n' + error.stack);
                await page.screenshot({ path: resolve(evidence, 'failure-' + number + '.png') }).catch(() => undefined);
            } finally {
                await context.close();
            }
        }
    } finally {
        await browser.close();
    }
    process.exitCode = failed ? 1 : 0;
})().catch(error => { console.error(error); process.exitCode = 1; });
