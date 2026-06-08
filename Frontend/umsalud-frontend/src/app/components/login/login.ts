import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth'; // Fíjate que importamos desde 'auth'
import { Router, RouterLink } from '@angular/router';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './login.html',
  styleUrl: './login.css'
})
export class LoginComponent {
  // variables de formulario
  email = '';
  password = '';
  errorMessage = '';

  private authService = inject(AuthService);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  /** Maneja el evento de inicio de sesión */
  onLogin() {
    const credentials = { email: this.email, password: this.password };// Creamos un objeto con las credenciales
    // Llamamos al servicio de autenticación
    this.authService.login(credentials).subscribe({
      next: (response) => {
        console.log('Login exitoso:', response);
        this.router.navigate(['/dashboard']);
      },
error: (error) => {
  console.error('Error:', error);
  // Capturamos el mensaje que viene del backend (error.error.message)
  // Si por alguna razón no viene (error de red), ponemos uno por defecto.
  this.errorMessage = error.error.message || 'Error al intentar iniciar sesión';
  this.cdr.detectChanges();
}
    });
  }
}