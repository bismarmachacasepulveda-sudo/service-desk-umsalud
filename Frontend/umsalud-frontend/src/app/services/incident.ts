import { Injectable, inject } from '@angular/core';
// Importamos solo lo necesario
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
// Ya no necesitamos HttpHeaders ni AuthService

@Injectable({
  providedIn: 'root'
})
export class IncidentService {
  private apiUrl = 'http://192.168.1.50:8000/api/incidents';
  private http = inject(HttpClient);
  // private authService = inject(AuthService); <-- BORRAMOS ESTO

  // getHeaders() { ... } <-- BORRAMOS ESTE MÉTODO

  getIncidents(): Observable<any[]> {
    // Ya no pasamos this.getHeaders()
    return this.http.get<any[]>(this.apiUrl);
  }

  createIncident(data: any): Observable<any> {
    // Ya no pasamos this.getHeaders()
    return this.http.post<any>(this.apiUrl, data);
  }
}