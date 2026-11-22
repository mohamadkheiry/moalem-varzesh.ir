const { mkdirSync, writeFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { createElement } = require('react');
const { renderToStaticMarkup } = require('react-dom/server');
const { StyleSheet, Text, View } = require('react-native-web');
const color = require('../src/design-tokens.json');
const paths = require('../src/navigation-icons.json');

const styles = StyleSheet.create({
    action: {
        alignItems: 'center', justifyContent: 'center', flexDirection: 'row',
        gap: 10, minHeight: 48, paddingHorizontal: 16, paddingVertical: 8,
        borderRadius: 14, backgroundColor: color.lime, color: color.ink,
    },
    shortcut: { alignItems: 'center', justifyContent: 'center', gap: 3, minHeight: 48, color: 'inherit' },
    actionText: { color: 'inherit', fontFamily: 'inherit', fontSize: 15, fontWeight: '800' },
    smallText: { color: 'inherit', fontFamily: 'inherit', fontSize: 11, fontWeight: '700', lineHeight: 16 },
});

function icon(name, size = 21) {
    return createElement('svg', {
        viewBox: '0 0 24 24', width: size, height: size, fill: 'none', stroke: 'currentColor',
        strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true, focusable: false,
    }, paths[name].map(path => createElement('path', { d: path, key: path })));
}

const labels = { home: 'خانه', shop: 'فروشگاه', cart: 'سبد خرید', account: 'حساب کاربری' };
function shortcut(name, small = false) {
    return createElement('a', {
        key: name, href: `__MV_${name}_href__`, 'aria-label': labels[name],
        'aria-current': `__MV_${name}_current__`,
        className: small ? 'mv-dock-link' : 'icon-link',
    }, small
        ? createElement(View, { style: styles.shortcut }, icon(name), createElement(Text, { style: styles.smallText }, labels[name]))
        : icon(name));
}

const fragments = {
    actions: createElement('div', { className: 'header-actions', 'data-mv-rnw': 'actions' }, shortcut('account'), shortcut('cart')),
    dock: createElement('nav', { className: 'mv-secondary-dock', 'aria-label': 'میانبرهای فروشگاه', 'data-mv-rnw': 'dock' },
        Object.keys(labels).map(name => shortcut(name, true))),
    hero: createElement('a', { className: 'mv-secondary-hero-action', href: '__MV_shop_href__', 'data-mv-rnw': 'hero' },
        createElement(View, { style: styles.action }, createElement(Text, { style: styles.actionText }, 'مشاهدهٔ فروشگاه'), icon('arrow', 19))),
};

const output = resolve(__dirname, '../../theme/assets/secondary');
mkdirSync(output, { recursive: true });
const html = Object.fromEntries(Object.entries(fragments).map(([name, element]) => [name, renderToStaticMarkup(element)]));
writeFileSync(resolve(output, 'components.json'), JSON.stringify(html, null, 2) + '\n');
const variables = Object.entries(color).map(([name, value]) => `--mv-app-${name}:${value};`).join('');
writeFileSync(resolve(output, 'native.css'), `.mv-secondary{${variables}}\n${StyleSheet.getSheet().textContent}\n`);
