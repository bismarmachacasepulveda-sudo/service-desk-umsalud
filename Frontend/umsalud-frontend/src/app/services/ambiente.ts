import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AmbienteService {
  
  private apiUrl = 'http://192.168.1.50:8000/api/ambientes';
  private http = inject(HttpClient);

  // 1. LISTAR (Todos pueden ver)
  getAmbientes(): Observable<any[]> {
    return this.http.get<any[]>(this.apiUrl);
  }

  // 2. CREAR (Solo Admin)
  createAmbiente(data: any): Observable<any> {
    return this.http.post<any>(this.apiUrl, data);
  }

  // 3. EDITAR (Solo Admin)
  updateAmbiente(id: number, data: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/${id}`, data);
  }

  // 4. ELIMINAR (Solo Admin)
  deleteAmbiente(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}`);
  }

  getTrashedAmbientes(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/trashed`);
  }

  restoreAmbiente(id: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/${id}/restore`, {});
  }

  deleteAmbientePermanently(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}/force-delete`);
  }
}

