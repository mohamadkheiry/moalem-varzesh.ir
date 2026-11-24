import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { safeCheckoutUrl, textOf } from './domain';
import type { CartRequest, Catalog, Category, Money, Photo, Product } from './domain';

const configuredStore = process.env.EXPO_PUBLIC_STORE_URL;
export const storeOrigin = new URL(configuredStore || 'https://www.moalem-varzesh.ir').origin;
const localStore = /^http:\/\/(localhost|127\.0\.0\.1|10\.0\.2\.2)(:\d+)?$/.test(storeOrigin);
if (!/^https:/.test(storeOrigin) && !localStore) {
  throw new Error('Use HTTPS for the store outside the local development environment.');
}

const api = storeOrigin + '/wp-json/wc/store/v1';
const storageId = storeOrigin.replace(/[^a-zA-Z0-9.-]/g, '_');
const tokenKey = 'mv.cart.' + storageId;
let cartToken: string | null = null;
let tokenLoad: Promise<void> | undefined;

type CartItem = {
  key: string;
  id: number;
  name: string;
  quantity: number;
  quantity_limits: { minimum: number; maximum: number; multiple_of: number; editable: boolean };
  prices: Money;
  images: Photo[];
  variation: { attribute: string; value: string }[];
  item_data: { key: string; value: string }[];
  totals: { line_total: string; line_total_tax: string; currency_code: string; currency_minor_unit: number; currency_symbol: string };
};

type CartTotals = {
  total_items: string;
  total_price: string;
  total_discount: string;
  total_shipping: string;
  currency_code: string;
  currency_minor_unit: number;
  currency_symbol: string;
};

export type Cart = {
  items: CartItem[];
  items_count: number;
  needs_shipping: boolean;
  has_calculated_shipping: boolean;
  payment_methods?: string[];
  totals: CartTotals;
  extensions?: { 'moalem-varzesh'?: { checkout_url?: string } };
};

class StoreError extends Error {
  constructor(message: string, public status: number) {
    super(message);
  }
}

async function loadCartToken(): Promise<void> {
  if (!tokenLoad) {
    tokenLoad = (async () => {
      try {
        cartToken = Platform.OS === 'web'
          ? window.sessionStorage.getItem(tokenKey)
          : await SecureStore.getItemAsync(tokenKey);
      } catch {
        cartToken = null;
      }
    })();
  }
  await tokenLoad;
}

async function saveCartToken(value: string | null): Promise<void> {
  cartToken = value;
  try {
    if (Platform.OS === 'web') {
      if (value) window.sessionStorage.setItem(tokenKey, value);
      else window.sessionStorage.removeItem(tokenKey);
    } else if (value) {
      await SecureStore.setItemAsync(tokenKey, value);
    } else {
      await SecureStore.deleteItemAsync(tokenKey);
    }
  } catch {
    // In-memory session remains available while the app is open.
  }
}

type RequestOptions = { payload?: object; cart?: boolean; signal?: AbortSignal };

async function request<T>(path: string, options: RequestOptions = {}): Promise<{ data: T; headers: Headers }> {
  if (options.cart) await loadCartToken();

  const controller = new AbortController();
  const abort = () => controller.abort();
  if (options.signal?.aborted) abort();
  options.signal?.addEventListener('abort', abort, { once: true });
  // The development PHP server can serve only one request at a time on Windows.
  const timer = setTimeout(abort, localStore ? 60000 : 20000);
  try {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (options.payload) headers['Content-Type'] = 'application/json';
    if (options.cart && cartToken) headers['Cart-Token'] = cartToken;

    const response = await fetch(api + path, {
      method: options.payload ? 'POST' : 'GET',
      headers,
      body: options.payload ? JSON.stringify(options.payload) : undefined,
      credentials: 'omit',
      signal: controller.signal,
    });

    let data;
    try {
      data = await response.json();
    } catch {
      throw new StoreError('پاسخ فروشگاه قابل خواندن نیست.', response.status);
    }
    if (!response.ok) {
      throw new StoreError(textOf(data?.message) || 'ارتباط با فروشگاه برقرار نشد.', response.status);
    }
    if (options.cart) {
      const token = response.headers.get('Cart-Token');
      if (token && token !== cartToken) await saveCartToken(token);
    }
    return { data: data as T, headers: response.headers };
  } catch (error) {
    if (error instanceof StoreError || options.signal?.aborted) throw error;
    throw new Error('ارتباط با فروشگاه برقرار نشد. دوباره تلاش کنید.');
  } finally {
    clearTimeout(timer);
    options.signal?.removeEventListener('abort', abort);
  }
}

