import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
  buildCartRequest, catalogResults, categoryScope, coverPhoto, formatMoney, formatPrice, latinDigits, needsPrint,
  normalize, normalizePrint, optionGroups, optionKey, productMatches, purchaseReady, quantityLimits,
  safeCheckoutUrl, textOf, validImage, variantFor,
} from '../src/domain.ts';

const input = name => JSON.parse(readFileSync(new URL('../../catalog/' + name + '.json', import.meta.url), 'utf8'));
const products = input('products');
const inventory = new Map(input('variations').map(item => [item.id, item]));
const categories = input('categories');
const catalog = { products, categories };
const cases = [];
const add = (name, run) => cases.push([name, run]);
const numeric = value => latinDigits(value).replace(/[^\d-]/g, '');

function choicesOf(product, variant) {
  return Object.fromEntries(optionGroups(product).map(group => {
    const option = variant.attributes.find(attr => normalize(attr.name) === normalize(group.name));
    const defaultValue = group.taxonomy ? group.terms[0]?.slug : group.terms[0]?.name;
    return [optionKey(group.name), option?.value || defaultValue || ''];
  }));
}

function categoryAncestors(id) {
  const path = new Set();
  let current = categories.find(row => row.id === id);
  while (current && !path.has(current.id)) {
    path.add(current.id);
    current = categories.find(row => row.id === current.parent);
  }
  return path;
}

for (const product of products) {
  for (const ref of product.variations) {
    const selected = choicesOf(product, ref);
    const variant = inventory.get(ref.id);
    const explicit = ref.attributes.some(attribute => !attribute.value)
      ? product.variations.find(candidate => candidate.attributes.length === ref.attributes.length
        && candidate.attributes.every(attribute => attribute.value && attribute.value === selected[optionKey(attribute.name)]))
      : undefined;
    const expectedChoice = explicit?.id ?? ref.id;
    add('V' + ref.id + ' انتخاب ترکیب گزینه‌ها', () => {
      assert.equal(variant.parent, product.id);
      const resolved = variantFor(product, selected);
      assert.ok(resolved);
      assert.equal(resolved.id, expectedChoice);
      const incomplete = { ...selected };
      delete incomplete[Object.keys(incomplete)[0]];
      assert.equal(variantFor(product, incomplete), null);
    });
    add('V' + ref.id + ' قیمت، موجودی و درخواست سبد', () => {
      const ready = variant.is_in_stock && variant.is_purchasable;
      assert.equal(purchaseReady(variant), ready);
      assert.match(formatMoney(variant.prices.price, variant.prices), /تومان$/);
      assert.equal(Number(numeric(formatMoney(variant.prices.price, variant.prices))), Number(variant.prices.price));
      const request = () => buildCartRequest(product, ref, variant, selected, 1, { name: 'نمونه', number: '۱۰' });
      if (expectedChoice !== ref.id) {
        assert.throws(request, /گزینه/);
      } else if (!ready) {
        assert.throws(request, /قابل خرید نیست/);
      } else {
        const result = request();
        assert.equal(result.id, ref.id);
        assert.equal(result.quantity, 1);
        assert.equal(result.variation.length, ref.attributes.length);
        if (needsPrint(ref.attributes)) {
          assert.equal(result.mv_print_name, 'نمونه');
          assert.equal(result.mv_print_number, '10');
        } else {
          assert.equal(result.mv_print_name, undefined);
        }
      }
    });
  }
  add('P' + product.id + ' نمایش و جست‌وجوی کالا', () => {
    const display = textOf(product.name);
    assert.ok(display.length > 3);
    assert.equal(productMatches(product, display, null), true);
    assert.equal(productMatches(product, 'سرچ-آزمایشی-هیچ-۲۱۸۸ـ', null), false);
    assert.ok(catalogResults(catalog, display, null, 'latest').some(entry => entry.id === product.id));
  });
  add('P' + product.id + ' تصویر و قیمت کارت کالا', () => {
    assert.ok(coverPhoto(product.images));
    assert.match(formatPrice(product.prices, true), /تومان$/);
    assert.equal(formatPrice(product.prices, true).startsWith('از '),
      !!product.prices.price_range && product.prices.price_range.min_amount !== product.prices.price_range.max_amount);
    assert.equal(quantityLimits(product).minimum, 1);
  });
}

