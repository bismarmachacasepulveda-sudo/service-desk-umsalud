import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CategoryService } from '../../../services/category'; 
import { UserService } from '../../../services/user';

@Component({
  selector: 'app-category-management',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './category-management.html',
  styleUrl: './category-management.css'
})
export class CategoryManagementComponent implements OnInit {
  categories: any[] = []; // Lista de categorías
  // Modelo para crear
  newName: string = '';
  newDescription: string = '';
  newType: string = 'ticket';
  newVisibility: string = 'publico';
  // Modelo para editar (Clon del objeto)
  editingCategory: any = null;
  // Permisos
  esAdmin = false;
  // Estados
  isLoading = false;
  isProcessing = false;
  successMessage = '';
  errorMessage = '';
  // Inyectamos servicios
  private catService = inject(CategoryService);
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);
  /**====ciclo de vida====*/
  ngOnInit() {
    this.verificarPermisos();
    this.loadCategories();
  }
  /**==== Verificación de permisos ====*/ 
  verificarPermisos() {
    this.userService.getCurrentUser().subscribe(user => {
      this.esAdmin = user.role === 'admin';
    });
  }
  /**==== Carga de categorías ====*/ 
  loadCategories() {
    this.isLoading = true;
    this.catService.getCategories().subscribe({
      next: (data) => {
        this.categories = data;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error(err);
        this.errorMessage = 'Error al cargar categorías.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }
  /**==== Creación de categoría ====*/
  create() {
    if (!this.newName.trim()) return;
    this.isProcessing = true;
    this.successMessage = '';
    this.errorMessage = '';
    // Regla de negocio: si es de Tickets siempre públicos
    if (this.newType === 'ticket') {
        this.newVisibility = 'publico';
    }
    const payload = {
        name: this.newName,
        description: this.newDescription, // Enviamos descripción
        tipo: this.newType,
        visibilidad: this.newVisibility
    };

    this.catService.createCategory(payload).subscribe({
      next: () => {
        this.successMessage = 'Categoría creada correctamente.';
        this.resetForm();
        this.loadCategories();
        this.isProcessing = false;
        this.mostrarMensajeExito();
      },
      error: (err) => {
        this.errorMessage = err.error.message || 'Error al crear.';
        this.isProcessing = false;
        this.cdr.detectChanges();
      }
    });
  }

  /**==== Toggle Rápido de Estado (Activo/Inactivo) ====*/ 
  toggleActive(category: any) {
    if (!this.esAdmin) return;
    // Invertimos estado localmente para efecto inmediato (UI Optimista)
    const nuevoEstado = !category.active;
    category.active = nuevoEstado; 
    // Enviamos al backend (usamos update parcial)
    const payload = { ...category, active: nuevoEstado };
    this.catService.updateCategory(category.id, payload).subscribe({
      error: () => {
        // Revertir si falla
        category.active = !nuevoEstado;
        alert('Error al cambiar el estado.');
      }
    });
  }
  /**==== Inicio de edición de categoría ====*/
  startEdit(cat: any) {
    // Creamos una copia para no modificar la tabla hasta guardar
    this.editingCategory = { ...cat };
  }
  /**==== Guardar edición de categoría ====*/
  saveEdit() {
    if (!this.editingCategory || !this.editingCategory.name.trim()) return;
    this.isProcessing = true;
    // Backend espera todo el objeto en el PUT
    this.catService.updateCategory(this.editingCategory.id, this.editingCategory).subscribe({
      next: () => {
        this.successMessage = 'Categoría actualizada.';
        this.editingCategory = null;
        this.loadCategories();
        this.isProcessing = false;
        this.mostrarMensajeExito();
      },
      error: (err) => {
        this.errorMessage = err.error.message || 'Error al actualizar.';
        this.isProcessing = false;
        this.cdr.detectChanges();
      }
    });
  }
  /**==== Eliminación de categoría ====*/
  deleteCategory(id: number) {
    if (!confirm('¿Mover esta categoría a la papelera? Los tickets asociados perderán su clasificación visual.')) return;
    this.catService.deleteCategory(id).subscribe({
      next: () => {
        this.successMessage = 'Categoría enviada a la papelera.';
        this.loadCategories();
        this.mostrarMensajeExito();
      },
      error: (err) => {
        alert('Error al eliminar.');
      }
    });
  }
  /**==== Reinicia el formulario de creación ====*/
  private resetForm() {
    this.newName = '';
    this.newDescription = '';
    this.newType = 'ticket';
    this.newVisibility = 'publico';
  }
  /**==== Muestra mensaje de éxito ====*/
  private mostrarMensajeExito() {
    this.cdr.detectChanges();
    setTimeout(() => { 
        this.successMessage = ''; 
        this.cdr.detectChanges(); 
    }, 3000);
  }
}