const catalogFields = 'id,parent,name,slug,sku,type,permalink,prices,images,categories,attributes,variations,is_in_stock,is_purchasable,sold_individually,add_to_cart';

async function listProducts(query: string, signal?: AbortSignal): Promise<Product[]> {
  const size = 100;
  const first = await request<Product[]>(query + '&page=1&per_page=' + size, { signal });
  if (!Array.isArray(first.data)) throw new Error('فهرست کالاها قابل خواندن نیست.');
  const pages = Number(first.headers.get('X-WP-TotalPages'));
  let results = first.data;
  for (let page = 2; page <= 100 && (pages ? page <= pages : results.length === (page - 1) * size); page++) {
    const next = await request<Product[]>(query + '&page=' + page + '&per_page=' + size, { signal });
    if (!Array.isArray(next.data) || !next.data.length) break;
    results = results.concat(next.data);
  }
  return [...new Map(results.map(item => [item.id, item])).values()];
}

export async function fetchCatalog(signal?: AbortSignal): Promise<Catalog> {
  const [products, categoryResponse] = await Promise.all([
    listProducts('/products?orderby=date&order=desc&_fields=' + catalogFields, signal),
    request<Category[]>('/products/categories?per_page=100&hide_empty=true', { signal }),
  ]);
  const categories = categoryResponse.data.filter(item => item.slug !== 'uncategorized');
  return { products, categories };
}

export function readProduct(id: number, signal?: AbortSignal) {
  return request<Product>('/products/' + id, { signal }).then(response => response.data);
}

export function readVariants(id: number, signal?: AbortSignal) {
  return listProducts('/products?type=variation&parent=' + id + '&_fields=' + catalogFields, signal);
}

export async function readCart(): Promise<Cart> {
  try {
    return (await request<Cart>('/cart', { cart: true })).data;
  } catch (error) {
    if (error instanceof StoreError && [401, 403].includes(error.status) && cartToken) {
      await saveCartToken(null);
      return (await request<Cart>('/cart', { cart: true })).data;
    }
    throw error;
  }
}

export async function addCartItem(payload: CartRequest): Promise<Cart> {
  await loadCartToken();
  if (!cartToken) await readCart();
  if (!cartToken) throw new Error('نشست سبد خرید در دسترس نیست.');
  return (await request<Cart>('/cart/add-item', { cart: true, payload })).data;
}

export async function changeCartItem(key: string, quantity: number): Promise<Cart> {
  const path = quantity === 0 ? '/cart/remove-item' : '/cart/update-item';
  const payload = quantity === 0 ? { key } : { key, quantity };
  return (await request<Cart>(path, { cart: true, payload })).data;
}

export async function checkoutUrl(): Promise<string> {
  const { data } = await request<Cart>('/cart?mv_checkout=1', { cart: true });
  const url = data.extensions?.['moalem-varzesh']?.checkout_url;
  if (!url) throw new Error('انتقال سبد به تسویه‌حساب در دسترس نیست.');
  return safeCheckoutUrl(url, storeOrigin);
}

export async function readCache<T>(name: string): Promise<T | null> {
  try {
    const value = await AsyncStorage.getItem('mv.' + name + '.' + storageId);
    return value ? JSON.parse(value) as T : null;
  } catch {
    return null;
  }
}

export async function writeCache(name: string, value: unknown): Promise<void> {
  try {
    await AsyncStorage.setItem('mv.' + name + '.' + storageId, JSON.stringify(value));
  } catch {
    // Browsing and the current session can continue if local storage is full.
  }
}
