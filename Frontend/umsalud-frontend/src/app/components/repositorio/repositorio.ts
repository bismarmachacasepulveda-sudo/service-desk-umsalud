import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RepositorioService } from '../../services/repositorio';
import { CategoryService } from '../../services/category'; // Necesitas esto
import { UserService } from '../../services/user';

@Component({
  selector: 'app-repositorio',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './repositorio.html',
  styleUrl: './repositorio.css'
})
export class RepositorioComponent implements OnInit {
  
  archivos: any[] = [];
  categorias: any[] = [];

  // Filtros
  filtroCategoriaId: number | null = null;
  textoBusqueda: string = '';
  // Variables del usuario actual
  esAdmin = false;
  esTecnico = false;
  puedeSubir = false; // Admin + Técnico

  // Formulario
  archivoSeleccionado: File | null = null;
  descripcion: string = '';
  categoriaId: number | null = null;
  visibilidad: string = 'tecnico'; // Por defecto privado
  busqueda: string = '';

  cargando = false;
  subiendo = false;
  descargandoId: number | null = null;
  mensajeExito = '';
  mensajeError = '';
  
  private repoService = inject(RepositorioService);
  private catService = inject(CategoryService);
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);

  ngOnInit() {
    this.verificarRol();
    this.cargarArchivos();
    this.cargarCategorias();
  }

  verificarRol() {
    this.userService.getCurrentUser().subscribe(user => {
      this.esAdmin = user.role === 'admin';
      this.esTecnico = user.role === 'tecnico';
      this.puedeSubir = this.esAdmin || this.esTecnico;
      // Usuarios normales solo ven Público
      if (!this.puedeSubir) this.visibilidad = 'publico';
    });
  }

cargarCategorias() {
    // Solo traemos categorías destinadas al repositorio
    this.catService.getCategories().subscribe(cats => {
        this.categorias = cats.filter((c: any) => c.tipo === 'repositorio' && c.active);
        this.cdr.detectChanges();
    });
  }

// Getter para filtrar en tiempo real (sin llamar al backend)
  get archivosFiltrados() {
    return this.archivos.filter(archivo => {
      // 1. Filtro por Categoría (Tabs)
      const matchCat = this.filtroCategoriaId === null || archivo.category_id === this.filtroCategoriaId;
      
      // 2. Filtro por Buscador
      const term = this.textoBusqueda.toLowerCase();
      const matchText = !term || 
                        archivo.nombre_original.toLowerCase().includes(term) || 
                        (archivo.descripcion && archivo.descripcion.toLowerCase().includes(term));
      
      return matchCat && matchText;
    });
  }

cargarArchivos() {
  this.cdr.detectChanges();
    this.cargando = true;
    this.repoService.obtenerArchivos().subscribe({
      next: (data) => {
        this.archivos = data;
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.cargando = false;
        this.cdr.detectChanges();
        this.mensajeError = 'Error al cargar el repositorio.';
      }
    });
  }

seleccionarArchivo(event: any) {
    if (event.target.files.length > 0) {
        this.archivoSeleccionado = event.target.files[0];
    }
  }

  subir() {
    if (!this.archivoSeleccionado || !this.categoriaId) return;

    this.subiendo = true;
    this.cdr.detectChanges();
    this.repoService.subirArchivo(
        this.archivoSeleccionado, 
        this.descripcion, 
        this.categoriaId, 
        this.visibilidad
    ).subscribe({
        next: () => {
            this.mensajeExito = 'Archivo subido con éxito.';
            this.subiendo = false;
            this.limpiarFormulario();
            this.cargarArchivos();
            this.cdr.detectChanges();
            setTimeout(() => this.mensajeExito = '', 3000);
        },
        error: (err) => {
            this.subiendo = false;
            this.mensajeError = 'Error al subir. Verifique el tamaño (Máx 50MB).';
            console.error(err);
            setTimeout(() => this.mensajeError = '', 3000);
        }
    });
  }

  
descargar(archivo: any) {
    this.descargandoId = archivo.id;
    this.cdr.detectChanges();
    this.repoService.descargarArchivo(archivo.id).subscribe({
        next: (blob) => {
            // Truco para descargar Blob
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = archivo.nombre_original;
            a.click();
            window.URL.revokeObjectURL(url);
            this.descargandoId = null;
        },
        error: () => {
            alert('Error al descargar el archivo.');
            this.descargandoId = null;
        }
    });
  }

  eliminar(id: number) {
    if (!confirm('¿Mover a la papelera?')) return;
    this.cdr.detectChanges();
    this.repoService.eliminarArchivo(id).subscribe(() => {
        this.archivos = this.archivos.filter(a => a.id !== id);
        this.mensajeExito = 'Archivo eliminado.';
        this.cdr.detectChanges();
        setTimeout(() => this.mensajeExito = '', 3000);
    });
  }

limpiarFormulario() {
    this.archivoSeleccionado = null;
    this.descripcion = '';
    this.categoriaId = null;
    this.cdr.detectChanges();
    // Resetear input file visualmente
    const input = document.getElementById('fileInput') as HTMLInputElement;
    if(input) input.value = '';
  }

  // Helpers visuales
  obtenerIcono(ext: string): string {
    const e = (ext || '').toLowerCase().replace('.', '');
    if (['pdf'].includes(e)) return 'bi-file-earmark-pdf text-danger';
    if (['doc', 'docx'].includes(e)) return 'bi-file-earmark-word text-primary';
    if (['xls', 'xlsx', 'csv'].includes(e)) return 'bi-file-earmark-excel text-success';
    if (['ppt', 'pptx'].includes(e)) return 'bi-file-earmark-ppt text-warning';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(e)) return 'bi-file-earmark-image text-info';
    if (['zip', 'rar', '7z'].includes(e)) return 'bi-file-earmark-zip text-dark';
    return 'bi-file-earmark text-secondary';
  }
  
}