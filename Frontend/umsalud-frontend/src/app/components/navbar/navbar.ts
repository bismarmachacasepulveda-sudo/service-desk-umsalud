import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth';
import { UserService } from '../../services/user'; 
import { NotificationComponent } from '../../components/notification/notification';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [CommonModule, RouterLink, NotificationComponent],
  templateUrl: './navbar.html',
  styleUrl: './navbar.css'
})
export class NavbarComponent implements OnInit {
  // --- VARIABLES ---
  user: any = null;
  whatsappActive: boolean = false;
  menuAbierto = false;

  // --- INYECCIONES ---
  private authService = inject(AuthService);
  private userService = inject(UserService);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  ngOnInit() {
    // Cargamos el usuario desde localStorage para tener respuesta inmediata
    const userString = localStorage.getItem('user');
    if (userString) {
      this.user = JSON.parse(userString);
      this.whatsappActive = Boolean(this.user.whatsapp_active);
      this.cdr.detectChanges();
    }
  }

  // --- LÓGICA COPIADA DEL DASHBOARD ---

  cambiarPreferencia(event: any) {
    const nuevoEstado = event.target.checked;
    
    // 1. Optimismo: Cambio visual inmediato
    this.whatsappActive = nuevoEstado;
    this.user.whatsapp_active = nuevoEstado; 
    this.cdr.detectChanges(); 

    // 2. Petición al servidor
    this.userService.toggleWhatsApp(nuevoEstado).subscribe({
      next: (res: any) => {
        localStorage.setItem('user', JSON.stringify(this.user));
        console.log('Preferencia guardada:', res.message);
      },
      error: (err) => {
        console.error('Error al guardar preferencia', err);
        // 3. Rollback: Si falla, volvemos atrás
        this.whatsappActive = !nuevoEstado;
        this.user.whatsapp_active = !nuevoEstado;
        event.target.checked = !nuevoEstado; 
        this.cdr.detectChanges();
        alert('Hubo un error de conexión, intenta de nuevo.');
      }
    });
  }

  logout() {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  toggleMenu() {
    this.menuAbierto = !this.menuAbierto;
  }
}