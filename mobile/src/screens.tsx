import { useEffect, useMemo, useState } from 'react';
import {
  FlatList, Image, Keyboard, Modal, Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, useWindowDimensions, View,
} from 'react-native';
import {
  ArrowLeft, Check, ChevronDown, CreditCard, Heart, Minus, Plus, ShoppingBag, SlidersHorizontal, Trash2, Truck,
} from './icons';
import { readProduct, readVariants } from './api';
import type { Cart } from './api';
import {
  buildCartRequest, catalogResults, coverPhoto, featuredCategories, formatMoney, formatPrice, needsPrint,
  optionGroups, optionKey, purchaseReady, quantityLimits, textOf, validImage, variantFor,
} from './domain';
import type { CartRequest, Catalog, Category, PrintDetails, Product, Selections, Sort } from './domain';
import { Action, Chip, color, IconButton, Label, Line, Picture, SearchField, SectionTitle, shared, StatePanel } from './ui';

type BrowseProps = { onOpen: (item: Product) => void; saved: number[]; toggle: (id: number) => void };
type QueryProps = { query: string; setQuery: (value: string) => void };

const persian = new Intl.NumberFormat('fa-IR');
const sortOptions: [Sort, string][] = [
  ['latest', 'تازه‌ترین'], ['low', 'ارزان‌ترین'], ['high', 'گران‌ترین'], ['name', 'نام کالا'],
];

function ProductCard({ item, onOpen, saved, toggle }: { item: Product } & BrowseProps) {
  const source = coverPhoto(item.images);
  const title = textOf(item.name);
  const favorite = saved.includes(item.id);
  return <View style={styles.productCard} testID={'product-card-' + item.id}>
    <Pressable accessibilityRole="button" accessibilityLabel={title} onPress={() => onOpen(item)}
      testID={'product-open-' + item.id} style={({ pressed }) => [styles.productPressable, pressed && styles.cardPressed]}>
      <Picture source={source ? { uri: source } : undefined} style={styles.productPhoto} />
      <View style={styles.productBody}>
        <Label weight="medium" size={14} numberOfLines={2} style={styles.productTitle}>{title}</Label>
        <View style={styles.productPrice}>
          <Label weight="bold" size={15} tone={color.navy}>{formatPrice(item.prices, true)}</Label>
        </View>
        {!item.is_in_stock && <Label size={12} tone={color.danger}>ناموجود</Label>}
      </View>
    </Pressable>
    <View style={styles.favorite}>
      <IconButton icon={Heart} label={favorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'}
        selected={favorite} onPress={() => toggle(item.id)} compact testID={'favorite-' + item.id} />
    </View>
  </View>;
}

function CategoryTile({ category, catalog, onPress }: { category: Category; catalog: Catalog; onPress: () => void }) {
  const example = catalog.products.find(product => product.categories.some(item => item.id === category.id));
  const photo = validImage(category.image?.thumbnail ?? category.image?.src) ?? coverPhoto(example?.images);
  return <Pressable accessibilityRole="button" accessibilityLabel={textOf(category.name)} onPress={onPress}
    style={({ pressed }) => [styles.categoryTile, pressed && styles.cardPressed]} testID={'home-category-' + category.id}>
    <Picture source={photo ? { uri: photo } : undefined} style={styles.categoryPhoto} fit="cover" size={21} />
    <Label size={12} weight="bold" style={styles.categoryName} numberOfLines={2}>{textOf(category.name)}</Label>
    <ArrowLeft size={14} color={color.blue} strokeWidth={1.9} />
  </Pressable>;
}

export function HomeScreen({ catalog, query, setQuery, onShop, onCategory, submitSearch, refreshing, refresh, ...browse }: BrowseProps & QueryProps & {
  catalog: Catalog | null; onShop: () => void; onCategory: (category: Category) => void;
  submitSearch: () => void; refreshing: boolean; refresh: () => void;
}) {
  const { width } = useWindowDimensions();
  return <ScrollView testID="home-screen" contentContainerStyle={shared.content} keyboardShouldPersistTaps="handled"
    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={color.blue} />}>
    <View style={styles.homeSearch}><SearchField value={query} onChange={setQuery} onSubmit={submitSearch} /></View>
    <View style={styles.hero}>
      <View style={styles.heroMedia}>
        <Image source={require('../assets/hero-app-court.webp')} resizeMode="cover" style={styles.heroPhoto} />
        <View style={styles.heroCopy}>
          <Label size={width < 360 ? 22 : 28} weight="bold" tone={color.white} accessibilityRole="header">لباس و{'\n'}تجهیزات ورزشی</Label>
        </View>
      </View>
      <Pressable onPress={onShop} accessibilityRole="button" testID="hero-shop"
        style={({ pressed }) => [styles.heroAction, pressed && styles.cardPressed]}>
        <Label size={15} weight="bold" tone={color.navy}>مشاهده فروشگاه</Label>
        <View style={styles.heroArrow}><ArrowLeft size={21} color={color.navy} strokeWidth={2} /></View>
      </Pressable>
    </View>
    {catalog && <>
      <SectionTitle title="دسته‌بندی کالاها" />
      <View style={styles.categoryGrid}>
        {featuredCategories(catalog.categories).map(category =>
          <CategoryTile key={category.id} category={category} catalog={catalog} onPress={() => onCategory(category)} />)}
      </View>
      <SectionTitle title="تازه‌های فروشگاه" link="همهٔ کالاها" onPress={onShop} />
      <View style={styles.homeGrid}>
        {catalog.products.slice(0, 6).map(item => <ProductCard key={item.id} item={item} {...browse} />)}
      </View>
    </>}
    {!catalog && <StatePanel title={refreshing ? 'در حال دریافت کالاها' : 'فهرست کالاها در دسترس نیست'}
      loading={refreshing} action={refreshing ? undefined : 'تلاش دوباره'} onPress={refresh} />}
  </ScrollView>;
}

