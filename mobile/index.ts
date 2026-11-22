import { registerRootComponent } from 'expo';
import App from './src/App';

if (typeof document !== 'undefined') {
  document.documentElement.lang = 'fa-IR';
  document.title = 'معلم ورزش';
}

registerRootComponent(App);
