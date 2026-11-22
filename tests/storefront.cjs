const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const { existsSync, mkdirSync, readFileSync, statSync } = require('node:fs');
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

async function fillBilling(page, phone, email) {
    for (const [id, value] of Object.entries({
        billing_first_name: 'آزمون', billing_last_name: 'محلی', billing_city: 'تهران',
        billing_address_1: 'نشانی آزمایشی', billing_postcode: '1234567890',
        billing_phone: phone, billing_email: email,
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
}

async function loadSmsFixture(page, path) {
    const fixture = JSON.parse(readFileSync(process.env.MV_QA_SMS_FIXTURE, 'utf8').replace(/^\uFEFF/, ''));
    await page.setExtraHTTPHeaders({ 'X-MV-QA-Mode': fixture.tag });
    const response = await go(page, path);
    assert.equal(response.headers()['x-mv-qa-mode'], 'mocked', 'OneSMS fixture must be active before any submit');
    return fixture;
}

const cases = [
    ['01', 'صفحهٔ اول، زبان فارسی و فایل‌های قالب', async page => {
        await go(page, '/');
        assert.match(await page.title(), /معلم ورزش/);
        assert.deepEqual(await page.locator('html').evaluate(e => [e.lang, e.dir]), ['fa-IR', 'rtl']);
        assert.equal((await page.locator('h1').innerText()).replace(/\s+/g, ' '), 'لباس و تجهیزات ورزشی');
        const active = page.locator('.hero-slide.is-active img');
        await active.evaluate(img => img.decode());
        const hero = new URL(await active.evaluate(img => img.currentSrc));
        assert.equal(hero.origin, base);
        assert.ok(hero.pathname.endsWith('/hero-jersey-780.webp'));
        const asset = await page.request.get(hero.href);
        assert.ok((await asset.body()).length < 240000);
        await page.screenshot({ path: resolve(evidence, 'storefront-desktop.png'), fullPage: true });
    }],
    ['02', 'چیدمان واکنش‌گرا و مسیر ورود به فروشگاه', async page => {
        for (const width of [320, 375, 414, 768, 1024, 1440, 1920]) {
            await page.setViewportSize({ width, height: 900 });
            await go(page, '/');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Overflow at width ' + width);
            assert.ok(await page.getByRole('button', { name: 'جست‌وجو', exact: true }).boundingBox().then(box => box.width >= 44));
            assert.ok(await page.getByRole('button', { name: 'تصویر بعدی' }).boundingBox().then(box => box.width >= 44 && box.height >= 44));
            assert.ok(await page.locator('.hero-controls').evaluate(controls => {
                const frame = controls.closest('.hero').getBoundingClientRect();
                return [...controls.querySelectorAll('button')].every(button => {
                    const box = button.getBoundingClientRect();
                    return box.width >= 44 && box.height >= 44 && box.left >= frame.left && box.right <= frame.right;
                });
            }), 'Slider controls fit at width ' + width);
            assert.ok(await page.locator('.hero-controls').evaluate(controls => {
                const active = controls.getBoundingClientRect().height;
                controls.hidden = true;
                const hidden = controls.getBoundingClientRect().height;
                controls.hidden = false;
                return active === hidden;
            }), 'Slider reserves the control space before JavaScript starts');
            if (width === 375) {
                const mobileHero = await page.locator('.hero-slide.is-active img').evaluate(async img => {
                    await img.decode();
                    return img.currentSrc;
                });
                assert.ok(mobileHero.endsWith('/hero-jersey-420.webp'));
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
        await fillBilling(page, '09120000000', 'checkout-test@example.test');
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
    ['11', 'شش تصویر اسلایدر، پیمایش دستی و گردش خودکار', async page => {
        await page.clock.install();
        await go(page, '/');
        await page.getByRole('button', { name: 'توقف حرکت خودکار' }).waitFor();
        assert.equal(await page.locator('.hero-slide').count(), 6);
        assert.equal(await page.locator('.hero-dot').count(), 6);
        assert.equal(await page.locator('.hero-slide img[src]').count(), 1);
        await page.clock.runFor(7100);
        await page.locator('.hero-slide-boots.is-active').waitFor();
        await page.getByRole('button', { name: 'توقف حرکت خودکار' }).click();
        await page.locator('h1').click();
        await page.clock.runFor(8000);
        assert.equal(await page.locator('.hero-slide-boots.is-active').count(), 1);
        await page.getByRole('button', { name: 'توپ فوتبال', exact: true }).click();
        await page.locator('.hero-slide-ball.is-active').waitFor();
        assert.equal(await page.getByRole('button', { name: 'توپ فوتبال', exact: true }).getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator('[data-hero-announcement]').textContent(), 'توپ فوتبال');
        const next = page.getByRole('button', { name: 'تصویر بعدی' });
        const addedSlides = [['gloves', 'دستکش دروازه‌بانی'], ['guards', 'ساق‌بند فوتبال'], ['socks', 'جوراب ورزشی']];
        for (const [key, label] of addedSlides) {
            await next.click();
            const image = page.locator('.hero-slide-' + key + '.is-active img');
            const desktop = await image.evaluate(async img => {
                await img.decode();
                return { src: img.currentSrc, width: img.naturalWidth };
            });
            assert.ok(desktop.src.endsWith('/hero-' + key + '-780.webp'));
            assert.equal(desktop.width, 780);
            assert.equal(await page.locator('.hero-dot[aria-pressed="true"]').getAttribute('aria-label'), label);
            const asset = await page.request.get(desktop.src);
            assert.ok(asset.ok());
            assert.ok((await asset.body()).length < 200000);
            await page.locator('.hero').screenshot({ path: resolve(evidence, 'hero-' + key + '-desktop.png'), animations: 'disabled' });
        }
        await page.getByRole('button', { name: 'شروع حرکت خودکار' }).click();
        await page.clock.runFor(8000);
        await page.locator('.hero-slide-jersey.is-active').waitFor();
        await page.getByRole('button', { name: 'تصویر قبلی' }).click();
        await page.locator('.hero-slide-socks.is-active').waitFor();
        await next.click();
        await page.locator('.hero-slide-jersey.is-active').waitFor();
        await page.setViewportSize({ width: 390, height: 900 });
        for (const [key, label] of addedSlides) {
            await page.getByRole('button', { name: label, exact: true }).click();
            const image = page.locator('.hero-slide-' + key + '.is-active img');
            const mobile = await image.evaluate(async img => {
                await img.decode();
                return { src: img.currentSrc, width: img.naturalWidth };
            });
            assert.ok(mobile.src.endsWith('/hero-' + key + '-420.webp'));
            assert.equal(mobile.width, 420);
        }
        await page.locator('.hero').screenshot({ path: resolve(evidence, 'hero-socks-mobile.png'), animations: 'disabled' });
    }],
    ['12', 'کاهش حرکت براساس تنظیمات دستگاه و کنترل با صفحه‌کلید', async page => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.clock.install();
        await go(page, '/');
        await page.getByRole('button', { name: 'شروع حرکت خودکار' }).waitFor();
        await page.clock.runFor(8000);
        assert.equal(await page.locator('.hero-slide-jersey.is-active').count(), 1);
        const button = page.getByRole('button', { name: 'تصویر بعدی' });
        await button.focus();
        await button.press('Enter');
        await page.locator('.hero-slide-boots.is-active').waitFor();
        assert.equal(await page.locator('.hero-slide-boots').evaluate(slide => getComputedStyle(slide).transitionDuration), '0s');
    }],
    ['13', 'فرم ورود با موبایل، مسیر درخواست کد و رد شناسهٔ نامعتبر فرم', async page => {
        await go(page, '/my-account/');
        const form = page.locator('.mv-account form');
        assert.deepEqual(await form.locator('input:not([type=hidden])').evaluateAll(nodes => nodes.map(node => node.type)), ['tel', 'password']);
        await page.getByRole('link', { name: /ورود یا عضویت/ }).click();
        await page.waitForURL(/mode=otp/);
        assert.deepEqual(await form.locator('input:not([type=hidden])').evaluateAll(nodes => nodes.map(node => node.type)), ['tel']);
        await form.locator('input[name="_mv_nonce"]').evaluate(field => { field.value = 'invalid'; });
        await page.locator('#mv-phone').fill('09120000000');
        await page.getByRole('button', { name: 'ارسال کد ورود' }).click();
        await page.getByText('فرم منقضی شده است.', { exact: false }).waitFor();
        assert.equal(await page.locator('#mv-code').count(), 0);
        await page.setViewportSize({ width: 414, height: 850 });
        await go(page, '/my-account/');
        await page.screenshot({ path: resolve(evidence, 'storefront-account.png') });
    }],
    ['14', 'تلاش دوباره بعد از خطای پیامک، بازیابی کد فعال و ورود با رمز عبور', async page => {
        await page.clock.install();
        const fixture = await loadSmsFixture(page, '/my-account/?mode=otp');
        await page.locator('#mv-phone').fill(fixture.phone);
        const send = page.getByRole('button', { name: 'ارسال کد ورود' });
        const [failed] = await Promise.all([
            page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/my-account/'),
            send.click(),
        ]);
        assert.equal(failed.status(), 303);
        await page.getByText('ارسال پیامک تأیید نشد.', { exact: false }).waitFor();
        assert.equal(await page.locator('#mv-phone').inputValue(), fixture.phone);
        assert.equal(await page.locator('#mv-code').count(), 0);
        assert.ok(!existsSync(fixture.capture));
        await send.click();
        await page.waitForURL(/mode=verify/);
        assert.ok(existsSync(fixture.capture));
        const code = readFileSync(fixture.capture, 'utf8').trim();
        assert.match(code, /^[0-9]{6}$/);
        assert.equal(await page.locator('#mv-phone').inputValue(), fixture.phone);
        const timer = page.getByRole('timer');
        const timing = page.locator('[data-mv-otp-timing]');
        const originalRemaining = Number(await timing.getAttribute('data-valid-for'));
        assert.ok(originalRemaining > 0 && originalRemaining <= 300);
        const startingCount = await timer.innerText();
        assert.match(startingCount, /^[۰-۹]{2}:[۰-۹]{2}$/);
        await page.clock.fastForward(2000);
        assert.notEqual(await timer.innerText(), startingCount);
        const sentAt = statSync(fixture.capture).mtimeMs;
        await page.goBack({ waitUntil: 'domcontentloaded' });
        await page.waitForURL(/mode=otp/);
        const persianPhone = fixture.phone.replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[digit]);
        await page.locator('#mv-phone').fill(persianPhone);
        await page.getByRole('button', { name: 'ارسال کد ورود' }).click();
        await page.waitForURL(/mode=verify/, { timeout: 7000 });
        assert.equal(statSync(fixture.capture).mtimeMs, sentAt);
        assert.equal(readFileSync(fixture.capture, 'utf8').trim(), code);
        const resumedRemaining = Number(await timing.getAttribute('data-valid-for'));
        assert.ok(resumedRemaining > 0 && resumedRemaining <= originalRemaining);
        assert.ok(await page.locator('#mv-code').isVisible());
        assert.ok(await timer.isVisible());
        const resend = page.getByRole('button', { name: /ارسال دوبارهٔ کد/ });
        assert.ok(await resend.isDisabled());
        await page.setViewportSize({ width: 390, height: 1000 });
        await page.screenshot({ path: resolve(evidence, 'account-otp-resumed.png') });
        await page.clock.fastForward(61000);
        assert.ok(await resend.isEnabled());
        assert.ok(await page.getByRole('button', { name: 'تأیید و ورود' }).isEnabled());
        await page.clock.fastForward(300000);
        assert.ok(await page.getByText('کد قبلی دیگر معتبر نیست.', { exact: false }).isVisible());
        assert.ok(await page.getByRole('button', { name: 'تأیید و ورود' }).isDisabled());
        await page.reload({ waitUntil: 'domcontentloaded' });
        assert.ok(await timer.isVisible());
        assert.ok(await page.getByRole('button', { name: 'تأیید و ورود' }).isEnabled());
        const wrong = code === '123456' ? '123455' : '123456';
        await page.locator('#mv-code').fill(wrong);
        await page.getByRole('button', { name: 'تأیید و ورود' }).click();
        await page.getByText('کد ورود درست نیست', { exact: false }).waitFor();
        await page.locator('#mv-code').fill(code);
        const password = 'Test only password Q4757!';
        await page.locator('#mv-password').fill(password);
        await page.getByRole('button', { name: 'تأیید و ورود' }).click();
        await page.waitForURL('**/my-account/');
        assert.ok(await page.locator('.woocommerce-MyAccount-navigation').isVisible());
        await page.context().clearCookies();
        await putSimpleInCart(page);
        await page.locator('a.checkout-button').click();
        await page.waitForURL('**/checkout/');
        await page.getByRole('link', { name: 'ورود با شماره موبایل', exact: true }).click();
        await page.locator('#mv-phone').fill(fixture.phone);
        await page.locator('#mv-password').fill(password);
        await page.getByRole('button', { name: 'ورود', exact: true }).click();
        await page.waitForURL('**/checkout/');
        assert.ok(await page.locator('.woocommerce-checkout-review-order-table').innerText().then(text => text.includes(socks.name)));
        assert.equal(await page.locator('.mv-checkout-login').count(), 0);
    }],
    ['15', 'رسانه‌های شفاف در کارت‌ها و نگاره‌های صفحهٔ کالا', async page => {
        await go(page, '/');
        const thumbnails = await page.locator('section[aria-labelledby="new-title"] ul.products .woocommerce-LoopProduct-link img')
            .evaluateAll(imgs => imgs.map(img => new URL(img.src).pathname));
        assert.equal(thumbnails.length, 8);
        assert.ok(thumbnails.every(path => /\/mv-cutouts\/v1\/[a-f0-9]{24}-440x440\.webp$/.test(path)));
        const galleryProduct = source.find(product => product.images.length >= 4);
        await go(page, pathOf(galleryProduct));
        const gallery = page.locator('.woocommerce-product-gallery__wrapper a');
        assert.ok(await gallery.count() >= 4);
        const paths = await gallery.evaluateAll(items => items.map(item => new URL(item.href).pathname));
        assert.ok(paths.every(path => /\/mv-cutouts\/v1\/[a-f0-9]{24}\.webp$/.test(path)));
        const image = gallery.first().locator('img');
        await image.evaluate(async img => { img.loading = 'eager'; await img.decode(); });
        const response = await page.request.get(base + paths[0]);
        assert.equal(response.status(), 200);
        assert.match(response.headers()['content-type'], /^image\/webp/);
    }],
    ['16', 'ثبت خرید آزمایشی و پیامک همان سفارش', async page => {
        const fixture = await loadSmsFixture(page, '/shop/?mv-qa=1');
        assert.ok(fixture.orderCapture && fixture.email.endsWith('@example.test'));
        await putSimpleInCart(page);
        await page.locator('a.checkout-button').click();
        await page.waitForURL('**/checkout/');
        await fillBilling(page, fixture.phone, fixture.email);
        await page.locator('.woocommerce-checkout-payment').getByText('پرداخت آزمایشی', { exact: true }).waitFor();
        const terms = page.locator('input#terms');
        if (await terms.isVisible()) await terms.check();
        await page.locator('#place_order').click();
        await page.waitForURL(/order-received\/\d+/);
        assert.equal(await page.locator('main h1').innerText(), 'نتیجهٔ سفارش');
        assert.ok(await page.locator('.woocommerce-thankyou-order-received').isVisible());
        const orderId = new URL(page.url()).pathname.match(/order-received\/(\d+)/)[1];
        assert.ok(existsSync(fixture.orderCapture));
        const message = JSON.parse(readFileSync(fixture.orderCapture, 'utf8'));
        assert.deepEqual(message.to, [fixture.phone]);
        assert.match(message.body, /سفارش/);
        assert.ok(message.body.includes(orderId));
    }],
    ['17', 'جابه‌جایی تصویر با کشیدن روی صفحهٔ لمسی', async page => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.emulateMedia({ reducedMotion: 'reduce' });
        const input = await page.context().newCDPSession(page);
        await input.send('Emulation.setTouchEmulationEnabled', { enabled: true });
        await go(page, '/');
        const stage = await page.locator('.hero-stage').boundingBox();
        const x = Math.floor(stage.x + stage.width / 3);
        const y = Math.floor(stage.y + stage.height / 2);
        await input.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
        await input.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + 90, y }] });
        await input.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await page.locator('.hero-slide-boots.is-active').waitFor();
    }],
    ['18', 'دکمهٔ شناور شبکه‌های اجتماعی، پیوندها و کنترل صفحه‌کلید', async page => {
        const expected = [
            'https://www.instagram.com/moalemvarzesh_store/',
            'https://t.me/MoalemVarzesh_Store',
            'https://wa.me/message/EMDTPXP6JVVMC1',
            'https://eitaa.com/MoalemVarzesh_Store',
        ];
        for (const [width, path] of [[1536, '/'], [320, '/my-account/']]) {
            await page.setViewportSize({ width, height: 780 });
            await go(page, path);
            const social = page.locator('.mv-social');
            const toggle = social.locator('summary');
            const panel = social.getByRole('navigation', { name: 'شبکه‌های اجتماعی فروشگاه', includeHidden: true });
            assert.equal(await panel.isVisible(), false);
            const before = await toggle.boundingBox();
            assert.ok(before.width >= 44 && before.height >= 44 && before.x < 24);
            await page.evaluate(() => window.scrollTo(0, 180));
            assert.deepEqual(await toggle.boundingBox(), before);
            await toggle.press('Enter');
            assert.ok(await panel.isVisible());
            const region = await panel.boundingBox();
            assert.ok(region.x >= 0 && region.x + region.width <= width && region.y >= 0);
            const links = await panel.locator('a[target="_blank"]').evaluateAll(items => items.map(link => [link.href, link.target, link.rel]));
            assert.deepEqual(links, expected.map(href => [href, '_blank', 'noopener noreferrer']));
            const call = panel.getByRole('link', { name: /تماس با مدیر/ });
            const expectedPhone = process.env.MV_PUBLIC_CONTACT_PHONE;
            if (expectedPhone || await call.count()) {
                assert.equal(await call.count(), 1);
                const displayedPhone = await call.locator('.mv-social-number').textContent();
                assert.match(displayedPhone, /^09[0-9]{9}$/);
                if (expectedPhone) assert.equal(displayedPhone, expectedPhone);
                assert.equal(await call.getAttribute('href'), 'tel:+98' + displayedPhone.slice(1));
                assert.equal(await call.getAttribute('target'), null);
            }
            await panel.getByRole('link').first().press('Escape');
            assert.equal(await panel.isVisible(), false);
            assert.ok(await toggle.evaluate(el => document.activeElement === el));
            await toggle.click();
            await page.getByRole('heading', { level: 1 }).click();
            assert.equal(await panel.isVisible(), false);
            if (width === 320) {
                await toggle.click();
                await page.screenshot({ path: resolve(evidence, 'storefront-social.png') });
            }
        }
    }],
    ['19', 'فهرست مقاله‌ها، موضوعات، نمایش خوانا و پیوند دوطرفه با کالا', async page => {
        await go(page, '/blog/');
        assert.equal(await page.locator('.guide-card').count(), 12);
        assert.equal(await page.locator('.guide-topics a').count(), 11);
        await page.locator('.guide-feature img').evaluate(img => img.decode());
        assert.ok(await page.locator('.guide-card-image').evaluateAll(cards => cards.every(card => {
            const photo = card.querySelector('img.mv-guide-photograph');
            return photo && getComputedStyle(card).aspectRatio === '3 / 2' && photo.srcset.includes('480w');
        })), 'Editorial card photos preserve the original framing and have a compact responsive size');
        await page.screenshot({ path: resolve(evidence, 'guides-desktop.png') });
        const article = await page.locator('.guide-feature h2 a').getAttribute('href');
        await go(page, '/blog/page/2/');
        assert.ok((await page.locator('link[rel=canonical]').getAttribute('href')).endsWith('/blog/page/2/'));
        assert.ok(!(await page.locator('.guide-card h2 a').evaluateAll(nodes => nodes.map(node => node.href))).includes(article));
        await go(page, '/category/guide-futsal/');
        assert.equal(await page.locator('.guide-card').count(), 10);
        assert.equal(await page.locator('.guide-topics [aria-current=page]').innerText(), 'کفش فوتسال');
        const path = new URL(await page.locator('.guide-card h2 a').first().getAttribute('href')).pathname;
        for (const width of [360, 768, 1536]) {
            await page.setViewportSize({ width, height: 920 });
            await go(page, path);
            assert.equal(await page.locator('h1').count(), 1);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            assert.ok(await page.locator('.guide-hero-image img').evaluate(async img => { await img.decode(); return img.naturalWidth && img.alt; }));
            assert.ok((await page.locator('script[type="application/ld+json"]').allTextContents()).some(data => /"BlogPosting"/.test(data)));
            assert.ok(await page.locator('.guide-product-links a').count() >= 2);
            if (width === 360) await page.screenshot({ path: resolve(evidence, 'guide-mobile.png') });
            const first = page.getByRole('navigation', { name: 'فهرست مقاله' }).locator('a').first();
            await first.click();
            assert.equal(new URL(page.url()).hash, '#step-1');
        }
        const product = await page.locator('.guide-product-links a').first().getAttribute('href');
        await go(page, new URL(product, base).pathname);
        assert.ok(await page.locator('.mv-related-guides a').count() >= 1);
        assert.ok((await page.locator('meta[name=description]').getAttribute('content')).length > 25);
        await go(page, '/school-sports-equipment-bulk-order/');
        assert.ok(await page.locator('.guide-header').evaluate(header => {
            const frame = header.getBoundingClientRect();
            return [...header.children].every(child => {
                const box = child.getBoundingClientRect();
                return box.left >= frame.left && box.right <= frame.right && box.bottom <= frame.bottom;
            });
        }), 'A long headline and its photo fit within the same article header');
    }],
    ['20', 'چیدمان خرید و ورود، گام‌های خرید و کنترل تعداد و رمز عبور', async page => {
        const fits = async () => assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await go(page, '/cart/');
        assert.ok(await page.getByRole('heading', { name: 'سبد خریدتان خالی است' }).isVisible());
        assert.ok(await page.getByRole('link', { name: 'رفتن به فروشگاه', exact: true }).isVisible());
        await putSimpleInCart(page);
        const steps = page.getByRole('navigation', { name: 'مراحل خرید' });
        assert.equal(await steps.locator('[aria-current=step]').innerText().then(x => x.replace(/^۱\s*/, '')), 'سبد خرید');
        const quantity = page.locator('.cart_item input.qty');
        await page.getByRole('button', { name: /افزایش تعداد/ }).click();
        assert.equal(await quantity.inputValue(), '2');
        await page.locator('button[name=update_cart]').click();
        await page.locator('.woocommerce-message').waitFor();
        await page.getByRole('button', { name: /کاهش تعداد/ }).click();
        assert.equal(await quantity.inputValue(), '1');
        await page.locator('button[name=update_cart]').click();
        await page.waitForFunction(() => document.querySelector('button[name=update_cart]').disabled);
        for (const width of [320, 768, 1440]) {
            await page.setViewportSize({ width, height: width === 1440 ? 1120 : 1000 });
            await go(page, '/cart/');
            await fits();
            assert.ok(await page.locator('.cart_item .product-thumbnail img').isVisible());
            assert.ok(await page.locator('.cart_totals a.checkout-button').isVisible());
            if (width !== 768) await page.screenshot({ path: resolve(evidence, 'purchase-cart-' + width + '.png') });
            await page.locator('a.checkout-button').click();
            await page.waitForURL('**/checkout/');
            await fits();
            assert.equal(await steps.locator('[aria-current=step]').innerText().then(x => x.replace(/^۲\s*/, '')), 'تسویه‌حساب');
            assert.ok(await page.locator('#customer_details').isVisible());
            assert.ok(await page.locator('.mv-checkout-review .mv-review-item-image img').isVisible());
            assert.ok(await page.locator('#place_order').isVisible());
            await page.locator('.mv-checkout-review .blockUI').first().waitFor({ state: 'hidden' });
            assert.equal(await page.locator('.mv-checkout-review .order-total .amount').evaluate(el => getComputedStyle(el).whiteSpace), 'nowrap');
            const privacy = page.locator('.woocommerce-privacy-policy-text');
            if (await privacy.count()) {
                assert.match(await privacy.innerText(), /^[آ-ی]/);
                assert.ok(await privacy.locator('a').isVisible());
            }
            if (width !== 768) await page.screenshot({ path: resolve(evidence, 'purchase-checkout-' + width + '.png') });
            if (width === 320) {
                const coupon = page.getByRole('button', { name: 'وارد کردن کد تخفیف' });
                await coupon.click();
                await page.locator('.woocommerce-form-coupon #coupon_code').waitFor();
                await page.locator('.showcoupon[aria-expanded="true"]').waitFor();
            }
            await page.getByRole('link', { name: 'ورود با شماره موبایل', exact: true }).click();
            await page.waitForURL('**/my-account/?return=checkout');
            await fits();
            assert.equal(await page.locator('main h1').count(), 1);
            assert.equal(await page.getByRole('link', { name: 'بازگشت به تسویه‌حساب' }).getAttribute('href'), base + '/checkout/');
            const password = page.locator('#mv-password');
            await password.fill('Sample password');
            const toggle = page.locator('.mv-password-field .show-password-input');
            assert.equal(await toggle.count(), 1);
            const label = await toggle.getAttribute('aria-label');
            assert.ok(label);
            await toggle.press('Enter');
            assert.equal(await password.getAttribute('type'), 'text');
            assert.notEqual(await toggle.getAttribute('aria-label'), label);
            await toggle.click();
            assert.equal(await password.getAttribute('type'), 'password');
            assert.equal(await password.inputValue(), 'Sample password');
            await password.clear();
            if (width === 1440) await page.locator('.mv-account-visual img').evaluate(img => img.decode());
            if (width !== 768) await page.screenshot({ path: resolve(evidence, 'purchase-login-' + width + '.png') });
        }
        await page.getByRole('link', { name: /ورود یا عضویت/ }).click();
        await page.waitForURL(/mode=otp/);
        assert.equal(new URL(page.url()).searchParams.get('return'), 'checkout');
        assert.equal(await page.locator('.mv-account-method [aria-current=page]').innerText(), 'رمز یک‌بارمصرف');
    }],
    ['21', 'منوی دسته‌ها، انتخاب کفش، راهنماها و کالاهای منتخب هفته', async page => {
        await go(page, '/');
        const menu = page.locator('#mv-catalog-menu');
        const trigger = page.getByLabel('دسته‌بندی‌های فروشگاه', { exact: true });
        const menuLinks = menu.locator('a');
        const shoeCards = page.locator('.mv-home-shoe');
        const guideCards = page.locator('.mv-home-guide');
        const weekly = page.locator('.mv-home-weekly li.product');
        const weeklyLinks = weekly.locator('.woocommerce-LoopProduct-link');
        const latestLinks = page.locator('section[aria-labelledby="new-title"] .woocommerce-LoopProduct-link');
        assert.deepEqual(await page.locator('#shoe-finder-title, #home-guides-title, #weekly-title').allTextContents(),
            ['کفش مناسب بازی‌تان را پیدا کنید', 'راهنمای خرید و مقالات ورزشی', 'محصولات منتخب این هفته']);
        assert.equal(await shoeCards.count(), 3);
        assert.equal(await guideCards.count(), 3);
        assert.equal(await weekly.count(), 4);
        assert.equal(await page.locator('.mv-home-weekly li.product.instock').count(), 4);
        const picks = await weeklyLinks.evaluateAll(links => links.map(link => link.href));
        assert.equal(new Set(picks).size, 4);
        const recent = await latestLinks.evaluateAll(links => links.map(link => link.href));
        assert.ok(picks.every(href => !recent.includes(href)));
        const shoes = await shoeCards.evaluateAll(cards => cards.map(card => card.href));
        const guides = await guideCards.locator('a').evaluateAll(links => links.map(link => link.href));
        assert.deepEqual(await shoeCards.locator('h3').allTextContents(), ['سالن و فوتسال', 'چمن مصنوعی', 'چمن طبیعی']);
        assert.equal(new Set(guides).size, 3);
        for (const image of await page.locator('.mv-home-shoe img, .mv-home-guide img').all()) {
            assert.ok(await image.evaluate(async el => { el.loading = 'eager'; await el.decode(); return el.naturalWidth; }));
        }
        assert.equal(await page.locator('.mv-home-shoe img, .mv-home-guide img').count(), 6);
        for (const width of [320, 390, 768, 1536]) {
            await page.setViewportSize({ width, height: 920 });
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            assert.equal(await menu.isVisible(), false);
            if (width === 390) await trigger.click();
            else await trigger.press('Enter');
            assert.ok(await menu.isVisible());
            assert.equal(await menuLinks.count(), 17);
            assert.deepEqual((await menu.locator('.mv-catalog-heading').allTextContents()).map(text => text.trim()),
                ['پوشاک و فوتبال', 'کفش ورزشی', 'توپ‌های ورزشی', 'لوازم جانبی']);
            const box = await menu.boundingBox();
            assert.ok(box.x >= 0 && box.x + box.width <= width && box.y + box.height <= 920);
            assert.ok(await menu.evaluate(panel => panel.scrollWidth <= panel.clientWidth));
            if ([390, 1536].includes(width)) {
                await page.mouse.move(0, 0);
                await menu.screenshot({ path: resolve(evidence, 'shop-menu-' + width + '.png') });
            }
            await menuLinks.first().press('Escape');
            assert.equal(await menu.isVisible(), false);
            assert.ok(await trigger.evaluate(el => el === document.activeElement));
        }
        await page.locator('.mv-home-shoes').screenshot({ path: resolve(evidence, 'home-shoe-finder.png') });
        await page.locator('.mv-home-guides').screenshot({ path: resolve(evidence, 'home-buying-guides.png') });
        await page.locator('.mv-home-weekly').screenshot({ path: resolve(evidence, 'home-weekly-picks.png') });
        await trigger.click();
        await page.locator('#site-search').click();
        assert.equal(await menu.isVisible(), false);
        const links = await menuLinks.evaluateAll(anchors => anchors.map(anchor => anchor.href));
        for (const href of new Set([...links, ...shoes, ...guides, ...picks])) {
            assert.equal(new URL(href).origin, base);
            assert.equal((await page.request.get(href, { maxRedirects: 0 })).status(), 200, href);
        }
        await page.reload({ waitUntil: 'domcontentloaded' });
        assert.deepEqual(await weeklyLinks.evaluateAll(links => links.map(link => link.href)), picks);
        await trigger.click();
        await menu.getByRole('link', { name: 'توپ‌های ورزشی', exact: true }).click();
        assert.equal(await page.locator('h1').innerText(), 'توپ‌های ورزشی');
        assert.equal(await page.locator('ul.products li.product').count(), 3);
    }],
    ['22', 'فوتر، مقصد پیوندها، تصاویر و فهرست صفحات راهنما', async page => {
        const paths = ['/about-us/', '/contact-us/', '/terms-and-conditions/', '/refund_returns-2/',
            '/' + encodeURIComponent('حریم-خصوصی-معلم-ورزش') + '/',
            '/' + encodeURIComponent('سیاست-کوکی') + '/', '/blog/'];
        await go(page, '/');
        const menus = page.locator('.site-footer .mv-footer-group');
        assert.equal(await menus.count(), 3);
        const destinations = await menus.locator('a').evaluateAll(anchors => anchors.map(a => a.href));
        assert.equal(destinations.length, 10);
        assert.equal(new Set(destinations).size, 10);
        for (const href of [...paths, '/shop/', '/my-account/', '/cart/']) {
            assert.ok(destinations.some(url => decodePath(new URL(url).pathname) === decodePath(href)), href);
            const response = await page.request.get(base + href, { maxRedirects: 0 });
            assert.equal(response.status(), 200, href);
        }
        const footer = page.locator('.site-footer');
        const help = footer.locator('[data-menu="mv_footer_help"]');
        const policies = footer.locator('[data-menu="mv_footer_policies"]');
        assert.deepEqual(await help.locator('.mv-footer-link-icon').evaluateAll(icons => icons.map(icon => icon.dataset.icon)),
            ['book', 'chat', 'info']);
        assert.deepEqual(await policies.locator('.mv-footer-link-icon').evaluateAll(icons => icons.map(icon => icon.dataset.icon)),
            ['document', 'return', 'shield', 'check']);
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 960 });
            await footer.scrollIntoViewIfNeeded();
            const [helpBox, policyBox] = await Promise.all([help.boundingBox(), policies.boundingBox()]);
            assert.ok(width < 768
                ? helpBox.y + helpBox.height < policyBox.y
                : Math.abs(helpBox.y - policyBox.y) < 1 && helpBox.x >= policyBox.x + policyBox.width, width);
            if (width >= 1024) {
                const brand = await footer.locator('.mv-footer-brand').boundingBox();
                assert.ok(brand.x >= helpBox.x + helpBox.width, width);
            }
            assert.ok(await menus.locator('a').evaluateAll(links => links.every(link => {
                const bounds = link.getBoundingClientRect();
                return bounds.width >= 48 && bounds.height >= 48 && bounds.left >= 0 && bounds.right <= innerWidth
                    && link.scrollWidth <= link.clientWidth;
            })), 'Touch targets and legible labels at ' + width);
            if ([390, 1440].includes(width)) {
                await footer.screenshot({ path: resolve(evidence, 'support-footer-' + (width === 390 ? 'mobile' : 'desktop') + '.png') });
            }
        }
        const guide = help.getByRole('link', { name: 'راهنمای خرید و مقالات ورزشی', exact: true });
        await guide.focus();
        assert.ok(await guide.evaluate(link => link.matches(':focus-visible') && getComputedStyle(link).outlineStyle !== 'none'));
        await page.keyboard.press('Enter');
        await page.waitForURL('**/blog/');
        assert.equal(await guide.getAttribute('aria-current'), 'page');
        const covers = new Set();
        for (const [index, path] of paths.entries()) {
            const mobile = index % 2 === 1;
            await page.setViewportSize({ width: mobile ? 390 : 1536, height: 950 });
            await go(page, path);
            assert.equal(await page.locator('main h1').count(), 1);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            const photo = page.locator('.mv-support-hero img');
            const image = await photo.evaluate(async img => { await img.decode(); return img.currentSrc; });
            assert.ok(image.endsWith(mobile ? '-640.webp' : '-1200.webp'), path);
            covers.add(image.replace(/-(?:640|1200)\.webp$/, ''));
            const body = page.locator('.mv-support-body');
            const outline = page.locator('.mv-support-outline a');
            if (await body.count()) {
                assert.ok(!(await body.locator('a[href]').evaluateAll(anchors => anchors.map(a => a.hostname))).includes('moalem-varzesh.ir'));
            }
            if (await outline.count()) {
                const first = outline.first();
                await first.click();
                const hash = new URL(page.url()).hash;
                assert.equal(hash, await first.getAttribute('href'));
                assert.ok(await page.evaluate(id => !!document.getElementById(id), decodeURIComponent(hash.slice(1))));
            }
            if (['/about-us/', '/contact-us/', '/refund_returns-2/', '/blog/'].includes(path)) {
                const name = path.replace(/\//g, '');
                await page.locator('.mv-support-hero').screenshot({ path: resolve(evidence, 'support-' + name + '.png') });
            }
        }
        assert.equal(covers.size, 7);
    }],
    ['23', 'مدیریت متن و تصویر صفحه، رنگ فوتر و منوی وردپرس', async page => {
        const fixture = JSON.parse(readFileSync(process.env.MV_SUPPORT_ADMIN_FIXTURE, 'utf8'));
        const guestAbout = () => fetch(base + '/about-us/', { headers: { Accept: 'text/html' } }).then(response => response.text());
        await guestAbout();
        await guestAbout();
        await page.goto(base + '/wp-login.php', { waitUntil: 'domcontentloaded' });
        await page.locator('#user_login').fill(fixture.user);
        await page.locator('#user_pass').fill(fixture.password);
        await page.locator('#wp-submit').click();
        await page.waitForURL(/\/wp-admin\//);
        const settingsPath = '/wp-admin/themes.php?page=mv-support-design';
        await page.goto(base + settingsPath, { waitUntil: 'domcontentloaded' });
        const admin = page.locator('.mv-support-admin');
        assert.equal(await admin.locator('[data-support-image]').count(), 7);
        assert.equal(await admin.locator('.mv-support-admin-menus a').count(), 3);
        await page.screenshot({ path: resolve(evidence, 'support-settings-desktop.png') });
        await admin.locator('input[name="mv_support_design[footer_intro]"]').fill('معرفی موقت برای آزمون فوتر');
        await admin.locator('input[name="mv_support_design[footer_accent]"]').fill('#594963');
        const prefix = 'mv_support_design[pages][about]';
        const intro = admin.locator('textarea[name="' + prefix + '[intro]"]');
        const cover = admin.locator('input[name="' + prefix + '[image]"]');
        const card = admin.locator('[data-support-image]').filter({ has: page.locator('textarea[name="' + prefix + '[intro]"]') });
        await intro.fill('معرفی موقت برای آزمون صفحه');
        await card.locator('input[type="color"]').fill('#6a4141');
        await card.locator('[data-pick-image]').click();
        const modal = page.locator('.media-modal');
        const image = modal.locator('.attachments-browser .attachment').first();
        await image.click();
        const attachment = await image.getAttribute('data-id');
        await modal.locator('.media-button-select').click();
        assert.equal(await cover.inputValue(), attachment);
        await admin.locator('input[type="submit"]').click();
        await page.waitForURL(/settings-updated=true/);
        await go(page, '/about-us/');
        assert.match(await page.locator('.site-footer').getAttribute('style'), /#594963/);
        assert.match(await page.locator('.mv-support-hero').getAttribute('style'), /#6a4141/);
        assert.equal(await page.locator('.mv-support-hero p').innerText(), 'معرفی موقت برای آزمون صفحه');
        assert.equal(await page.locator('.mv-footer-brand p').innerText(), 'معرفی موقت برای آزمون فوتر');
        assert.match(await guestAbout(), /معرفی موقت برای آزمون فوتر/);
        assert.ok((await page.locator('.mv-support-hero img').getAttribute('src')).includes('/uploads/'));
        await page.goto(base + settingsPath, { waitUntil: 'domcontentloaded' });
        assert.equal(await cover.inputValue(), attachment);
        await card.locator('[data-reset-image]').click();
        assert.equal(await cover.inputValue(), '0');
        const [reset] = await Promise.all([
            page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/wp-admin/options.php'),
            admin.locator('input[type="submit"]').click(),
        ]);
        assert.equal(reset.status(), 302);
        assert.match(await guestAbout(), /src="[^"]*\/assets\/support\/about-1200\.webp"/);
        await page.goto(base + settingsPath, { waitUntil: 'domcontentloaded' });
        assert.equal(await cover.inputValue(), '0');
        await admin.locator('.mv-support-admin-menus a').first().click();
        await page.waitForURL(/nav-menus.php\?menu=/);
        const menuItem = page.locator('#menu-item-' + fixture.item);
        await menuItem.locator('.item-edit').click();
        await menuItem.locator('.edit-menu-item-title').fill('پیوند موقت آزمون فوتر');
        await page.locator('#save_menu_footer').click();
        await go(page, '/about-us/');
        assert.ok(await page.locator('.site-footer').getByRole('link', { name: 'پیوند موقت آزمون فوتر' }).isVisible());
        assert.match(await guestAbout(), /پیوند موقت آزمون فوتر/);
        assert.match(await page.locator('.mv-support-hero img').getAttribute('src'), /\/assets\/support\/about-1200.webp$/);
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
            if (['14', '16'].includes(number) && !process.env.MV_QA_SMS_FIXTURE) continue;
            if (number === '23' && !process.env.MV_SUPPORT_ADMIN_FIXTURE) continue;
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
