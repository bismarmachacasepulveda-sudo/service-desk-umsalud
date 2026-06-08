import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
// 🟢 CORRECCIÓN: Importar AppComponent
import { AppComponent } from './app/app'; 

// 🟢 CORRECCIÓN: Arrancar AppComponent
bootstrapApplication(AppComponent, appConfig)
  .catch((err) => console.error(err));