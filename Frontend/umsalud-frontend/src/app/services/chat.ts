import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ChatService {
  
  private apiBaseUrl = 'http://192.168.1.50:8000/api/tickets';
  private http = inject(HttpClient);

  // Obtener mensajes de un ticket
  getMessages(ticketId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiBaseUrl}/${ticketId}/chat`);
  }

  // Enviar mensaje
// Enviar mensaje con archivo opcional
  sendMessage(ticketId: number, message: string, archivo: File | null): Observable<any> {
    
    const formData = new FormData();
    
    if (message) {
        formData.append('message', message);
    }
    
    if (archivo) {
        // 🟢 El nombre aquí debe coincidir con el del controlador ($request->file('archivo'))
        formData.append('archivo', archivo); 
    }

    return this.http.post<any>(`${this.apiBaseUrl}/${ticketId}/chat`, formData);
  }
  sendPresence(ticketId: number): Observable<any> {
    return this.http.post(`${this.apiBaseUrl}/${ticketId}/presence`, {});
  }
}