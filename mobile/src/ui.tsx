import type { ReactNode } from 'react';
import { ActivityIndicator, Image, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import type { ImageSourcePropType, StyleProp, TextProps, ViewStyle } from 'react-native';
import { ArrowLeft, Check, ImageOff, Search, ShoppingBag, Shirt, X } from './icons';
import type { IconComponent } from './icons';
import color from './design-tokens.json';
export { color };

export const font = { regular: 'Vazir', medium: 'VazirMedium', bold: 'VazirBold' };

type LabelProps = TextProps & { tone?: string; weight?: keyof typeof font; size?: number };
export function Label({ tone = color.ink, weight = 'regular', size = 14, style, ...props }: LabelProps) {
  return <Text accessibilityLanguage="fa-IR" {...props} style={[styles.text, { color: tone, fontFamily: font[weight], fontSize: size, lineHeight: Math.ceil(size * 1.55) }, style]} />;
}

export function IconButton({ icon: Icon, label, onPress, dark = false, selected = false, testID, disabled = false, badge, compact = false }: {
  icon: IconComponent; label: string; onPress: () => void; dark?: boolean; selected?: boolean; testID?: string;
  disabled?: boolean; badge?: number; compact?: boolean;
}) {
  return <Pressable accessibilityRole="button" accessibilityLabel={label}
    accessibilityState={{ disabled, ...(selected ? { selected } : {}) }}
    onPress={onPress} testID={testID} disabled={disabled}
    style={({ pressed }) => [styles.iconButton, compact && styles.iconCompact, dark && styles.iconDark,
      (pressed || selected) && (dark ? styles.iconDarkPressed : styles.iconPressed), disabled && styles.disabled]}>
    <Icon size={compact ? 18 : 21} strokeWidth={1.85} color={dark ? color.white : color.ink} fill={selected ? color.lime : 'none'} />
    {!!badge && <View pointerEvents="none" style={styles.badge}>
      <Label size={10} weight="bold" style={styles.center}>{new Intl.NumberFormat('fa-IR').format(badge)}</Label>
    </View>}
  </Pressable>;
}

export function Action({ title, onPress, icon: Icon = ArrowLeft, busy = false, disabled = false, style, testID }: {
  title: string; onPress: () => void; icon?: IconComponent; busy?: boolean; disabled?: boolean; style?: StyleProp<ViewStyle>; testID?: string;
}) {
  return <Pressable accessibilityRole="button" accessibilityLabel={title}
    accessibilityState={{ disabled: busy || disabled, busy }}
    onPress={onPress} disabled={busy || disabled} testID={testID}
    style={({ pressed }) => [styles.action, style, (busy || disabled) && styles.actionDisabled, pressed && styles.actionPressed]}>
    {busy ? <ActivityIndicator color={color.forest} /> : <>
      <Label weight="bold" size={14} style={styles.center}>{title}</Label>
      <Icon color={color.ink} size={18} strokeWidth={2} />
    </>}
  </Pressable>;
}

export function SearchField({ value, onChange, onSubmit, testID = 'search-input' }: {
  value: string; onChange: (value: string) => void; onSubmit: () => void; testID?: string;
}) {
  return <View style={styles.search}>
    <Pressable accessibilityRole="button" accessibilityLabel="جست‌وجو" onPress={onSubmit} style={styles.iconPlain} testID="search-submit">
      <Search size={19} color={color.ink} strokeWidth={1.8} />
    </Pressable>
    <TextInput accessibilityLabel="جست‌وجوی کالا" testID={testID} value={value} onChangeText={onChange}
      placeholder="جست‌وجوی کالا" placeholderTextColor={color.muted} returnKeyType="search"
      onSubmitEditing={onSubmit} autoCorrect={false} autoCapitalize="none" style={styles.searchInput} />
    {value.length > 0 && <Pressable accessibilityRole="button" accessibilityLabel="پاک‌کردن جست‌وجو"
      onPress={() => onChange('')} style={styles.iconPlain}><X size={18} color={color.muted} /></Pressable>}
  </View>;
}

export function SectionTitle({ title, link, onPress }: { title: string; link?: string; onPress?: () => void }) {
  return <View style={styles.sectionTitle}>
    <Label accessibilityRole="header" weight="bold" size={20}>{title}</Label>
    {link && onPress && <Pressable accessibilityRole="button" onPress={onPress} style={styles.sectionLink}>
      <Label size={12} tone={color.green} weight="medium">{link}</Label>
      <ArrowLeft size={14} color={color.green} strokeWidth={1.9} />
    </Pressable>}
  </View>;
}

export function Picture({ source, label, fit = 'contain', style, size = 28 }: {
  source?: ImageSourcePropType; label?: string; fit?: 'contain' | 'cover'; style?: StyleProp<ViewStyle>; size?: number;
}) {
  return <View style={[styles.picture, style]}>
    <Shirt size={size} color={color.green} strokeWidth={1.45} />
    {source && <Image source={source} accessibilityLabel={label} accessible={!!label} resizeMode={fit} style={styles.pictureImage} />}
  </View>;
}

export function StatePanel({ title, detail, action, onPress, loading = false, kind = 'plain' }: {
  title: string; detail?: string; action?: string; onPress?: () => void; loading?: boolean; kind?: 'plain' | 'cart' | 'success';
}) {
  const Icon = kind === 'cart' ? ShoppingBag : kind === 'success' ? Check : ImageOff;
  return <View style={styles.state}>
    {loading ? <ActivityIndicator color={color.green} /> : <View style={styles.stateIcon}><Icon size={25} strokeWidth={1.6} color={color.green} /></View>}
    <Label size={17} weight="bold" style={styles.center}>{title}</Label>
    {!!detail && <Label size={13} tone={color.muted} style={styles.center}>{detail}</Label>}
    {action && onPress && <Action title={action} onPress={onPress} style={styles.stateAction} />}
  </View>;
}

export function Chip({ label, selected, onPress, testID }: { label: string; selected: boolean; onPress: () => void; testID?: string }) {
  return <Pressable accessibilityRole="button" accessibilityState={{ selected }} onPress={onPress} testID={testID}
    style={({ pressed }) => [styles.chip, selected && styles.chipSelected, pressed && !selected && styles.iconPressed]}>
    <Label weight="medium" tone={selected ? color.white : color.ink} size={13}>{label}</Label>
  </Pressable>;
}

export function Line({ label, children, strong = false }: { label: string; children: ReactNode; strong?: boolean }) {
  return <View style={styles.line}>
    <Label weight={strong ? 'bold' : 'medium'} size={strong ? 15 : 13}>{label}</Label>
    <View style={styles.lineValue}>{children}</View>
  </View>;
}

export const shared = StyleSheet.create({
  rtlRow: { flexDirection: 'row-reverse', alignItems: 'center' },
  center: { textAlign: 'center' },
  content: { paddingHorizontal: 16, paddingBottom: 22 },
  card: { backgroundColor: color.white, borderWidth: 1, borderColor: color.line, borderRadius: 17 },
  input: {
    minHeight: 46, borderWidth: 1, borderColor: color.line, backgroundColor: color.white,
    borderRadius: 11, fontFamily: font.regular, color: color.ink,
    fontSize: 14, textAlign: 'right', writingDirection: 'rtl', paddingHorizontal: 12,
  },
});

const styles = StyleSheet.create({
  text: { textAlign: 'right', writingDirection: 'rtl', includeFontPadding: false },
  center: { textAlign: 'center' },
  disabled: { opacity: 0.42 },
  iconButton: { width: 46, height: 46, alignItems: 'center', justifyContent: 'center', borderRadius: 13,
    backgroundColor: color.white, borderWidth: 1, borderColor: color.line },
  iconCompact: { width: 44, height: 44, borderRadius: 12 },
  iconDark: { backgroundColor: '#20382E', borderColor: '#3A5548' },
  iconPressed: { backgroundColor: color.soft },
  iconDarkPressed: { backgroundColor: '#3A5548' },
  iconPlain: { minWidth: 44, minHeight: 46, alignItems: 'center', justifyContent: 'center' },
  badge: { position: 'absolute', top: 2, right: 2, minWidth: 16, height: 16, borderRadius: 8,
    paddingHorizontal: 2, backgroundColor: color.lime, justifyContent: 'center' },
  action: { minHeight: 48, paddingHorizontal: 13, backgroundColor: color.lime, borderRadius: 12, flexDirection: 'row-reverse',
    alignItems: 'center', justifyContent: 'center', gap: 8 },
  actionDisabled: { backgroundColor: color.soft, opacity: 0.68 },
  actionPressed: { backgroundColor: '#E8FF96' },
  search: { minHeight: 48, alignItems: 'center', flexDirection: 'row-reverse', backgroundColor: color.white,
    borderColor: color.line, borderWidth: 1, borderRadius: 14 },
  searchInput: { flex: 1, minWidth: 0, minHeight: 46, fontFamily: font.regular, fontSize: 15, color: color.ink,
    textAlign: 'right', writingDirection: 'rtl' },
  sectionTitle: { flexDirection: 'row-reverse', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginBottom: 10 },
  sectionLink: { flexDirection: 'row-reverse', alignItems: 'center', minHeight: 44, gap: 4 },
  picture: { overflow: 'hidden', backgroundColor: color.soft, alignItems: 'center', justifyContent: 'center' },
  pictureImage: { position: 'absolute', top: 0, left: 0, width: '100%', height: '100%' },
  state: { alignItems: 'center', justifyContent: 'center', gap: 8, minHeight: 230, padding: 24, borderRadius: 17,
    borderColor: color.line, borderWidth: 1, backgroundColor: color.white },
  stateIcon: { width: 48, height: 48, borderRadius: 16, backgroundColor: color.soft, alignItems: 'center', justifyContent: 'center' },
  stateAction: { marginTop: 6, alignSelf: 'center' },
  chip: { paddingHorizontal: 13, paddingVertical: 7, minHeight: 44, borderRadius: 12, borderWidth: 1,
    borderColor: color.line, backgroundColor: color.white, justifyContent: 'center' },
  chipSelected: { backgroundColor: color.forest, borderColor: color.forest },
  line: { flexDirection: 'row-reverse', gap: 10, alignItems: 'center', justifyContent: 'space-between', minHeight: 39 },
  lineValue: { flexShrink: 1 },
});