function SortSheet({ open, selection, onClose, onSelect }: { open: boolean; selection: Sort; onClose: () => void; onSelect: (value: Sort) => void }) {
  return <Modal visible={open} transparent animationType="none" onRequestClose={onClose}>
    <View style={styles.sheetMask}>
      <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityRole="button" accessibilityLabel="بستن مرتب‌سازی" />
      <View style={styles.sheet} accessibilityViewIsModal>
        <Label accessibilityRole="header" weight="bold" size={19}>مرتب‌سازی کالاها</Label>
        {sortOptions.map(([value, label]) => <Pressable key={value} accessibilityRole="button"
          accessibilityState={{ selected: selection === value }} testID={'sort-' + value}
          style={({ pressed }) => [styles.sheetOption, pressed && styles.cardPressed]} onPress={() => onSelect(value)}>
          <Label size={15} weight="medium">{label}</Label>
          {selection === value && <Check size={20} color={color.blue} />}
        </Pressable>)}
      </View>
    </View>
  </Modal>;
}

export function ShopScreen({ catalog, query, setQuery, category, setCategory, refreshing, refresh, ...browse }: BrowseProps & QueryProps & {
  catalog: Catalog | null; category: number | null; setCategory: (value: number | null) => void; refreshing: boolean; refresh: () => void;
}) {
  const [sort, setSort] = useState<Sort>('latest');
  const [open, setOpen] = useState(false);
  const results = useMemo(() => catalog ? catalogResults(catalog, query, category, sort) : [], [catalog, query, category, sort]);
  const categoryList = useMemo(() => (catalog?.categories ?? []).filter(item => (item.count ?? 0) > 0)
    .sort((a, b) => Number(Boolean(a.parent)) - Number(Boolean(b.parent)) || (b.count ?? 0) - (a.count ?? 0)), [catalog]);
  return <>
    <FlatList testID="shop-screen" keyboardShouldPersistTaps="handled"
      data={results} keyExtractor={item => String(item.id)} numColumns={2}
      contentContainerStyle={shared.content} columnWrapperStyle={styles.column}
      renderItem={({ item }) => <ProductCard item={item} {...browse} />}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={color.blue} />}
      ListHeaderComponent={<>
        <Label accessibilityRole="header" size={25} weight="bold" style={styles.pageHeading}>فروشگاه</Label>
        <SearchField value={query} onChange={setQuery} onSubmit={Keyboard.dismiss} />
        <ScrollView key={category ?? 'all'} horizontal showsHorizontalScrollIndicator={false}
          style={styles.chipScroller} contentContainerStyle={styles.chipContent} keyboardShouldPersistTaps="handled">
          <Chip label="همهٔ کالاها" selected={category === null} onPress={() => setCategory(null)} testID="category-all" />
          {categoryList.map(item => <Chip key={item.id} label={textOf(item.name)} selected={item.id === category}
            onPress={() => setCategory(item.id)} testID={'category-' + item.id} />)}
        </ScrollView>
        <View style={styles.shopTools}>
          <Label size={12} tone={color.muted} testID="result-count">{persian.format(results.length)} کالا</Label>
          <Pressable accessibilityRole="button" accessibilityLabel="مرتب‌سازی" testID="sort-open" onPress={() => setOpen(true)} style={styles.sortButton}>
            <SlidersHorizontal size={16} color={color.blue} />
            <Label weight="medium" size={12}>{sortOptions.find(([value]) => value === sort)?.[1]}</Label>
            <ChevronDown size={14} color={color.muted} />
          </Pressable>
        </View>
      </>}
      ListEmptyComponent={<StatePanel
        title={refreshing ? 'در حال دریافت کالاها' : !catalog ? 'فهرست فروشگاه در دسترس نیست' : 'کالایی پیدا نشد'}
        detail={catalog && !refreshing ? 'عبارت جست‌وجو یا دسته‌بندی را تغییر دهید.' : undefined}
        loading={refreshing} action={!catalog && !refreshing ? 'تلاش دوباره' : undefined} onPress={refresh} />}
      initialNumToRender={12} maxToRenderPerBatch={8} windowSize={7}
    />
    <SortSheet open={open} selection={sort} onClose={() => setOpen(false)} onSelect={value => { setSort(value); setOpen(false); }} />
  </>;
}

