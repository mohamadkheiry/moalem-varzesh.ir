import type { ComponentType } from 'react';
import Svg, { Path } from 'react-native-svg';
import navigationIcons from './navigation-icons.json';

type IconProps = { size?: number; color?: string; strokeWidth?: number; fill?: string };
export type IconComponent = ComponentType<IconProps>;

function outline(...paths: string[]): IconComponent {
  return function StoreIcon({ size = 24, color = '#142821', strokeWidth = 1.8, fill = 'none' }: IconProps) {
    return <Svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke={color}
      strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round">
      {paths.map(path => <Path key={path} d={path} />)}
    </Svg>;
  };
}

export const ArrowLeft = outline(...navigationIcons.arrow);
export const ArrowRight = outline('M5 12h14m-6 6 6-6-6-6');
export const Check = outline('m5 12 4 4L19 6');
export const ChevronDown = outline('m6 9 6 6 6-6');
export const Heart = outline('M20.8 4.8a5.35 5.35 0 0 0-7.6 0L12 6l-1.2-1.2a5.35 5.35 0 0 0-7.6 7.6l1.2 1.2L12 21l7.6-7.4 1.2-1.2a5.35 5.35 0 0 0 0-7.6Z');
export const House = outline(...navigationIcons.home);
export const ImageOff = outline('M3 3l18 18M8 3h11a2 2 0 0 1 2 2v11M3 7v12a2 2 0 0 0 2 2h12', 'm3 17 4-4 4 4m3-5 1-1 6 6');
export const Minus = outline('M5 12h14');
export const Plus = outline('M12 5v14M5 12h14');
export const Search = outline('M17.8 11a6.8 6.8 0 1 1-13.6 0 6.8 6.8 0 0 1 13.6 0Z', 'm16 16 4.2 4.2');
export const Shirt = outline('M8 3H6L2 7l3 4 2-1v11h10V10l2 1 3-4-4-4h-2a4 4 0 0 1-8 0Z');
export const ShoppingBag = outline(...navigationIcons.cart);
export const SlidersHorizontal = outline('M3 6h4m4 0h10M7 4v4M3 12h8m4 0h6m-6-2v4M3 18h3m4 0h11m-11-2v4');
export const Store = outline(...navigationIcons.shop);
export const Trash2 = outline('M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7m4 4v6m4-6v6');
export const Truck = outline('M2 6h12v11H2Zm12 5h4l4 4v2h-8', 'M8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm12 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z');
export const CreditCard = outline('M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm-2 5h20m-16 5h4');
export const UserRound = outline(...navigationIcons.account);
export const X = outline('m6 6 12 12M18 6 6 18');
