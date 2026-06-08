import { ApplicationConfig, LOCALE_ID } from '@angular/core'; // 🟢 Importar LOCALE_ID
import { provideRouter } from '@angular/router';
import { routes } from './app.routes';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { tokenInterceptor } from './interceptors/token-interceptor';
import { provideCharts, withDefaultRegisterables } from 'ng2-charts';

// 🟢 IMPORTAR IDIOMA ESPAÑOL
import { registerLocaleData } from '@angular/common';
import localeEs from '@angular/common/locales/es';

registerLocaleData(localeEs); // 🟢 Registrar

export const appConfig: ApplicationConfig = {
  providers: [
    provideRouter(routes),
    provideHttpClient(withInterceptors([tokenInterceptor])),
    provideCharts(withDefaultRegisterables()),

    { provide: LOCALE_ID, useValue: 'es' } 
  ]
};