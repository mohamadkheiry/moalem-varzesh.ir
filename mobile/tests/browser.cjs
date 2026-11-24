const assert = require('node:assert/strict');
const { mkdirSync } = require('node:fs');
const { resolve } = require('node:path');
const { chromium } = require('playwright');

const base = new URL(process.env.MV_MOBILE_TEST_URL || 'http://127.0.0.1:8091').origin;
const store = new URL(process.env.MV_STORE_TEST_URL || 'http://127.0.0.1:8087').origin;
for (const origin of [base, store]) assert.match(origin, /^http:\/\/(localhost|127\.0\.0\.1)(:|$)/);

const output = resolve(process.env.MV_MOBILE_QA_OUTPUT || 'test-results');
mkdirSync(output, { recursive: true });
const catalog = require('../../catalog/products.json');
const least = Math.min(...catalog.map(row => Number(row.prices.price_range?.min_amount ?? row.prices.price)));
const leastPrice = new Intl.NumberFormat('fa-IR').format(least);
const productTitle = 'لباس دوم مسی آرژانتین جام جهانی ۲۰۲۶';
const selectedOption = '6 (2XL) با نام و شماره دلخواه';
const check = name => console.log('PASS ' + name);
const digitText = value => value.replace(/[۰-۹]/g, c => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c)));

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.MV_CHROME });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'fa-IR', isMobile: true, hasTouch: true });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => {
    if ((response.url().startsWith(base) || response.url().startsWith(store)) && response.status() >= 500) {
      errors.push('Server error ' + response.status() + ' ' + new URL(response.url()).pathname);
    }
  });
  const by = id => page.getByTestId(id);
  const capture = async name => {
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: resolve(output, name + '.png'), animations: 'disabled' });
  };

  try {
    await page.goto(base, { waitUntil: 'domcontentloaded' });
    await by('home-screen').waitFor();
    await by(/^home-category-/).first().waitFor({ timeout: 45000 });
    assert.equal(await by(/^home-category-/).count(), 4);
    assert.equal(await by(/^tab-/).count(), 4);
    await capture('app-home');
    check('صفحهٔ اول و کاتالوگ واقعی');

    for (const width of [320, 390, 430, 768]) {
      await page.setViewportSize({ width, height: 844 });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      const box = await by('tab-cart').boundingBox();
      assert.ok(box.width >= 44 && box.height >= 44);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    check('چهار اندازهٔ نمایش و اندازهٔ هدف لمسی');

    await by('hero-shop').click();
    await by('shop-screen').waitFor();
    assert.match(digitText(await by('result-count').innerText()), /116/);
    await capture('app-shop');
    await by('sort-open').click();
    await by('sort-low').click();
    assert.ok((await by(/^product-card-/).first().innerText()).includes(leastPrice));
    check('مرتب‌سازی روی کل کاتالوگ');

    await by('search-input').fill('مسي');
    await page.getByRole('button', { name: productTitle, exact: true }).waitFor();
    await by('search-input').fill('عبارت-بی‌نتیجه-۲۱۸۸');
    await page.getByText('کالایی پیدا نشد').waitFor();
    await by('search-input').fill('');
    check('جست‌وجوی فارسی، حروف عربی و حالت بدون نتیجه');

    await by('tab-home').click();
    await by(/^home-category-/).first().click();
    await by('shop-screen').waitFor();
    assert.ok(Number(digitText(await by('result-count').innerText()).match(/\d+/)[0]) < 116);
    await by('category-all').click();
    assert.match(digitText(await by('result-count').innerText()), /116/);
    check('فیلتر دسته و برگشت به همهٔ کالاها');

    await by('tab-home').click();
    const favorite = by(/^favorite-/).first();
    const favoriteId = await favorite.getAttribute('data-testid');
    await favorite.click();
    await by('tab-saved').click();
    await by(favoriteId).waitFor();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await by('tab-saved').click();
    await by(favoriteId).waitFor();
    check('ذخیرهٔ علاقه‌مندی بعد از بارگذاری مجدد');

    await by('tab-home').click();
    await page.getByRole('button', { name: productTitle, exact: true }).click();
    await by('product-screen').waitFor();
    assert.equal(await by('add-to-cart').getAttribute('aria-disabled'), 'true');
    await page.getByRole('button', { name: selectedOption, exact: true }).click();
    await by('print-name').waitFor();
    await page.waitForFunction(() => document.querySelector('[data-testid="add-to-cart"]')?.getAttribute('aria-disabled') !== 'true');
    assert.match(await by('detail-price').innerText(), /۱[٬,]۴۹۰[٬,]۰۰۰/);
    await capture('app-product');
    check('انتخاب گزینه و قیمت واقعی محصول متغیر');

    await by('add-to-cart').click();
    await by('product-error').waitFor();
    assert.match(await by('product-error').innerText(), /نام و شماره/);
    await by('print-name').fill('آزمون رابط');
    await by('print-number').fill('۱۰');
    await by('add-to-cart').click();
    await page.getByText('کالا به سبد خرید اضافه شد.').waitFor();
    await by('header-cart').click();
    await by('cart-item').getByText(/آزمون رابط/).waitFor();
    await page.waitForFunction(() => document.querySelector('[data-testid="review-open"]')?.getAttribute('aria-disabled') !== 'true');
    assert.equal(await page.getByText('کالا به سبد خرید اضافه شد.').count(), 0);
    await capture('app-cart');
    check('اعتبارسنجی و انتقال نام و شمارهٔ چاپ به سبد');

    await by('cart-quantity-plus').click();
    await page.waitForFunction(() => document.querySelector('[data-testid="cart-total"]')?.textContent?.includes('۲٬۹۸۰٬۰۰۰'));
    await by('cart-quantity-minus').click();
    await page.waitForFunction(() => document.querySelector('[data-testid="cart-total"]')?.textContent?.includes('۱٬۴۹۰٬۰۰۰'));
    check('افزایش و کاهش تعداد با جمع واقعی سرور');

    await by('review-open').click();
    await by('review-screen').waitFor();
    await capture('app-checkout');
    const cartTransfer = page.waitForRequest(request => {
      const url = new URL(request.url());
      return url.origin === store && url.pathname === '/checkout/' && url.searchParams.has('session');
    });
    await by('checkout-open').click();
    await cartTransfer;
    await page.waitForURL(url => url.origin === store && (url.pathname === '/checkout/'
      || (url.pathname === '/my-account/' && url.searchParams.get('return') === 'checkout')),
    { timeout: 45000, waitUntil: 'domcontentloaded' });
    if (new URL(page.url()).pathname === '/my-account/') {
      await page.locator('#mv-phone').waitFor();
      await page.getByRole('link', { name: 'ثبت‌نام', exact: true }).waitFor();
    } else {
      await page.locator('#billing_email').waitFor();
      assert.ok((await page.locator('.woocommerce-checkout-review-order-table').innerText()).includes('آزمون رابط'));
    }
    check('انتقال سبد و ادامهٔ مسیر تسویه‌حساب در همان فروشگاه');

    await page.goto(base, { waitUntil: 'domcontentloaded' });
    await by('tab-cart').click();
    await page.waitForFunction(() => document.querySelector('[data-testid="cart-item"]')
      || document.body.innerText.includes('سبد خرید خالی است'));
    const line = by('cart-item');
    if (await line.count()) {
      await by('cart-remove').click();
      await page.getByText('سبد خرید خالی است').waitFor();
    } else {
      await page.getByText('سبد خرید خالی است').waitFor();
    }
    check('بازگشت به اپ و خالی‌کردن سبد');

    await page.route(store + '/wp-json/wc/store/v1/products**', route => route.abort());
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.getByText(/در حال نمایش فهرست ذخیره‌شده/).waitFor({ timeout: 35000 });
    await by(/^product-open-/).first().waitFor();
    await page.unrouteAll();
    check('نمایش کاتالوگ ذخیره‌شده در قطع ارتباط');

    assert.deepEqual(errors, []);
  } finally {
    await browser.close();
  }
})().catch(error => {
  const safe = (error?.stack || String(error)).replace(/([?&]session=)[^ '&\n]+/g, '$1[REDACTED]');
  console.error(safe);
  process.exitCode = 1;
});
