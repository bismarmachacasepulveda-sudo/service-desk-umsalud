import { Injectable, inject } from '@angular/core'; //
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class LogsActividadesService {
  private http = inject(HttpClient);
  private url = 'http://192.168.1.50:8000/api/admin/logs-actividades';

  getLogs(page: number = 1, filtros: any = {}): Observable<any> {
    let params = new HttpParams().set('page', page.toString());
    
    // Agregar filtros dinámicamente si existen
    Object.keys(filtros).forEach(key => {
      if (filtros[key]) {
        params = params.set(key, filtros[key]);
      }
    });

    return this.http.get(this.url, { params });
  }
}