for (const category of categories) {
  add('C' + category.id + ' محدودهٔ دسته', () => {
    const scope = categoryScope(categories, category.id);
    assert.ok(scope.has(category.id));
    assert.ok(textOf(category.name));
  });
  add('C' + category.id + ' زیر‌دسته‌ها', () => {
    const scope = categoryScope(categories, category.id);
    const expected = categories.filter(row => categoryAncestors(row.id).has(category.id)).map(row => row.id);
    assert.deepEqual([...scope].sort((a, b) => a - b), expected.sort((a, b) => a - b));
  });
  add('C' + category.id + ' نتایج کاتالوگ', () => {
    const expected = products.filter(row => row.categories.some(item => categoryAncestors(item.id).has(category.id))).map(row => row.id);
    assert.deepEqual(catalogResults(catalog, '', category.id, 'latest').map(row => row.id), expected);
  });
  add('C' + category.id + ' ترتیب قیمت در دسته', () => {
    const price = row => Number(row.prices.price_range?.min_amount ?? row.prices.price);
    const low = catalogResults(catalog, '', category.id, 'low').map(price);
    const high = catalogResults(catalog, '', category.id, 'high').map(price);
    assert.deepEqual(low, [...low].sort((a, b) => a - b));
    assert.deepEqual(high, [...high].sort((a, b) => b - a));
    assert.deepEqual(high, low.reverse());
  });
}

