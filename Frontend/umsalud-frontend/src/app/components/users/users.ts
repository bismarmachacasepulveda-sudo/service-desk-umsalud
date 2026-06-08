import { Component, inject, ChangeDetectorRef, OnInit } from '@angular/core'; 
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { UserService } from '../../services/user';
import { AreaService } from '../../services/area';
import { finalize } from 'rxjs/operators';

@Component({
  selector: 'app-users',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './users.html',
  styleUrl: './users.css'
})
export class UsersComponent implements OnInit {
  
  // --- VARIABLES DE DATOS ---
  users: any[] = [];
  pendientes: any[] = []; 
  inactivos: any[] = [];
  areas: any[] = [];

  // --- CONTROL DE UI ---
  activeTab: 'activos' | 'pendientes' | 'inactivos' | 'historial' = 'activos';
  historialSolicitudes: any[] = [];
  isLoading = false;
  successMessage = '';
  errorMessage = '';

  // --- CONTROL DE MODALES Y AUDITORÍA ---
  mostrarModalAuditoria = false; // Unificamos nomenclatura
  usuarioAuditoria: any = null;

  // --- MODELO DEL FORMULARIO ---
  newUser = {
    name: '',
    email: '',
    password: '',
    phone: '',
    ci: '',
    cargo: '',
    role: 'usuario',
    area_id: null,
    expertise: ''
  };

  isEditing = false;
  editingUserId: number | null = null;

  private userService = inject(UserService);
  private areaService = inject(AreaService);
  private changeDetectorRef = inject(ChangeDetectorRef);

  ngOnInit() {
    this.loadAreas(() => {
        this.loadUsers(); 
        this.loadPendientes(); 
    });
  }

  // TRADUCTOR DE ÁREAS (Para mostrar nombres en lugar de IDs)
  getAreaName(areaId: any): string {
    if (!areaId) return 'N/A';
    const id = Number(areaId);
    const area = this.areas.find(a => a.id === id);
    return area ? area.name : 'Desconocida';
  }

  //LÓGICA DE ROLES: Limpia campos según el rol seleccionado
  onRoleChange() {
    if (this.newUser.role === 'usuario') {
      this.newUser.expertise = '';
      this.newUser.ci = ''; // CI no es necesario para usuarios comunes
    } else if (this.newUser.role === 'tecnico') {
      this.newUser.area_id = null; // Técnicos no pertenecen a un área específica
    } else if (this.newUser.role === 'admin') {
      this.newUser.area_id = null;
      this.newUser.expertise = '';
    }
  }
  /**
   * ELIMINAR (INHABILITAR): Envía al usuario a la papelera (Soft Delete)
   */
  deleteUser(id: number) {
    if (confirm('¿Estás seguro de inhabilitar a este usuario? Se enviará a la papelera.')) {
      this.userService.deleteUser(id).subscribe({
        next: () => {
          this.mostrarMensaje('Usuario inhabilitado correctamente.', 'success');
          this.loadUsers();     // Recarga la lista de activos
          this.changeDetectorRef.detectChanges();
        },
        error: (err) => {
          this.mostrarError(err);
        }
      });
    }
  }

  // --- CARGA DE DATOS ---

  loadUsers() {
    this.userService.getUsers().subscribe({
      next: (data) => {
        this.users = data;
        this.changeDetectorRef.detectChanges(); 
      },
      error: (err) => console.error('Error cargando activos', err)
    });
  }

  loadPendientes() {
    this.userService.getPendientes().subscribe({
        next: (data) => {
            this.pendientes = data; // Ahora vienen de 'solicitudes_registro'
            this.changeDetectorRef.detectChanges();
        }
    });
  }


  loadAreas(callback?: () => void) {
    this.areaService.getAreas().subscribe({
      next: (data) => {
        this.areas = data;
        if (callback) callback(); 
      }
    });
  }

