import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  
  private apiUrl = 'http://192.168.1.50:8000/api/dashboard/stats';
  private http = inject(HttpClient);

  getStats(): Observable<any> {
    return this.http.get<any>(this.apiUrl);
  }
}