export function SavedScreen({ catalog, onShop, ...browse }: BrowseProps & { catalog: Catalog | null; onShop: () => void }) {
  const products = catalog?.products.filter(item => browse.saved.includes(item.id)) ?? [];
  return <FlatList testID="saved-screen" contentContainerStyle={shared.content}
    data={products} keyExtractor={item => String(item.id)} numColumns={2} columnWrapperStyle={styles.column}
    ListHeaderComponent={<Label accessibilityRole="header" size={25} weight="bold" style={styles.pageHeading}>علاقه‌مندی‌ها</Label>}
    ListEmptyComponent={<StatePanel title="کالای ذخیره‌شده‌ای ندارید" detail="برای ذخیرهٔ کالا روی علامت قلب آن بزنید." action="مشاهده فروشگاه" onPress={onShop} />}
    renderItem={({ item }) => <ProductCard item={item} {...browse} />} />;
}

function Stepper({ value, minimum, maximum, step = 1, onChange, disabled = false, testID = 'quantity' }: {
  value: number; minimum: number; maximum: number; step?: number; onChange: (value: number) => void; disabled?: boolean; testID?: string;
}) {
  return <View style={styles.stepper}>
    <IconButton icon={Plus} compact label="افزایش تعداد" testID={testID + '-plus'} disabled={disabled || value + step > maximum} onPress={() => onChange(value + step)} />
    <Label weight="bold" size={13} style={styles.quantityNumber}>{persian.format(value)}</Label>
    <IconButton icon={Minus} compact label="کاهش تعداد" testID={testID + '-minus'} disabled={disabled || value - step < minimum} onPress={() => onChange(value - step)} />
  </View>;
}

