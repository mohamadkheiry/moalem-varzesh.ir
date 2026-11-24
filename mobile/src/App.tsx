import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, AppState, BackHandler, Linking, Platform, Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { useFonts } from 'expo-font';
import { ArrowRight, Heart, House, ShoppingBag, Store, UserRound } from './icons';
import type { IconComponent } from './icons';
import Svg, { Circle, Path } from 'react-native-svg';
import { addCartItem, changeCartItem, checkoutUrl, fetchCatalog, readCache, readCart, storeOrigin, writeCache } from './api';
import type { Cart } from './api';
import type { CartRequest, Catalog, Product } from './domain';
import { CartScreen, HomeScreen, ProductScreen, ReviewScreen, SavedScreen, ShopScreen } from './screens';
import { color, IconButton, Label, shared } from './ui';

type Tab = 'home' | 'shop' | 'saved' | 'cart';
const tabs: { id: Tab; label: string; icon: IconComponent }[] = [
  { id: 'home', label: 'خانه', icon: House },
  { id: 'shop', label: 'فروشگاه', icon: Store },
  { id: 'saved', label: 'علاقه‌مندی‌ها', icon: Heart },
  { id: 'cart', label: 'سبد خرید', icon: ShoppingBag },
];

function ClubMark({ dark }: { dark: boolean }) {
  return <View style={[styles.mark, dark && styles.markDark]}>
    <Svg width={26} height={26} viewBox="0 0 24 24" fill="none">
      <Path d="M4 17c4.5-7.4 9-10.3 15-10.7M7 20l6.3-5.8h5l-3 4.2M4 10l4.7-3.2 5 .8"
        stroke={color.accent} strokeWidth={1.85} strokeLinecap="round" strokeLinejoin="round" />
      <Circle cx="17" cy="3.7" r="1.5" fill={color.accent} />
    </Svg>
  </View>;
}

function Header({ dark, back, count, onCart, onAccount }: {
  dark: boolean; back?: () => void; count: number; onCart: () => void; onAccount: () => void;
}) {
  return <View style={[styles.header, dark && styles.headerDark]}>
    <View style={styles.brand}>
      {back ? <IconButton icon={ArrowRight} label="بازگشت" testID="back" onPress={back} dark={dark} /> : <ClubMark dark={dark} />}
      <Label size={20} weight="bold" tone={dark ? color.white : color.ink}>معلم ورزش</Label>
    </View>
    <View style={styles.headerActions}>
      <IconButton icon={UserRound} label="حساب کاربری" testID="account" onPress={onAccount} dark={dark} />
      <IconButton icon={ShoppingBag} label="سبد خرید" testID="header-cart" onPress={onCart} badge={count} dark={dark} />
    </View>
  </View>;
}

function Tabs({ tab, onChange }: { tab: Tab; onChange: (tab: Tab) => void }) {
  return <View style={styles.tabs} accessibilityRole="tablist">
    {tabs.map(({ id, label, icon: Icon }) => {
      const selected = tab === id;
      return <Pressable key={id} accessibilityRole="tab" accessibilityState={{ selected }} testID={'tab-' + id}
        onPress={() => onChange(id)} style={({ pressed }) => [styles.tab, selected && styles.tabSelected, pressed && !selected && styles.tabPressed]}>
        <Icon size={22} color={selected ? color.navy : color.muted} strokeWidth={selected ? 2.1 : 1.75} />
        <Label size={11} weight={selected ? 'bold' : 'medium'} tone={selected ? color.navy : color.muted} style={shared.center}>{label}</Label>
      </Pressable>;
    })}
  </View>;
}

function openSite(url: string) {
  if (Platform.OS === 'web') {
    window.location.assign(url);
    return Promise.resolve();
  }
  return Linking.openURL(url);
}

