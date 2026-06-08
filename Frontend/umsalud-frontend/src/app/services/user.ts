import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class UserService {
  
  private apiUrl = 'http://192.168.1.50:8000/api'; 
  private http = inject(HttpClient);

  // --- MÉTODOS DE ROL Y AUTENTICACIÓN ---
  
  getCurrentUser(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/user`); 
  }
  
  // --- MÉTODOS DE GESTIÓN DE USUARIOS (ACTIVOS e INACTIVOS) ---

  getUsers(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/users`);
  }

  createUser(userData: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/users`, userData);
  }

  updateUser(id: number, userData: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/users/${id}`, userData);
  }

  deleteUser(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/users/${id}`);
  }

// --- MÉTODOS DE PAPELERA (CORREGIDOS) ---

getTrashedUsers(): Observable<any[]> {
  // Antes: `${this.apiUrl}/trashed/all`
  return this.http.get<any[]>(`${this.apiUrl}/users/trashed/all`);
}

restoreUser(id: number): Observable<any> {
  // Antes: `${this.apiUrl}/${id}/restore`
  return this.http.post<any>(`${this.apiUrl}/users/${id}/restore`, {});
}

deleteUserPermanently(id: number): Observable<any> {
  // Antes: `${this.apiUrl}/${id}/force-delete`
  return this.http.delete<any>(`${this.apiUrl}/users/${id}/force-delete`);
}

  // --- MÉTODOS PARA SOLICITUDES DE REGISTRO (PENDIENTES) ---

  // Obtiene datos de la tabla 'solicitudes_registro'
  getPendientes(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/users/pendientes`);
  }

  // Aprrobar una solicitud a la tabla 'users'
  aprobarUsuario(id: number): Observable<any> {
    // Usamos POST ya que el backend crea un nuevo recurso (User) y actualiza la solicitud
    return this.http.post(`${this.apiUrl}/users/${id}/aprobar`, {});
  }

  // Cambia el estado de la solicitud a 'rechazado' enviando un motivo
  rechazarUsuario(id: number, motivo?: string): Observable<any> {
    // .post() porque ahora enviamos un cuerpo (motivo)
    return this.http.post(`${this.apiUrl}/users/${id}/rechazar`, { motivo });
  }

  // --- CONFIGURACIONES DE USUARIO ---

  toggleWhatsApp(active: boolean): Observable<any> {
    return this.http.post(`${this.apiUrl}/user/toggle-whatsapp`, { active });
  }

  getHistorialSolicitudes(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/users/solicitudes-historial`);
}

/**
 * Obtiene los datos del perfil del usuario autenticado.
 */
getPerfil(): Observable<any> {
  return this.http.get<any>(`${this.apiUrl}/profile`);
}

/**
 * Actualiza la información básica (Nombre, Email, Teléfono, Cargo).
 */
updatePerfil(data: any): Observable<any> {
  return this.http.put<any>(`${this.apiUrl}/profile`, data);
}

/**
 * Actualiza la contraseña enviando la actual y la nueva confirmada.
 */
updatePassword(data: any): Observable<any> {
  return this.http.put<any>(`${this.apiUrl}/profile/password`, data);
}
}