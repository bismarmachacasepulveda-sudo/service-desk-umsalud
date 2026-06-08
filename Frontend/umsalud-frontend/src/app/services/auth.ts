import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject } from 'rxjs'; // 🟢 Importar BehaviorSubject
import { tap } from 'rxjs/operators';
import { Router } from '@angular/router';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = 'http://192.168.1.50:8000/api';
  private http = inject(HttpClient);
  private router = inject(Router);

  // 1. UNIFICAR: Solo usamos currentUserSubject como fuente de la verdad
  private currentUserSubject = new BehaviorSubject<any>(JSON.parse(localStorage.getItem('user') || 'null'));
  public currentUser$ = this.currentUserSubject.asObservable();
public get currentUserValue(): any {
    return this.currentUserSubject.value;
  }
  constructor() { }

  // 2. NUEVO MÉTODO MÁGICO: Obtener valor SIN suscripción (Síncrono)
  get currentUserSnapshot(): any {
    return this.currentUserSubject.value;
  }

  login(credentials: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/login`, credentials).pipe(
      tap(response => {
        if (response.accessToken) {
          localStorage.setItem('token', response.accessToken);
          localStorage.setItem('user', JSON.stringify(response.user));
          this.currentUserSubject.next(response.user);
        }
      })
    );
  }

  logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    this.currentUserSubject.next(null); 
    this.router.navigate(['/login']);
  }

  getToken() {
    return localStorage.getItem('token');
  }

  registrarUsuario(datos: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/registrarse`, datos);
  }

  actualizarDatosLocales(nuevoUsuario: any) {
    localStorage.setItem('user', JSON.stringify(nuevoUsuario));
    this.currentUserSubject.next(nuevoUsuario); // Usamos el unificado
  }
}