function Storefront() {
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [refreshing, setRefreshing] = useState(true);
  const [offline, setOffline] = useState(false);
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState<number | null>(null);
  const [tab, setTab] = useState<Tab>('home');
  const [item, setItem] = useState<Product | null>(null);
  const [review, setReview] = useState(false);
  const [saved, setSaved] = useState<number[]>([]);
  const [savedReady, setSavedReady] = useState(false);
  const [cart, setCart] = useState<Cart | null>(null);
  const [cartBusy, setCartBusy] = useState(false);
  const [cartError, setCartError] = useState('');
  const [notice, setNotice] = useState('');

  const loading = useRef<AbortController | null>(null);
  const cartLock = useRef(false);
  const refresh = useCallback(async () => {
    loading.current?.abort();
    const control = new AbortController();
    loading.current = control;
    setRefreshing(true);
    try {
      const data = await fetchCatalog(control.signal);
      if (control.signal.aborted) return;
      setCatalog(data);
      setOffline(false);
      void writeCache('catalog', data);
    } catch {
      if (control.signal.aborted) return;
      const past = await readCache<Catalog>('catalog');
      if (control.signal.aborted) return;
      if (past && Array.isArray(past.products) && Array.isArray(past.categories)) {
        setCatalog(past);
        setOffline(true);
      }
    } finally {
      if (!control.signal.aborted) setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
    return () => loading.current?.abort();
  }, [refresh]);

  useEffect(() => {
    let active = true;
    void readCache<number[]>('favorites').then(value => {
      if (!active) return;
      if (Array.isArray(value)) setSaved(value.filter(id => Number.isInteger(id)));
      setSavedReady(true);
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (savedReady) void writeCache('favorites', saved);
  }, [saved, savedReady]);

  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(''), 3200);
    return () => clearTimeout(timer);
  }, [notice]);

  const updateCart = useCallback(async (operation: () => Promise<Cart>, notifyError = true) => {
    if (cartLock.current) throw new Error('به‌روزرسانی سبد در حال انجام است.');
    cartLock.current = true;
    setCartBusy(true);
    setCartError('');
    try {
      const next = await operation();
      setCart(next);
      return next;
    } catch (error) {
      if (notifyError) setCartError(error instanceof Error ? error.message : 'سبد خرید به‌روزرسانی نشد.');
      throw error;
    } finally {
      cartLock.current = false;
      setCartBusy(false);
    }
  }, []);

  const refreshCart = useCallback(() => {
    if (!cartLock.current) void updateCart(readCart).catch(() => {});
  }, [updateCart]);

  useEffect(() => {
    refreshCart();
    const subscription = AppState.addEventListener('change', state => {
      if (state === 'active') refreshCart();
    });
    return () => subscription.remove();
  }, [refreshCart]);

  const changeTab = useCallback((next: Tab) => {
    setItem(null);
    setReview(false);
    setTab(next);
    setNotice('');
    if (next === 'cart') refreshCart();
  }, [refreshCart]);

  useEffect(() => {
    if (Platform.OS !== 'android') return;
    const handler = BackHandler.addEventListener('hardwareBackPress', () => {
      if (review) { setReview(false); return true; }
      if (item) { setItem(null); return true; }
      if (tab !== 'home') { changeTab('home'); return true; }
      return false;
    });
    return () => handler.remove();
  }, [review, item, tab, changeTab]);

  const toggle = (id: number) => {
    if (savedReady) setSaved(old => old.includes(id) ? old.filter(value => value !== id) : [...old, id]);
  };
  const browse = { onOpen: setItem, saved, toggle };
  const goShop = () => { setQuery(''); setCategory(null); changeTab('shop'); };
  const product = !!item;
  const reviewing = review && !!cart?.items.length;
  const dark = reviewing || product || tab !== 'home';
  const back = reviewing ? () => setReview(false) : product ? () => setItem(null) : undefined;
  const onCheckout = async () => {
    if (cartLock.current) return;
    cartLock.current = true;
    setCartBusy(true);
    setCartError('');
    try {
      const target = await checkoutUrl();
      await openSite(target);
    } catch (error) {
      setCartError(error instanceof Error ? error.message : 'تسویه‌حساب باز نشد.');
    } finally {
      cartLock.current = false;
      setCartBusy(false);
    }
  };

  return <View style={styles.stage}>
    <SafeAreaView style={[styles.shell, dark && styles.headerDark]} edges={['top']}>
      <StatusBar style={dark ? 'light' : 'dark'} />
      <Header dark={dark} back={back} count={cart?.items_count ?? 0} onCart={() => changeTab('cart')}
        onAccount={() => { void openSite(storeOrigin + '/my-account/').catch(() => setNotice('حساب کاربری باز نشد.')); }} />
      <SafeAreaView style={styles.body} edges={['bottom']}>
        {offline && <Pressable style={styles.offline} accessibilityRole="button" onPress={() => void refresh()}>
          <Label tone={color.blue} size={12}>در حال نمایش فهرست ذخیره‌شده · تلاش دوباره</Label>
        </Pressable>}
        {reviewing && cart ? <ReviewScreen cart={cart} busy={cartBusy} error={cartError} onCheckout={() => void onCheckout()} />
          : item ? <ProductScreen key={item.id} item={item} saved={saved} toggle={toggle} busy={cartBusy}
            onAdd={async (request: CartRequest) => {
              await updateCart(() => addCartItem(request), false);
              setNotice('کالا به سبد خرید اضافه شد.');
            }} />
          : tab === 'home' ? <HomeScreen catalog={catalog} query={query} setQuery={setQuery} onShop={goShop}
            submitSearch={() => { setCategory(null); changeTab('shop'); }}
            onCategory={selected => { setQuery(''); setCategory(selected.id); changeTab('shop'); }}
            refreshing={refreshing} refresh={() => void refresh()} {...browse} />
          : tab === 'shop' ? <ShopScreen catalog={catalog} query={query} setQuery={setQuery} category={category} setCategory={setCategory}
            refreshing={refreshing} refresh={() => void refresh()} {...browse} />
          : tab === 'saved' ? <SavedScreen catalog={catalog} onShop={goShop} {...browse} />
          : <CartScreen cart={cart} busy={cartBusy} error={cartError} onShop={goShop} refresh={refreshCart}
            onReview={() => setReview(true)} onChange={(key, quantity) => { void updateCart(() => changeCartItem(key, quantity)).catch(() => {}); }} />}
        {!product && !reviewing && <Tabs tab={tab} onChange={changeTab} />}
        {!!notice && (product || tab !== 'cart') && <View pointerEvents="none" style={styles.notice}>
          <Label accessibilityLiveRegion="polite" size={13} weight="medium" tone={color.white} style={shared.center}>{notice}</Label>
        </View>}
      </SafeAreaView>
    </SafeAreaView>
  </View>;
}

