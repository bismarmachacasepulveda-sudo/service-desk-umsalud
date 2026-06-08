import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ChatbotService {
  
  private apiUrl = 'http://192.168.1.50:8000/api/chatbot/preguntar';
  private http = inject(HttpClient);

  // 🟢 AHORA RECIBIMOS EL HISTORIAL
  enviarPregunta(mensaje: string, historial: any[] = []): Observable<any> {
    return this.http.post<any>(this.apiUrl, { 
        mensaje, 
        historial // Enviamos el array de mensajes anteriores
    });
  }
}