export function ProductScreen({ item, saved, toggle, busy, onAdd }: { item: Product; saved: number[]; toggle: (id: number) => void; busy: boolean; onAdd: (request: CartRequest) => Promise<void> }) {
  const [product, setProduct] = useState(item);
  const [variants, setVariants] = useState<Product[]>([]);
  const [pending, setPending] = useState(true);
  const [error, setError] = useState('');
  const [attempt, setAttempt] = useState(0);
  const [selections, setSelections] = useState<Selections>({});
  const [imageIndex, setImageIndex] = useState(0);
  const [print, setPrint] = useState<PrintDetails>({ name: '', number: '' });
  const [quantity, setQuantity] = useState(quantityLimits(item).minimum);
  const [formError, setFormError] = useState('');
  const [expanded, setExpanded] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    setPending(true);
    setError('');
    Promise.all([
      readProduct(item.id, controller.signal),
      item.type === 'variable' ? readVariants(item.id, controller.signal) : Promise.resolve([]),
    ]).then(([fresh, options]) => {
      if (controller.signal.aborted) return;
      setProduct(fresh);
      setVariants(options);
    }).catch(() => {
      if (!controller.signal.aborted) setError('جزئیات کالا دریافت نشد.');
    }).finally(() => {
      if (!controller.signal.aborted) setPending(false);
    });
    return () => controller.abort();
  }, [item.id, item.type, attempt]);

  const groups = optionGroups(product);
  const ref = variantFor(product, selections);
  const chosen = variants.find(variant => variant.id === ref?.id);
  const requiresPrint = needsPrint(ref?.attributes ?? []);
  const images = chosen?.images?.length
    ? [...new Map([...chosen.images, ...product.images].map(image => [image.src, image])).values()]
    : product.images;
  const photo = images[imageIndex] && validImage(images[imageIndex].src);
  const saleItem = product.type === 'variable' ? chosen : product;
  const limits = quantityLimits(saleItem ?? product);
  const count = Math.max(limits.minimum, Math.min(quantity, limits.maximum));
  const available = purchaseReady(saleItem);
  const title = textOf(product.name);
  const description = textOf(product.description || product.short_description);
  const favorite = saved.includes(product.id);
  const onSubmit = async () => {
    try {
      setFormError('');
      await onAdd(buildCartRequest(product, ref, chosen, selections, count, print));
    } catch (issue) {
      setFormError(issue instanceof Error ? issue.message : 'افزودن کالا انجام نشد.');
    }
  };

  return <View style={styles.fill} testID="product-screen">
    <ScrollView contentContainerStyle={shared.content} keyboardShouldPersistTaps="handled">
      <View style={styles.detailImageWrap}>
        <Picture source={photo ? { uri: photo } : undefined} label={title} style={styles.detailImage} size={42} />
        <View style={styles.favorite}>
          <IconButton icon={Heart} label={favorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'}
            selected={favorite} onPress={() => toggle(product.id)} testID="product-favorite" />
        </View>
      </View>
      {images.length > 1 && <ScrollView horizontal showsHorizontalScrollIndicator={false}
        style={styles.thumbnails} contentContainerStyle={styles.thumbRow}>
        {images.map((image, index) => <Pressable accessibilityRole="button" accessibilityLabel={'تصویر ' + persian.format(index + 1)}
          accessibilityState={{ selected: imageIndex === index }} key={image.src} onPress={() => setImageIndex(index)}
          style={[styles.thumbnail, imageIndex === index && styles.thumbnailActive]}>
          <Picture source={{ uri: validImage(image.thumbnail) ?? image.src }} style={styles.thumbnailPicture} />
        </Pressable>)}
      </ScrollView>}
      <View style={styles.detailText}>
        <Label accessibilityRole="header" size={23} weight="bold">{title}</Label>
        <View style={styles.detailPrice}>
          <Label tone={color.navy} weight="bold" size={21} testID="detail-price">{formatPrice(chosen?.prices ?? product.prices)}</Label>
        </View>
      </View>
      {!product.is_in_stock && <Label tone={color.danger} weight="medium">این کالا فعلاً ناموجود است.</Label>}
      {!!error && <StatePanel title={error} action="تلاش دوباره" onPress={() => setAttempt(value => value + 1)} />}
      {groups.map(group => <View key={group.name} style={styles.group}>
        <Label weight="bold" size={15}>{textOf(group.name)}</Label>
        <View style={styles.optionRow}>
          {group.terms.map(term => {
            const value = group.taxonomy ? term.slug : term.name;
            const key = optionKey(group.name);
            return <Chip key={value} label={textOf(term.name)} selected={selections[key] === value}
              onPress={() => {
                setSelections(old => ({ ...old, [key]: value }));
                setImageIndex(0);
                setFormError('');
              }} />;
          })}
        </View>
      </View>)}
      {ref && chosen && !available && <Label tone={color.danger} weight="medium">این گزینه فعلاً قابل خرید نیست.</Label>}
      {requiresPrint && <View style={[shared.card, styles.print]}>
        <Label weight="bold" size={15}>چاپ نام و شماره</Label>
        <Label size={12} tone={color.muted}>نام روی لباس</Label>
        <TextInput accessibilityLabel="نام چاپی" testID="print-name" value={print.name}
          onChangeText={name => setPrint(old => ({ ...old, name }))} maxLength={30} style={shared.input} />
        <Label size={12} tone={color.muted}>شماره روی لباس</Label>
        <TextInput accessibilityLabel="شماره چاپی" testID="print-number" value={print.number}
          onChangeText={number => setPrint(old => ({ ...old, number }))} maxLength={2} keyboardType="number-pad" style={shared.input} />
      </View>}
      {description && <View style={styles.description}>
        <Label weight="bold" size={16}>دربارهٔ کالا</Label>
        <Label size={14} tone={color.muted} numberOfLines={expanded ? undefined : 5}>{description}</Label>
        {description.length > 220 && <Pressable accessibilityRole="button" onPress={() => setExpanded(old => !old)} style={styles.descriptionLink}>
          <Label weight="medium" tone={color.blue}>{expanded ? 'نمایش کمتر' : 'ادامهٔ توضیحات'}</Label>
        </Pressable>}
      </View>}
    </ScrollView>
    <View style={styles.purchase}>
      {!!formError && <Label accessibilityLiveRegion="polite" tone={color.danger} testID="product-error">{formError}</Label>}
      <View style={styles.purchaseRow}>
        <Stepper value={count} {...limits} onChange={setQuantity} disabled={busy || !available} />
        <Action title="افزودن به سبد خرید" icon={ShoppingBag} onPress={() => void onSubmit()} testID="add-to-cart"
          busy={busy} disabled={!available || pending || !!error} style={styles.fill} />
      </View>
    </View>
  </View>;
}

