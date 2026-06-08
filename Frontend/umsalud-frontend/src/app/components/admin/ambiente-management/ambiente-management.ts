import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AmbienteService } from '../../../services/ambiente';
import { UserService } from '../../../services/user';

// Componente Principal de Gestión de Ambientes
@Component({
  selector: 'app-ambiente-management',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './ambiente-management.html',
  styleUrl: './ambiente-management.css'
})
// Clase del Componente
export class AmbienteManagementComponent implements OnInit {
  // Variables de Formulario
  ambientes: any[] = [];
  formModel = {
    id: null as number | null,
    nombre: '',
    tipo: 'Aula',
    capacidad: 30,
    ubicacion: '',
    estado: 'activo',
    descripcion: ''
  };

  // Estado
  isAdmin = false;
  isEditing = false; // Bandera para saber si es update o create
  isLoading = false;
  isProcessing = false;
  successMessage = '';
  errorMessage = '';
  // Inyectamos los servicios necesarios
  private ambienteService = inject(AmbienteService);
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);


  /**====carga inicial de datos====*/
  ngOnInit() {
    this.verificarPermisos();
    this.loadAmbientes();
  }
  /** verifica los permisos del usuario */
  verificarPermisos() {
    this.cdr.detectChanges();
    this.userService.getCurrentUser().subscribe(user => {
      this.isAdmin = user.role === 'admin';
    });
  }
  /**====carga los ambientes====*/
  loadAmbientes() {
    this.isLoading = true;
    this.ambienteService.getAmbientes().subscribe({
      next: (data) => {
        this.ambientes = data;
        this.isLoading = false; // Detenemos el spinner
        this.cdr.detectChanges(); // Forzamos la detección de cambios para actualizar la vista
      },
      error: (err) => {
        this.errorMessage = 'Error de conexión.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  /**===== Guarda los cambios en el ambiente===== */
  save() {
    this.cdr.detectChanges();
    // Validación básica: el nombre no puede estar vacío
    if (!this.formModel.nombre.trim()) return;
    this.isProcessing = true;
    this.successMessage = '';
    this.errorMessage = '';
    // Determinamos si es una creación o actualización
    const request = this.isEditing && this.formModel.id
      ? this.ambienteService.updateAmbiente(this.formModel.id, this.formModel)
      : this.ambienteService.createAmbiente(this.formModel);
    // Suscribimos al resultado de la petición
    request.subscribe({
      next: () => {
        this.successMessage = this.isEditing ? 'Ambiente actualizado.' : 'Ambiente creado.';
        this.loadAmbientes();
        this.resetForm();
        this.isProcessing = false;
        this.cdr.detectChanges();
        setTimeout(() => this.successMessage = '', 3000);
      },
      error: (err) => {
        this.errorMessage = err.error.message || 'Error al procesar.';
        this.isProcessing = false;
        this.cdr.detectChanges();
      }
    });
  }
  /**====Inicia la edición de un ambiente====*/
  startEdit(item: any) {
    // Clonamos los datos al formulario
    this.cdr.detectChanges();
    this.formModel = { ...item };
    this.isEditing = true;
    
    // Scroll suave hacia arriba en móviles
    window.scrollTo({ top: 0, behavior: 'smooth' }); 
    this.cdr.detectChanges();
  }

  /**====Elimina un ambiente====*/
  deleteAmbiente(id: number) {
    this.cdr.detectChanges();
    if(!confirm('¿Mover este ambiente a la papelera?')) return;
    
    this.ambienteService.deleteAmbiente(id).subscribe({
        next: () => {
            this.successMessage = 'Ambiente movido a la papelera.';
            this.loadAmbientes();
            this.cdr.detectChanges();
            setTimeout(() => this.successMessage = '', 3000);
        },
        error: () => alert('Error al eliminar.')
    });
  }

  /**====Reinicia el formulario==== */
  resetForm() {
    this.isEditing = false;
    this.formModel = {
      id: null,
      nombre: '',
      tipo: 'Aula',
      capacidad: 30,
      ubicacion: '',
      estado: 'activo',
      descripcion: ''
    };
  }
  
}