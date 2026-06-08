import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core'; // Añadimos ChangeDetectorRef
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AsistenteVirtualComponent } from './components/asistente-virtual/asistente-virtual'; 
import { NavbarComponent } from './components/navbar/navbar';
import { AuthService } from './services/auth';
import { Router, NavigationEnd } from '@angular/router';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, CommonModule, AsistenteVirtualComponent, NavbarComponent,RouterLink,RouterLinkActive], 
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class AppComponent implements OnInit {
  user: any = null;
  MostrarLayout = false;
  public mostrarLayout: boolean = true;
  private authService = inject(AuthService);
  private cdr = inject(ChangeDetectorRef); // Inyectamos el detector
  private router = inject(Router);

ngOnInit() {
    // 1. Recuperar usuario del storage
    const savedUser = localStorage.getItem('user');
    if (savedUser) {
      this.user = JSON.parse(savedUser);
    }

    // 2. Escuchar cambios de usuario (Login/Logout)
    this.authService.currentUser$.subscribe(userData => {
      this.user = userData;
      this.validarVisibilidadLayout(); // Validar cuando el usuario cambie
      this.cdr.detectChanges(); 
    });

    // 3. 🟢 LA CLAVE: Escuchar cada vez que cambie la URL
    this.router.events.subscribe(event => {
      if (event instanceof NavigationEnd) {
        this.validarVisibilidadLayout();
      }
    });
  }
  validarVisibilidadLayout() {
    const rutasPublicas = ['/login', '/registro'];
    const urlActual = this.router.url;

    // Solo mostramos el layout si hay usuario Y no estamos en una ruta pública
    this.MostrarLayout = !!this.user && !rutasPublicas.includes(urlActual);
    this.cdr.detectChanges();
  }
}