import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AreaService } from '../../../services/area'; 
import { UserService } from '../../../services/user';

@Component({
  selector: 'app-area-management',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './area-management.html',
  styleUrl: './area-management.css'
})
export class AreaManagementComponent implements OnInit {
  
  areas: any[] = []; // Lista de áreas
  newAreaName: string = ''; // Campo para el nombre de la nueva área
  newAreaDesc: string = ''; // Campo para la descripción de la nueva área
  editingArea: any = null; // Área que se está editando
  esAdmin = false; // Permiso de administrador
  // Estados UI
  errorMessage: string = '';
  successMessage: string = '';
  isCreating = false; 
  isUpdating = false;
  isLoading = false;

  private areaService = inject(AreaService);
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);
  /**====carga inicial de datos====*/
  ngOnInit(): void {
    this.verificarPermisos();
    this.loadAreas();
  }
  /**====verifica los permisos del usuario==== */
  verificarPermisos() {
    this.userService.getCurrentUser().subscribe(user => {
        this.esAdmin = user.role === 'admin';
    });
  }
  /*====carga las áreas====*/
  loadAreas() {
    this.cdr.detectChanges();
    this.isLoading = true;
    this.areaService.getAreas().subscribe({
      next: (data) => {
        this.areas = data;
        this.isLoading = false;
        this.cdr.detectChanges(); 
      },
      error: (err) => {
        this.errorMessage = 'Error al cargar áreas.';
        this.isLoading = false;
        this.cdr.detectChanges(); 
      }
    });
  }
  /**====Crea una nueva área====*/
  createArea() {
    this.cdr.detectChanges();
    this.successMessage = '';
    this.errorMessage = '';
    //simple validación: el nombre no puede estar vacío
    if (!this.newAreaName.trim()) return;
    this.isCreating = true;
    // Preparamos el payload para la creación
    const payload = {
        name: this.newAreaName,
        description: this.newAreaDesc
    };
    // Enviamos la solicitud de creación
    this.areaService.createArea(payload).subscribe({
      next: (res) => {
        this.successMessage = `Área creada correctamente.`;
        this.newAreaName = '';
        this.newAreaDesc = '';
        this.loadAreas(); 
        this.isCreating = false;
        this.cdr.detectChanges(); 
        setTimeout(() => this.successMessage = '', 3000); 
      },
      error: (err) => {
        this.isCreating = false;
        this.errorMessage = err.error.message || 'Error al crear.';
        this.cdr.detectChanges();
      }
    });
  }

  /**====Cambia el estado de un área (Activo/Inactivo)==== */
  toggleActive(area: any) {
    if (!this.esAdmin) return;
    
    const newState = !area.active;
    area.active = newState; // Optimista

    this.areaService.updateArea(area.id, { ...area, active: newState }).subscribe({
        error: () => {
            area.active = !newState; // Revertir
            alert('Error al cambiar estado.');
        }
    });
  }
  /**====Inicia la edición de un área==== */
  editArea(area: any) {
    this.editingArea = { ...area }; // Clonar objeto
  }
  /**====Guarda los cambios en el área==== */
  saveEdit() {
    if (!this.editingArea.name.trim()) return;

    this.isUpdating = true;
    
    this.areaService.updateArea(this.editingArea.id, this.editingArea).subscribe({
      next: (res) => {
        this.successMessage = `Área actualizada.`;
        this.editingArea = null;
        this.loadAreas();
        this.isUpdating = false;
        this.cdr.detectChanges(); 
        setTimeout(() => this.successMessage = '', 3000);
      },
      error: (err) => {
        this.isUpdating = false;
        this.errorMessage = err.error.message || 'Error al actualizar.';
        this.cdr.detectChanges();
      }
    });
  }
  /**====Elimina un área====*/
  deleteArea(id: number) {
    if (confirm('¿Mover esta área a la papelera? Los tickets asociados perderán su referencia visual.')) {
      this.areaService.deleteArea(id).subscribe({
        next: () => {
          this.successMessage = 'Área movida a la papelera.';
          this.loadAreas();
          this.cdr.detectChanges();
          setTimeout(() => this.successMessage = '', 3000);
        },
        error: (err) => {
          alert('Error al eliminar.');
        }
      });
    }
  }
}