export default function App() {
  const [loaded, error] = useFonts({
    Vazir: require('../assets/Vazirmatn-Regular.ttf'),
    VazirMedium: require('../assets/Vazirmatn-SemiBold.ttf'),
    VazirBold: require('../assets/Vazirmatn-ExtraBold.ttf'),
  });
  if (!loaded && !error) return <View style={styles.launch}><ActivityIndicator color={color.blue} /></View>;
  return <SafeAreaProvider><Storefront /></SafeAreaProvider>;
}

const styles = StyleSheet.create({
  stage: { flex: 1, alignItems: 'center', backgroundColor: '#E4EAF2', direction: 'ltr' },
  shell: { flex: 1, width: '100%', maxWidth: 560, backgroundColor: color.white },
  body: { flex: 1, backgroundColor: color.paper },
  header: { minHeight: 70, paddingHorizontal: 18, paddingVertical: 8, gap: 8, borderBottomWidth: 1,
    borderBottomColor: color.line, flexDirection: 'row-reverse', alignItems: 'center',
    justifyContent: 'space-between', backgroundColor: color.white },
  headerDark: { backgroundColor: color.navy },
  brand: { flexDirection: 'row-reverse', alignItems: 'center', gap: 8 },
  headerActions: { flexDirection: 'row-reverse', alignItems: 'center', gap: 5 },
  mark: { width: 43, height: 43, alignItems: 'center', justifyContent: 'center', backgroundColor: color.navy, borderRadius: 11 },
  markDark: { backgroundColor: color.blue },
  tabs: { minHeight: 66, paddingHorizontal: 10, paddingVertical: 5, borderTopColor: color.line,
    borderTopWidth: 1, flexDirection: 'row-reverse', gap: 4, backgroundColor: color.white },
  tab: { minHeight: 53, borderRadius: 11, alignItems: 'center', justifyContent: 'center', flex: 1, minWidth: 0, gap: 3 },
  tabSelected: { backgroundColor: color.accentSoft },
  tabPressed: { backgroundColor: color.soft },
  offline: { paddingHorizontal: 16, paddingVertical: 8, backgroundColor: color.accentSoft },
  notice: { position: 'absolute', left: 16, right: 16, bottom: 71, padding: 10, borderRadius: 13, backgroundColor: color.navy },
  launch: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: color.paper },
});
