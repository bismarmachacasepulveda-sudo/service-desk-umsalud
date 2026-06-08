import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ReservaService {
  
  private apiUrl = 'http://192.168.1.50:8000/api/reservas';
  private http = inject(HttpClient);

  // 1. Obtener todas las reservas (para pintar el calendario)
  getReservas(): Observable<any[]> {
    return this.http.get<any[]>(this.apiUrl);
  }

  // 2. Crear nueva reserva
  createReserva(data: any): Observable<any> {
    return this.http.post<any>(this.apiUrl, data);
  }

 // 🟢 En el backend, esto cancela si es usuario o borra a papelera si es admin
  deleteReserva(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}`);
  }

  // 🟢 Ajustado para recibir el objeto completo (estado + motivo_rechazo)
  cambiarEstado(id: number, data: { estado: string, motivo_rechazo?: string }): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/${id}`, data);
  }

  // --- PAPELERA ---
  getTrashedReservas(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/trashed`);
  }

  restoreReserva(id: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/${id}/restore`, {});
  }

  deletePermanently(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}/force-delete`);
  }
}