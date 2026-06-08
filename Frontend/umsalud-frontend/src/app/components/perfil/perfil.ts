import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { UserService } from '../../services/user';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './perfil.html',
  styleUrls: ['./perfil.css']
})
export class PerfilComponent implements OnInit {
  
  // Datos del perfil
  perfil: any = {
    name: '',
    email: '',
    phone: '',
    cargo: '',
    area: null, // Solo lectura
    role: ''    // Solo lectura
  };

  // Formulario de contraseña
  seguridad = {
    current_password: '',
    password: '',
    password_confirmation: ''
  };

  mensajeExito = '';
  mensajeError = '';
  cargando = false;

  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);
  private authService = inject(AuthService);

  ngOnInit() {
    this.cargarDatosPerfil();
  }

  cargarDatosPerfil() {
    this.userService.getPerfil().subscribe({
      next: (data) => {
        this.perfil = data;
        this.cdr.detectChanges();
      },
      error: () => this.mensajeError = 'No se pudo cargar la información del perfil.'
    });
  }

actualizarInformacion() {
  this.cargando = true;
  this.userService.updatePerfil(this.perfil).subscribe({
    next: (res) => {
      this.mensajeExito = res.message;
      
      // 🟢 AQUÍ ESTÁ EL TRUCO: Avisamos al AuthService del cambio
      this.authService.actualizarDatosLocales(res.user);
      
      this.cargando = false;
      this.cdr.detectChanges();
    },
      error: (err) => {
        this.mensajeError = err.error.message || 'Error al actualizar el perfil.';
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  actualizarContrasena() {
    if (this.seguridad.password !== this.seguridad.password_confirmation) {
      this.mensajeError = 'La nueva contraseña y su confirmación no coinciden.';
      return;
    }

    this.cargando = true;
    this.limpiarMensajes();

    this.userService.updatePassword(this.seguridad).subscribe({
      next: (res) => {
        this.mensajeExito = res.message;
        this.seguridad = { current_password: '', password: '', password_confirmation: '' };
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.mensajeError = err.error.message || 'Error al cambiar la contraseña.';
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  private limpiarMensajes() {
    this.mensajeExito = '';
    this.mensajeError = '';
  }
}