const simple = products.find(item => item.type === 'simple' && item.is_purchasable);
const variable = products.find(item => item.type === 'variable');
const iran = { currency_code: 'IRT', currency_minor_unit: 0, currency_symbol: 'تومان' };
const usd = { currency_code: 'USD', currency_minor_unit: 2, currency_symbol: '$' };
const print = { name: 'آرش', number: '۷' };
const casesAtBoundaries = [
  ['عنوان خالی', () => assert.equal(textOf(undefined), '')],
  ['تبدیل پاراگراف به متن', () => assert.equal(textOf('<p>الف</p><p>ب</p>'), 'الف ب')],
  ['تبدیل شکست خط', () => assert.equal(textOf('الف<br/>ب'), 'الف ب')],
  ['موجودیت نقل قول', () => assert.equal(textOf('&quot;الف&quot;'), '"الف"')],
  ['موجودیت عددی دهدهی', () => assert.equal(textOf('&#1777;'), '۱')],
  ['موجودیت عددی شانزدهی', () => assert.equal(textOf('&#x6f1;'), '۱')],
  ['موجودیت ناشناخته', () => assert.equal(textOf('&brandname;'), '&brandname;')],
  ['نیم‌فاصلهٔ فرستاده‌شده توسط فروشگاه', () => assert.equal(textOf('نیم\\u200cساق'), 'نیم‌ساق')],
  ['یکسان‌سازی ک و ی', () => assert.equal(normalize('كيت'), 'کیت')],
  ['یکسان‌سازی اعراب', () => assert.equal(normalize('کِــفش'), 'کفش')],
  ['یکسان‌سازی عدد فارسی', () => assert.equal(latinDigits('۰۱۲۳۴۵۶۷۸۹'), '0123456789')],
  ['یکسان‌سازی عدد عربی', () => assert.equal(latinDigits('٠١٢٣٤٥٦٧٨٩'), '0123456789')],
  ['جست‌وجوی لاتین بدون حساسیت به حروف بزرگ', () => assert.equal(normalize(' NIKE '), 'nike')],
  ['نیم‌فاصله در جست‌وجو', () => assert.equal(normalize('نیم‌ساق'), 'نیم ساق')],
  ['نام چاپی با فاصله و املای گزینه‌های چاپ', () => {
    assert.deepEqual(normalizePrint({ name: '  علی  ', number: '  ۹  ' }), { name: 'علی', number: '9' });
    assert.ok(needsPrint([{ name: 'سایز', value: 'سایز 2XL با اسم وشماره دلخواه' }]));
    assert.ok(needsPrint([{ name: 'سایز', value: 'سایز ۴ با نام‌و‌شمارهٔ دلخواه' }]));
    assert.ok(needsPrint([{ name: 'سایز', value: encodeURIComponent('سایز-۲-با-اسم-وشماره-دلخواه') }]));
    assert.equal(needsPrint([{ name: 'سایز', value: 'سایز ۴ بدون چاپ' }]), false);
  }],
  ['شمارهٔ چاپی صفر', () => assert.equal(normalizePrint({ name: 'الف', number: '۰' }).number, '0')],
  ['شمارهٔ چاپی دو رقمی', () => assert.equal(normalizePrint({ name: 'الف', number: '۹۹' }).number, '99')],
  ['شمارهٔ چاپی سه رقمی', () => assert.equal(normalizePrint({ name: 'الف', number: '۱۰۰' }), null)],
  ['شمارهٔ چاپی الفبایی', () => assert.equal(normalizePrint({ name: 'الف', number: 'A' }), null)],
  ['نام چاپی خالی', () => assert.equal(normalizePrint({ name: ' ', number: '۹' }), null)],
  ['نام چاپی سی‌حرفی', () => assert.ok(normalizePrint({ name: 'ب'.repeat(30), number: '۹' }))],
  ['نام چاپی سی‌ویک‌حرفی', () => assert.equal(normalizePrint({ name: 'ب'.repeat(31), number: '۹' }), null)],
  ['صفر تومان', () => assert.equal(formatMoney('0', iran), '۰ تومان')],
  ['مقدار نامعتبر', () => assert.equal(formatMoney('12abc', iran), '—')],
  ['ارز با کوچک‌ترین واحد', () => assert.match(formatMoney('125', usd), /۱٫۲۵/)],
  ['تصویر خالی', () => assert.equal(coverPhoto([]), undefined)],
  ['برگشت از بندانگشتی نامعتبر به تصویر اصلی', () => assert.equal(coverPhoto([{ thumbnail: 'foo', src: 'https://store.test/item.png' }]), 'https://store.test/item.png')],
  ['تصویر با پروتکل دیگر', () => assert.equal(validImage('file:///tmp/x.jpg'), undefined)],
  ['تصویر با دادهٔ احراز هویت در نشانی', () => assert.equal(validImage('https://user:pass@store.test/image.jpg'), undefined)],
  ['تسویهٔ هم‌مبدأ HTTPS', () => assert.equal(safeCheckoutUrl('https://store.test/checkout/', 'https://store.test'), 'https://store.test/checkout/')],
  ['تسویهٔ توسعه روی loopback', () => assert.equal(safeCheckoutUrl('http://127.0.0.1:8087/checkout/', 'http://127.0.0.1:8087'), 'http://127.0.0.1:8087/checkout/')],
  ['تسویهٔ دامنهٔ متفاوت', () => assert.throws(() => safeCheckoutUrl('https://other.test/checkout/', 'https://store.test'))],
  ['تسویهٔ پورت متفاوت', () => assert.throws(() => safeCheckoutUrl('https://store.test:444/checkout/', 'https://store.test'))],
  ['تسویهٔ پروتکل نابرابر', () => assert.throws(() => safeCheckoutUrl('http://store.test/checkout/', 'https://store.test'))],
  ['تسویهٔ دارای userinfo', () => assert.throws(() => safeCheckoutUrl('https://demo:demo@store.test/checkout/', 'https://store.test'))],
  ['تسویهٔ بدون نشانی مطلق', () => assert.throws(() => safeCheckoutUrl('/checkout/', 'https://store.test'))],
  ['افزودن کالای ساده', () => assert.equal(buildCartRequest(simple, null, undefined, {}, 1, print).id, simple.id)],
  ['تعداد منفی سفارش', () => assert.throws(() => buildCartRequest(simple, null, undefined, {}, -1, print), /تعداد/)],
  ['تعداد غیرصحیح سفارش', () => assert.throws(() => buildCartRequest(simple, null, undefined, {}, 1.3, print), /تعداد/)],
  ['افزودن کالای متغیر بدون انتخاب', () => assert.throws(() => buildCartRequest(variable, null, undefined, {}, 1, print), /گزینه/)],
];
for (const [label, check] of casesAtBoundaries) add('E ' + label, check);

assert.equal(products.length, 116);
assert.equal(inventory.size, 826);
assert.equal(categories.length, 19);
assert.equal(cases.length, 2000);
for (const [name, run] of cases) test(name, run);
