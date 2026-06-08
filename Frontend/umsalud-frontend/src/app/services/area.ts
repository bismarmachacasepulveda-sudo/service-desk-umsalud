import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
// import { environment } from 'src/environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AreaService {
  // Ajusta a tu IP o environment
  private apiUrl = 'http://192.168.1.50:8000/api/areas';
  private http = inject(HttpClient);

  // 1. LISTAR
  getAreas(): Observable<any[]> {
    return this.http.get<any[]>(this.apiUrl);
  }

  // 2. CREAR (Ahora soporta descripción)
  createArea(areaData: { name: string, description?: string }): Observable<any> {
    return this.http.post<any>(this.apiUrl, areaData);
  }

  // 3. ACTUALIZAR (Soporta descripción y active)
  updateArea(id: number, areaData: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/${id}`, areaData);
  }

  // 4. ELIMINAR (Soft Delete)
  deleteArea(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}`);
  }

  // --- MÉTODOS DE PAPELERA (NUEVOS) ---

  getTrashedAreas(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/trashed`);
  }

  restoreArea(id: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/${id}/restore`, {});
  }

  deleteAreaPermanently(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}/force-delete`);
  }
}