function CartSummary({ cart }: { cart: Cart }) {
  const totals = cart.totals;
  return <View style={[shared.card, styles.summary]}>
    <Label weight="bold" size={17}>خلاصهٔ سفارش</Label>
    <Line label="مبلغ کالاها"><Label size={14} weight="medium">{formatMoney(totals.total_items, totals)}</Label></Line>
    {Number(totals.total_discount) > 0 && <Line label="تخفیف"><Label size={14} tone={color.blue}>{formatMoney(totals.total_discount, totals)}</Label></Line>}
    {cart.needs_shipping && <Line label="هزینهٔ ارسال"><Label size={12} tone={color.muted}>
      {cart.has_calculated_shipping ? formatMoney(totals.total_shipping, totals) : 'محاسبه در تسویه‌حساب'}
    </Label></Line>}
    <View style={styles.total}>
      <Line label="جمع فعلی" strong><Label size={16} weight="bold" testID="cart-total">{formatMoney(totals.total_price, totals)}</Label></Line>
    </View>
  </View>;
}

export function CartScreen({ cart, busy, error, refresh, onShop, onReview, onChange }: {
  cart: Cart | null; busy: boolean; error: string; refresh: () => void; onShop: () => void; onReview: () => void;
  onChange: (key: string, quantity: number) => void;
}) {
  return <View style={styles.fill} testID="cart-screen">
    <ScrollView contentContainerStyle={shared.content}
      refreshControl={<RefreshControl refreshing={busy && !cart} onRefresh={refresh} tintColor={color.blue} />}>
      <Label accessibilityRole="header" size={27} weight="bold" style={styles.pageHeading}>سبد خرید</Label>
      {!!error && <Label tone={color.danger} accessibilityLiveRegion="polite" style={styles.cartError}>{error}</Label>}
      {cart?.items.length ? <>
        <Label size={13} tone={color.muted} style={styles.cartCount}>{persian.format(cart.items_count)} کالا در سبد شما</Label>
        {cart.items.map(item => {
          const photo = coverPhoto(item.images);
          return <View key={item.key} style={[shared.card, styles.cartItem]} testID="cart-item">
            <Picture source={photo ? { uri: photo } : undefined} style={styles.cartPhoto} />
            <View style={styles.cartInfo}>
              <View style={styles.itemHead}>
                <Label weight="bold" size={14} numberOfLines={2} style={styles.fill}>{textOf(item.name)}</Label>
                <IconButton icon={Trash2} label="حذف کالا" compact disabled={busy} onPress={() => onChange(item.key, 0)} testID="cart-remove" />
              </View>
              {item.variation.map(option => <Label size={12} tone={color.muted} key={option.attribute}>{textOf(option.attribute)} · {textOf(option.value)}</Label>)}
              {item.item_data?.map((option, index) => <Label size={12} tone={color.muted} key={option.key + index}>{textOf(option.key)} · {textOf(option.value)}</Label>)}
              <Label weight="bold" tone={color.blue}>{formatMoney(item.totals.line_total, item.totals)}</Label>
              {item.quantity_limits.editable && <Stepper value={item.quantity} minimum={item.quantity_limits.minimum}
                maximum={item.quantity_limits.maximum} step={item.quantity_limits.multiple_of}
                disabled={busy} testID="cart-quantity" onChange={value => onChange(item.key, value)} />}
            </View>
          </View>;
        })}
        <CartSummary cart={cart} />
      </> : <StatePanel kind="cart"
        title={busy ? 'در حال دریافت سبد خرید' : !cart && error ? 'سبد در دسترس نیست' : 'سبد خرید خالی است'}
        detail={cart && !busy ? 'کالاهای دلخواه‌تان را از فروشگاه انتخاب کنید.' : undefined} loading={busy}
        action={busy ? undefined : !cart && error ? 'تلاش دوباره' : 'مشاهده فروشگاه'}
        onPress={!cart && error ? refresh : onShop} />}
    </ScrollView>
    {!!cart?.items.length && <View style={styles.purchase}><Action title="ادامهٔ خرید" onPress={onReview} disabled={busy} testID="review-open" /></View>}
  </View>;
}

