import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class CategoryService {
  private apiUrl = 'http://192.168.1.50:8000/api/categories';
  private http = inject(HttpClient);

//'
  getCategories(tipo: 'ticket' | 'repositorio' | null = null): Observable<any[]> {
    let url = this.apiUrl;
    if (tipo) {
      url += `?tipo=${tipo}`;
    }
    return this.http.get<any[]>(url);
  }


  createCategory(data: { name: string, tipo: string }): Observable<any> {
    return this.http.post<any>(this.apiUrl, data);
  }
  
  updateCategory(id: number, data: any): Observable<any> { return this.http.put<any>(`${this.apiUrl}/${id}`, data); }
  
  deleteCategory(id: number): Observable<any> { return this.http.delete<any>(`${this.apiUrl}/${id}`); }

  // PAPELERA

 getTrashedCategories(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/trashed`);
  }

  // 2. Restaurar categoría
  restoreCategory(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/${id}/restore`, {});
  }

  // 3. Eliminar permanentemente (Lo que te daba error)
  deleteCategoryPermanently(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}/force-delete`);
  }

  
}