import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class TicketService {
  
  // ⚠️ AJUSTA LA URL BASE SEGÚN TU CONFIGURACIÓN DE LARAVEL
  private apiUrl = 'http://192.168.1.50:8000/api/tickets'; 
  private http = inject(HttpClient);

  // 1. Obtener todos los tickets (para la tabla principal)
  getTickets(): Observable<any[]> {
    return this.http.get<any[]>(this.apiUrl);
  }

// 2. Crear un nuevo ticket (AHORA CON ARCHIVO)
  // Recibimos un objeto que puede tener una propiedad 'archivo' de tipo File
  createTicket(ticketData: any, archivo: File | null = null): Observable<any> {
    const formData = new FormData();
    // Agregamos los campos de texto uno por uno
    formData.append('area_id', ticketData.area_id);
    formData.append('user_id', ticketData.user_id);
    formData.append('subject', ticketData.subject);
    formData.append('description', ticketData.description);
  formData.append('impacto', ticketData.impacto);
    formData.append('urgencia', ticketData.urgencia);

    // Solo si hay técnico asignado (puede ser null)
    if (ticketData.assigned_to) {
        formData.append('assigned_to', ticketData.assigned_to);
    }

    // 🟢 AGREGAMOS EL ARCHIVO SI EXISTE
    if (archivo) {
        formData.append('archivo', archivo);
    }

    return this.http.post<any>(this.apiUrl, formData);
  }
  
  // 3. Obtener un ticket por ID (para la vista de detalle, futuro)
  getTicket(id: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/${id}`);
  }
  
  // 4. Cerrar o actualizar estado del ticket (futuro)
// En tu archivo del servicio de tickets
updateTicket(id: number, updateData: any, action?: string): Observable<any> {
  let url = `${this.apiUrl}/${id}`;
  // Si enviamos una acción (take o release), la añadimos como Query Param
  if (action) {
    url += `?action=${action}`;
  }

  return this.http.put<any>(url, updateData);
}
  
  // 5. Eliminar ticket (futuro)
  deleteTicket(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/${id}`);
  }
  takeTicket(id: number): Observable<any> {
    // Enviamos el parámetro 'action=take' en la query string
    return this.http.put<any>(`${this.apiUrl}/${id}?action=take`, {});
  }

  // 1. Obtener solo los tickets en la papelera
getTrashedTickets(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/trashed`);
}

// 2. Restaurar un ticket
restoreTicket(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/${id}/restore`, {});
}

// 3. Eliminación permanente
permanentDeleteTicket(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}/force-delete`);
}
}