export function ReviewScreen({ cart, busy, error, onCheckout }: { cart: Cart; busy: boolean; error: string; onCheckout: () => void }) {
  return <View style={styles.fill} testID="review-screen">
    <ScrollView contentContainerStyle={shared.content}>
      <Label accessibilityRole="header" size={25} weight="bold" style={styles.pageHeading}>تسویه‌حساب</Label>
      <CartSummary cart={cart} />
      <View style={[shared.card, styles.reviewInfo]}>
        <View style={styles.reviewHeading}><Truck size={22} color={color.blue} /><Label weight="bold" size={16}>ارسال سفارش</Label></View>
        <Label size={13} tone={color.muted}>نشانی و روش ارسال را در تسویه‌حساب فروشگاه وارد کنید.</Label>
      </View>
      <View style={[shared.card, styles.reviewInfo]}>
        <View style={styles.reviewHeading}><CreditCard size={22} color={color.blue} /><Label weight="bold" size={16}>روش پرداخت</Label></View>
        <Label size={13} tone={color.muted}>روش‌های پرداخت در صفحهٔ فروشگاه بر اساس سفارش شما نمایش داده می‌شوند.</Label>
      </View>
      {!!error && <Label tone={color.danger} accessibilityLiveRegion="polite">{error}</Label>}
    </ScrollView>
    <View style={styles.purchase}><Action title="ادامه در تسویه‌حساب فروشگاه" testID="checkout-open" onPress={onCheckout} busy={busy} /></View>
  </View>;
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  homeSearch: { marginTop: 14 },
  hero: { borderRadius: 16, backgroundColor: color.white, overflow: 'hidden',
    borderWidth: 1, borderColor: color.line, marginTop: 16, marginBottom: 19 },
  heroMedia: { aspectRatio: 1.55, position: 'relative', backgroundColor: color.navy,
    justifyContent: 'center', alignItems: 'flex-end' },
  heroPhoto: { position: 'absolute', top: 0, left: 0, width: '100%', height: '100%' },
  heroCopy: { paddingRight: 15, paddingLeft: 5, alignItems: 'flex-end', width: '54%' },
  heroAction: { flexDirection: 'row-reverse', justifyContent: 'space-between', alignItems: 'center',
    minHeight: 57, paddingHorizontal: 12, paddingVertical: 7 },
  heroArrow: { width: 36, height: 36, borderRadius: 10, backgroundColor: color.accent,
    alignItems: 'center', justifyContent: 'center' },
  categoryGrid: { flexDirection: 'row-reverse', flexWrap: 'wrap', justifyContent: 'space-between',
    rowGap: 8, marginBottom: 22 },
  categoryTile: { width: '48.6%', minHeight: 64, padding: 6, borderRadius: 12, borderColor: color.line,
    borderWidth: 1, backgroundColor: color.white, gap: 6, flexDirection: 'row-reverse', alignItems: 'center' },
  categoryPhoto: { width: 42, height: 48, borderRadius: 8 },
  categoryName: { flex: 1, minWidth: 0 },
  homeGrid: { flexDirection: 'row-reverse', flexWrap: 'wrap', justifyContent: 'space-between' },
  productCard: { width: '48.5%', marginBottom: 12, borderWidth: 1, borderColor: color.line, borderRadius: 14,
    backgroundColor: color.white, overflow: 'hidden' },
  productPressable: { flex: 1 },
  cardPressed: { backgroundColor: color.soft },
  productPhoto: { aspectRatio: 1.06, backgroundColor: color.soft },
  productBody: { paddingHorizontal: 10, paddingVertical: 9, gap: 6 },
  productTitle: { minHeight: 44 },
  productPrice: { borderTopWidth: 1, borderTopColor: color.line, paddingTop: 6 },
  favorite: { position: 'absolute', top: 7, left: 7 },
  column: { flexDirection: 'row-reverse', justifyContent: 'space-between' },
  pageHeading: { marginTop: 15, marginBottom: 12 },
  chipScroller: { marginTop: 12, marginBottom: 8, direction: 'rtl' },
  chipContent: { gap: 7 },
  shopTools: { flexDirection: 'row-reverse', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  sortButton: { flexDirection: 'row-reverse', gap: 6, alignItems: 'center', minHeight: 44,
    paddingHorizontal: 10, borderRadius: 9, backgroundColor: color.soft },
  sheetMask: { flex: 1, justifyContent: 'flex-end', alignItems: 'center', backgroundColor: '#122A4866' },
  sheet: { width: '100%', maxWidth: 540, padding: 18, paddingBottom: 28, backgroundColor: color.white,
    borderTopLeftRadius: 23, borderTopRightRadius: 23, gap: 5 },
  sheetOption: { flexDirection: 'row-reverse', justifyContent: 'space-between', alignItems: 'center', minHeight: 48,
    borderBottomColor: color.line, borderBottomWidth: 1, paddingHorizontal: 8 },
  detailImageWrap: { marginTop: 13 },
  detailImage: { aspectRatio: 1.24, borderRadius: 15, backgroundColor: color.soft },
  thumbnails: { marginTop: 9, direction: 'rtl' },
  thumbRow: { gap: 6 },
  thumbnail: { height: 53, width: 53, padding: 2, borderRadius: 10, borderWidth: 1, borderColor: color.line, backgroundColor: color.white },
  thumbnailActive: { borderColor: color.blue, borderWidth: 2 },
  thumbnailPicture: { flex: 1, borderRadius: 7, backgroundColor: color.white },
  detailText: { gap: 9, marginTop: 17, paddingBottom: 13, borderBottomColor: color.line, borderBottomWidth: 1 },
  detailPrice: { paddingVertical: 7, paddingHorizontal: 11, borderRadius: 9,
    alignSelf: 'flex-end', backgroundColor: color.accentSoft },
  group: { marginTop: 17, gap: 8 },
  optionRow: { flexDirection: 'row-reverse', flexWrap: 'wrap', gap: 6 },
  print: { padding: 13, gap: 6, marginTop: 14 },
  description: { marginTop: 15, gap: 7, paddingTop: 13, borderTopColor: color.line, borderTopWidth: 1 },
  descriptionLink: { minHeight: 44, justifyContent: 'center' },
  purchase: { paddingHorizontal: 18, paddingVertical: 10, borderTopColor: color.line, borderTopWidth: 1, backgroundColor: color.white, gap: 6 },
  purchaseRow: { flexDirection: 'row-reverse', alignItems: 'center', gap: 8 },
  stepper: { flexDirection: 'row-reverse', alignItems: 'center', alignSelf: 'flex-end', gap: 3 },
  quantityNumber: { minWidth: 22, textAlign: 'center' },
  cartCount: { marginBottom: 10, marginTop: -7 },
  cartItem: { flexDirection: 'row-reverse', gap: 11, padding: 11, marginBottom: 10, alignItems: 'flex-start' },
  cartPhoto: { width: 79, height: 93, borderRadius: 10 },
  cartInfo: { flex: 1, minWidth: 0, gap: 5 },
  itemHead: { flexDirection: 'row-reverse', alignItems: 'flex-start', gap: 7 },
  cartError: { marginBottom: 10 },
  summary: { padding: 14, gap: 4, marginBottom: 11 },
  total: { backgroundColor: color.accentSoft, borderRadius: 9, paddingHorizontal: 10, marginTop: 7 },
  reviewInfo: { padding: 13, marginBottom: 9, gap: 6 },
  reviewHeading: { flexDirection: 'row-reverse', alignItems: 'center', gap: 7 },
});
