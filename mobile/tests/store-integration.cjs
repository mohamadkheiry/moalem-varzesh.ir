const { test } = require('node:test');
const assert = require('node:assert/strict');

const origin = new URL(process.env.MV_STORE_TEST_URL || 'http://127.0.0.1:8087').origin;
assert.match(origin, /^http:\/\/(localhost|127\.0\.0\.1)(:|$)/, 'Integration tests use only the local store');
const prefix = origin + '/wp-json/wc/store/v1';
let token;

test('The local browser preview receives scoped Store API CORS headers', async () => {
  const previewOrigin = 'http://127.0.0.1:8091';
  const address = prefix + '/cart/add-item';
  const optionsFor = originHeader => ({
    method: 'OPTIONS',
    headers: {
      Origin: originHeader,
      'Access-Control-Request-Method': 'POST',
      'Access-Control-Request-Headers': 'content-type,cart-token',
    },
    signal: AbortSignal.timeout(25000),
  });
  const allowed = await fetch(address, optionsFor(previewOrigin));
  assert.ok(allowed.ok);
  assert.equal(allowed.headers.get('Access-Control-Allow-Origin'), previewOrigin);
  assert.match(allowed.headers.get('Access-Control-Allow-Headers'), /cart-token/i);
  const other = await fetch(address, optionsFor('https://outside.example.test'));
  assert.equal(other.status, 403);
});

async function request(path, data) {
  const headers = { Accept: 'application/json' };
  if (token) headers['Cart-Token'] = token;
  if (data) headers['Content-Type'] = 'application/json';
  const response = await fetch(prefix + path, {
    headers, method: data ? 'POST' : 'GET', body: data ? JSON.stringify(data) : undefined,
    signal: AbortSignal.timeout(25000),
  });
  token = response.headers.get('Cart-Token') || token;
  return [response.status, await response.json()];
}

test('A guest can transfer a personalized cart to WooCommerce checkout', async () => {
  const [catalogStatus, catalog] = await request('/products?per_page=5');
  assert.equal(catalogStatus, 200);
  const product = catalog.find(row => row.variations.some(ref => ref.attributes.some(a => a.value?.includes('نام و شماره دلخواه'))));
  assert.ok(product);
  const variant = product.variations.find(ref => ref.attributes.some(a => a.value?.includes('نام و شماره دلخواه')));
  const payload = { id: variant.id, quantity: 1, variation: variant.attributes.map(a => ({ attribute: a.name, value: a.value })) };

  const [emptyCode, empty] = await request('/cart');
  assert.equal(emptyCode, 200);
  assert.equal(empty.items_count, 0);
  assert.ok(token);

  const [invalidCode, invalid] = await request('/cart/add-item', payload);
  assert.equal(invalidCode, 400);
  assert.equal(invalid.code, 'mv_print_required');

  const [addedCode, added] = await request('/cart/add-item', { ...payload, mv_print_name: 'آزمون موبایل', mv_print_number: '۱۰' });
  assert.ok(addedCode === 200 || addedCode === 201, JSON.stringify({ code: added.code, message: added.message }));
  assert.equal(added.items_count, 1);
  const line = added.items[0];
  try {
    assert.ok(line.item_data.some(meta => meta.value === 'آزمون موبایل'));
    assert.ok(line.item_data.some(meta => String(meta.value) === '10'));
    const [transferCode, transfer] = await request('/cart?mv_checkout=1');
    assert.equal(transferCode, 200, JSON.stringify({ code: transfer.code, message: transfer.message }));
    const target = new URL(transfer.extensions['moalem-varzesh'].checkout_url);
    assert.equal(target.origin, origin);
    assert.equal(target.pathname, '/checkout/');
    assert.ok(target.searchParams.get('session'));

    const page = await fetch(target.href, { redirect: 'manual', signal: AbortSignal.timeout(25000) });
    assert.ok([200, 302].includes(page.status));
    let body = await page.text();
    if (page.status === 302) {
      const location = new URL(page.headers.get('Location'), origin);
      assert.equal(location.origin, origin);
      const cookie = page.headers.getSetCookie().map(line => line.split(';')[0]).join('; ');
      body = await fetch(location.href, { headers: { Cookie: cookie }, signal: AbortSignal.timeout(25000) }).then(result => result.text());
    }
    assert.ok(body.includes('billing_email'), 'Checkout form was present.');
    assert.ok(body.includes('woocommerce-checkout-review-order-table'), 'Transferred items were present.');
    assert.ok(body.includes('آزمون موبایل'), 'Print details transferred to checkout.');
  } finally {
    const [removedCode, removed] = await request('/cart/remove-item', { key: line.key });
    assert.equal(removedCode, 200);
    assert.equal(removed.items_count, 0);
  }
});