  // --- GESTIÓN DE FORMULARIO ---

registerUser() {
  this.isLoading = true;
  this.successMessage = '';
  this.errorMessage = '';
  
  this.onRoleChange(); 

  // Definimos la operación (Crear o Editar)
  const request$ = (this.isEditing && this.editingUserId)
    ? this.userService.updateUser(this.editingUserId, this.newUser)
    : this.userService.createUser(this.newUser);

  request$.pipe(
    // finalize apaga el cargador sin importar el resultado
    finalize(() => {
      this.isLoading = false;
      this.changeDetectorRef.detectChanges();
    })
  ).subscribe({
    next: () => {
      const msg = this.isEditing ? 'Usuario actualizado correctamente.' : 'Usuario registrado correctamente.';
      this.mostrarMensaje(msg, 'success');
      this.loadUsers(); 
      if (this.isEditing) {
        this.cancelEdit();
      } else {
        this.resetForm();
      }
    },
    error: (err) => this.mostrarError(err)
  });
}

  editUser(user: any) {
    this.isEditing = true;
    this.editingUserId = user.id;
    this.activeTab = 'activos'; 

    this.newUser = {
      name: user.name,
      email: user.email,
      password: '', 
      phone: user.phone,
      ci: user.ci,
      cargo: user.cargo,
      role: user.role,
      area_id: user.area_id,
      expertise: user.expertise
    };
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  cancelEdit() {
    this.isEditing = false;
    this.editingUserId = null;
    this.resetForm();
  }

  resetForm() {
    this.newUser = {
      name: '', email: '', password: '', phone: '',
      ci: '', cargo: '', role: 'usuario', area_id: null, expertise: ''
    };
    this.changeDetectorRef.detectChanges(); 
  }

  // --- ACCIONES DE AUDITORÍA Y ESTADO ---

  aprobar(user: any) {
    if(!confirm(`¿Aprobar acceso a ${user.name}?`)) return;
    
    this.userService.aprobarUsuario(user.id).subscribe({
        next: () => {
            this.mostrarMensaje(`Acceso aprobado para ${user.name}.`, 'success');
            this.loadPendientes();
            this.loadUsers();
        },
        error: () => alert('Error al aprobar usuario.')
    });
  }

  /**
   * RECHAZAR: Ahora solicita un motivo para cumplir con la auditoría
   */
  rechazar(user: any) {
    const motivo = window.prompt(`¿Por qué rechaza la solicitud de ${user.name}?`, "No cumple con los requisitos.");
    
    if (motivo !== null) { // Si no canceló el prompt
        this.userService.rechazarUsuario(user.id, motivo).subscribe({
            next: () => {
                this.mostrarMensaje('Solicitud rechazada y guardada en historial.', 'success');
                this.loadPendientes();
            },
            error: () => alert('Error al procesar el rechazo.')
        });
    }
  }


  // --- MODAL DE INFORMACIÓN (AUDITORÍA) ---

  abrirAuditoria(user: any) {
    this.usuarioAuditoria = user;
    this.mostrarModalAuditoria = true;
    this.changeDetectorRef.detectChanges();
  }

  cerrarAuditoria() {
    this.mostrarModalAuditoria = false;
    this.usuarioAuditoria = null;
  }

  // --- HELPERS DE MENSAJES ---

  private mostrarMensaje(msg: string, type: 'success' | 'error') {
    this.isLoading = false;
    if (type === 'success') this.successMessage = msg;
    else this.errorMessage = msg;
    
    this.changeDetectorRef.detectChanges();
    setTimeout(() => { 
      this.successMessage = ''; 
      this.errorMessage = ''; 
      this.changeDetectorRef.detectChanges(); 
    }, 4000);
  }

  private mostrarError(err: any) {
    this.isLoading = false;
    this.errorMessage = err.error.message || 'Ocurrió un error inesperado.';
    this.changeDetectorRef.detectChanges();
  }

  loadHistorial() {
    this.userService.getHistorialSolicitudes().subscribe({
        next: (data) => {
            this.historialSolicitudes = data;
            this.changeDetectorRef.detectChanges();
        }
    });
}
// Agrega este método en tu clase .ts
getRoleLabel(role: string): string {
  const roles: { [key: string]: string } = {
    'admin': 'Administrador',
    'tecnico': 'Agente de Soporte',
    'usuario': 'Usuario Final'
  };
  return roles[role] || role;
}
}