export type Money = {
  price: string;
  currency_code: string;
  currency_minor_unit: number;
  currency_symbol: string;
  price_range?: { min_amount: string; max_amount: string } | null;
};

export type Photo = { id: number; src: string; thumbnail?: string; alt?: string; name?: string };
export type Category = { id: number; name: string; slug: string; parent?: number; count?: number; image?: Photo | null };
export type Option = { name: string; value: string | null };
export type VariantRef = { id: number; attributes: Option[] };
export type Attribute = {
  name: string;
  taxonomy: string | null;
  has_variations: boolean;
  terms: { name: string; slug: string }[];
};
export type Product = {
  id: number;
  parent: number;
  name: string;
  slug: string;
  sku: string;
  type: string;
  permalink: string;
  prices: Money;
  images: Photo[];
  categories: Category[];
  attributes: Attribute[];
  variations: VariantRef[];
  short_description?: string;
  description?: string;
  is_in_stock: boolean;
  is_purchasable: boolean;
  sold_individually?: boolean;
  add_to_cart?: { minimum: number; maximum: number; multiple_of: number };
};
export type Catalog = { products: Product[]; categories: Category[] };
export type Selections = Record<string, string>;
export type Variant = Product;
export type PrintDetails = { name: string; number: string };
export type Sort = 'latest' | 'low' | 'high' | 'name';
export type CartRequest = {
  id: number;
  quantity: number;
  variation: { attribute: string; value: string }[];
  mv_print_name?: string;
  mv_print_number?: string;
};

const publicEntities: Record<string, string> = {
  amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', zwnj: '\u200c',
  ndash: '–', mdash: '—', ldquo: '“', rdquo: '”', lsquo: '‘', rsquo: '’', hellip: '…',
};

export function textOf(raw: string | undefined): string {
  return (raw ?? '')
    .replace(/<[^>]*>/g, tag => /^<\/?(p|br|div|li|h[1-6])\b/i.test(tag) ? ' ' : '')
    .replace(/\\u200c/gi, '\u200c')
    .replace(/&(#x[\da-f]+|#\d+|[a-z]+);/gi, (all, entity: string) => {
      if (entity[0] !== '#') return publicEntities[entity.toLowerCase()] ?? all;
      const hex = entity[1].toLowerCase() === 'x';
      const value = Number.parseInt(entity.slice(hex ? 2 : 1), hex ? 16 : 10);
      return value > 0 && value <= 0x10ffff ? String.fromCodePoint(value) : all;
    })
    .replace(/\s+/g, ' ')
    .trim();
}

export function latinDigits(value: string): string {
  return value.replace(/[۰-۹٠-٩]/g, digit => {
    const point = digit.charCodeAt(0);
    return String(point - (point >= 0x06f0 ? 0x06f0 : 0x0660));
  });
}

export function normalize(value: string): string {
  return latinDigits(textOf(value).normalize('NFKC'))
    .replace(/[يى]/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/[\u0640\u064b-\u065f\u0670]/g, '')
    .replace(/[\u200c\u200d]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

export function validImage(value: string | undefined): string | undefined {
  if (!value) return undefined;
  try {
    const url = new URL(value);
    return ['https:', 'http:'].includes(url.protocol) && !url.username && !url.password ? url.href : undefined;
  } catch {
    return undefined;
  }
}

export function coverPhoto(photos: Photo[] | undefined, useThumbnail = true): string | undefined {
  for (const image of photos ?? []) {
    const url = validImage(useThumbnail ? image.thumbnail : image.src) ?? validImage(image.src);
    if (url) return url;
  }
  return undefined;
}

export function formatMoney(amount: string, currency: { currency_code: string; currency_minor_unit: number; currency_symbol: string }): string {
  if (!/^-?\d+$/.test(amount)) return '—';
  const places = Math.min(6, Math.max(0, currency.currency_minor_unit || 0));
  const value = Number(amount) / 10 ** places;
  if (!Number.isFinite(value)) return '—';
  const unit = currency.currency_code === 'IRT' ? 'تومان' : textOf(currency.currency_symbol) || currency.currency_code;
  const display = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: places }).format(value);
  return display + ' ' + unit;
}

export function formatPrice(prices: Money, useStartingPrice = false): string {
  const range = prices.price_range;
  if (!range || range.min_amount === range.max_amount) return formatMoney(prices.price, prices);
  const minimum = formatMoney(range.min_amount, prices);
  return useStartingPrice ? 'از ' + minimum : minimum + ' تا ' + formatMoney(range.max_amount, prices);
}

export function categoryScope(categories: Category[], category: number | null): Set<number> | null {
  if (category === null) return null;
  const scope = new Set([category]);
  let changed = true;
  while (changed) {
    changed = false;
    for (const item of categories) {
      if (item.parent && scope.has(item.parent) && !scope.has(item.id)) {
        scope.add(item.id);
        changed = true;
      }
    }
  }
  return scope;
}

