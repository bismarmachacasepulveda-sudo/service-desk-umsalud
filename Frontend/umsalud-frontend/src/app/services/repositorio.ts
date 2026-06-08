import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
// import { environment } from 'src/environments/environment'; // Recomendado

@Injectable({
  providedIn: 'root'
})
export class RepositorioService {
  
  // Ajusta esto a tu IP real o usa environment
  private apiUrl = 'http://192.168.1.50:8000/api/repositorio'; 
  private http = inject(HttpClient);

  obtenerArchivos(categoryId?: number): Observable<any[]> {
    let url = this.apiUrl;
    if (categoryId) url += `?category_id=${categoryId}`;
    return this.http.get<any[]>(url);
  }

  subirArchivo(archivo: File, descripcion: string, categoryId: number, visibilidad: string): Observable<any> {
    const formData = new FormData();
    formData.append('archivo', archivo);
    formData.append('descripcion', descripcion || '');
    formData.append('category_id', categoryId.toString());
    formData.append('visibilidad', visibilidad);

    return this.http.post<any>(this.apiUrl, formData);
  }

  // 🟢 NUEVO: Descarga segura (Blob) para archivos privados
  descargarArchivo(id: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${id}/download`, {
      responseType: 'blob'
    });
  }

  eliminarArchivo(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}`);
  }
  // OBTENER BORRADOS
  getTrashedFiles(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/trashed`);
  }

  // RESTAURAR
  restoreFile(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/${id}/restore`, {});
  }

  // ELIMINAR PERMANENTE
  permanentDeleteFile(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}/force-delete`);
  }
}