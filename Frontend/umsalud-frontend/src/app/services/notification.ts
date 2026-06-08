import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, interval, switchMap, tap } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class NotificationService {
  
  private apiUrl = 'http://192.168.1.50:8000/api/notifications';
  private http = inject(HttpClient);

  // Fuente de la verdad del contador
  public unreadCount$ = new BehaviorSubject<number>(0);

  constructor() {
    // 1. Carga inicial inmediata
    this.refreshCount();

    // 2. Polling: Actualizar cada 30 segundos
    interval(30000).subscribe(() => {
        this.refreshCount();
    });
  }

  getNotifications(): Observable<any[]> {
    return this.http.get<any[]>(this.apiUrl);
  }

  // Método centralizado para actualizar el contador
  refreshCount() {
    this.http.get<any>(`${this.apiUrl}/unread`).subscribe({
        next: (res) => {
            // Aseguramos que sea un número
            const count = Number(res.count); 
            this.unreadCount$.next(count);
        },
        error: (err) => console.error('Error obteniendo notificaciones', err)
    });
  }

  markAsRead(id: string): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}/read`, {}).pipe(
        tap(() => this.refreshCount()) // Actualizar contador al marcar
    );
  }

  markAllRead(): Observable<any> {
    return this.http.put(`${this.apiUrl}/read-all`, {}).pipe(
        tap(() => this.refreshCount())
    );
  }
}