export function productMatches(product: Product, query: string, scope: Set<number> | null): boolean {
  if (scope && !product.categories.some(category => scope.has(category.id))) return false;
  const tokens = normalize(query).split(' ').filter(Boolean);
  if (!tokens.length) return true;
  const name = normalize(product.name + ' ' + product.sku);
  return tokens.every(token => name.includes(token));
}

export function catalogResults(catalog: Catalog, query: string, category: number | null, sort: Sort): Product[] {
  const scope = categoryScope(catalog.categories, category);
  const products = catalog.products.filter(product => productMatches(product, query, scope));
  if (sort === 'latest') return products;
  if (sort === 'name') return products.sort((a, b) => textOf(a.name).localeCompare(textOf(b.name), 'fa'));
  const unitPrice = (p: Product) => Number(p.prices.price_range?.min_amount ?? p.prices.price) / 10 ** (p.prices.currency_minor_unit || 0);
  return products.sort((a, b) => sort === 'low' ? unitPrice(a) - unitPrice(b) : unitPrice(b) - unitPrice(a));
}

export function featuredCategories(categories: Category[]): Category[] {
  return categories.filter(category => !category.parent && (category.count ?? 0) > 0)
    .sort((a, b) => (b.count ?? 0) - (a.count ?? 0))
    .slice(0, 4);
}

export function optionKey(name: string): string {
  return normalize(name);
}

export function optionGroups(product: Product): Attribute[] {
  return (product.attributes ?? []).filter(attribute => attribute.has_variations);
}

export function variantFor(product: Product, choices: Selections): VariantRef | null {
  const groups = optionGroups(product);
  if (!groups.length || groups.some(attribute => !choices[optionKey(attribute.name)])) return null;
  const matches = (product.variations ?? []).filter(variant => variant.attributes.every(attribute =>
    !attribute.value || normalize(attribute.value) === normalize(choices[optionKey(attribute.name)] ?? '')
  ));
  return matches.sort((a, b) => b.attributes.filter(option => option.value).length - a.attributes.filter(option => option.value).length)[0] ?? null;
}

export function needsPrint(options: Option[]): boolean {
  return options.some(option => normalize(option.value ?? '').includes('نام و شماره دلخواه'));
}

export function normalizePrint(input: PrintDetails): PrintDetails | null {
  const name = input.name.trim();
  const number = latinDigits(input.number.trim());
  return name && Array.from(name).length <= 30 && /^\d{1,2}$/.test(number) ? { name, number } : null;
}

export function quantityLimits(product: Product): { minimum: number; maximum: number; step: number } {
  if (product.sold_individually) return { minimum: 1, maximum: 1, step: 1 };
  const limits = product.add_to_cart;
  return {
    minimum: Math.max(1, limits?.minimum || 1),
    maximum: Math.max(1, limits?.maximum || 9999),
    step: Math.max(1, limits?.multiple_of || 1),
  };
}

export function purchaseReady(product: Product | undefined): boolean {
  return Boolean(product?.is_in_stock && product.is_purchasable);
}

export function buildCartRequest(
  product: Product,
  ref: VariantRef | null,
  variant: Variant | undefined,
  selections: Selections,
  quantity: number,
  print: PrintDetails,
): CartRequest {
  const chosen = product.type === 'variable' ? variant : product;
  if (!chosen || (product.type === 'variable' && (!ref || ref.id !== chosen.id || ref.id !== variantFor(product, selections)?.id))) {
    throw new Error('ابتدا گزینهٔ کالا را انتخاب کنید.');
  }
  if (!purchaseReady(chosen)) throw new Error('این گزینه فعلاً قابل خرید نیست.');
  const { minimum, maximum, step } = quantityLimits(chosen);
  if (!Number.isInteger(quantity) || quantity < minimum || quantity > maximum || quantity % step !== 0) {
    throw new Error('تعداد انتخاب‌شده برای این کالا معتبر نیست.');
  }
  const options = ref?.attributes ?? [];
  const result: CartRequest = {
    id: chosen.id,
    quantity,
    variation: options.map(option => ({
      attribute: textOf(option.name),
      value: textOf(option.value || selections[optionKey(option.name)]),
    })),
  };
  const printOptions = options.map(option => ({ ...option, value: option.value || selections[optionKey(option.name)] }));
  if (needsPrint(printOptions)) {
    const values = normalizePrint(print);
    if (!values) throw new Error('نام و شمارهٔ چاپ را وارد کنید.');
    result.mv_print_name = values.name;
    result.mv_print_number = values.number;
  }
  return result;
}

export function safeCheckoutUrl(value: string, origin: string): string {
  const url = new URL(value);
  if (url.origin !== new URL(origin).origin || !['https:', 'http:'].includes(url.protocol) || url.username || url.password) {
    throw new Error('نشانی تسویه‌حساب معتبر نیست.');
  }
  return url.href;
}
