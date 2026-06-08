import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core'; 
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth';
import { AreaService } from '../../services/area';

@Component({
  selector: 'app-registro',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './registro.html',
  styleUrls: ['./registro.css']
})
export class RegistroComponent implements OnInit {
  
  // 1. OBJETO REFINADO: Se elimina 'ci' para usuarios finales
  datosRegistro = {
    nombre_completo: '',
    email: '',
    password: '',
    password_confirmation: '',
    telefono: '', // Se enviará como 'telefono' y Laravel lo mapeará a 'phone'
    cargo: '',
    area_id: null
  };

  listaAreas: any[] = [];
  mensajeError: string = '';
  mensajeExito: string = ''; // Nueva variable para mensajes positivos detallados
  
  cargando: boolean = false;
  registroExitoso: boolean = false;

  private authService = inject(AuthService);
  private areaService = inject(AreaService);
  private cdr = inject(ChangeDetectorRef);

  ngOnInit() {
    this.cargarAreas();
  }

  cargarAreas() {
    this.areaService.getAreas().subscribe({
        next: (areas) => {
            this.listaAreas = areas;
            this.cdr.detectChanges();
        },
        error: () => {
            this.mensajeError = 'Error de conexión: No se pudieron cargar las áreas.';
            this.cdr.detectChanges();
        }
    });
  }

  registrarse() {
    this.mensajeError = '';
    this.mensajeExito = '';

    // Validaciones preventivas de Frontend
    if (this.datosRegistro.password !== this.datosRegistro.password_confirmation) {
        this.mensajeError = 'Las contraseñas no coinciden.';
        return;
    }

    this.cargando = true;
    this.cdr.detectChanges();

// En RegistroComponent, dentro del método registrarse()

this.authService.registrarUsuario(this.datosRegistro).subscribe({
    next: (res) => {
        this.cargando = false;
        this.registroExitoso = true;
        
        // El backend ahora puede enviar un mensaje de "actualización"
        // Mostramos el mensaje exacto que viene del servidor
        this.mensajeExito = res.message || 'Solicitud enviada correctamente.';
        
        this.cdr.detectChanges(); 
    },
    error: (err) => {
        this.cargando = false;
        
        // Manejo de errores de validación o usuario activo
        if (err.status === 422) {
            const errores = err.error.errores || {};
            const primerError = Object.keys(errores)[0];
            this.mensajeError = primerError ? errores[primerError][0] : err.error.message;
        } else {
            this.mensajeError = err.error.message || 'Error al procesar la solicitud.';
        }
        
        this.cdr.detectChanges();
    }
});
  } 
}