import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { TicketService } from '../../services/ticket';
import { CategoryService } from '../../services/category';
import { ChatComponent } from '../chat/chat';
import { AuthService } from '../../services/auth';
import { UserService } from '../../services/user';
import { LogsActividadesService } from '../../services/logs-actividades';

@Component({
  selector: 'app-ticket-detail',
  standalone: true,
  imports: [CommonModule, FormsModule, ChatComponent],
  templateUrl: './ticket-detail.html',
  // styleUrl: './ticket-detail.css'
})
export class TicketDetailComponent implements OnInit {
  
  ticket: any = null;
  categories: any[] = [];
  // Variables nuevas para el modal
showCollaboratorModal = false;
tecnicosDisponibles: any[] = [];
selectedCollaboratorId: number | null = null;
isSavingCollaborator = false;
  // Estados de carga y visualización
  isLoading = true;
  isClosing = false; // Muestra el formulario amarillo de resolución
  isUpdating = false;
  errorMessage = '';
  isLoadingCollaborators = false;
  // 
  isUpdatingPriority = false;
// Variables para el modal de asignación
showAssignModal = false;
tecnicosParaAsignar: any[] = [];
selectedTechId: number | null = null;
isAssigning = false;
isLoadingAssign = false;

  // Datos para cerrar/resolver el ticket
  closureData = {
    solution_notes: '',
    minutes_spent: 30,
    category_id: null
  };

  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private ticketService = inject(TicketService);
  private categoryService = inject(CategoryService);
  private cdr = inject(ChangeDetectorRef);
  private userService = inject(AuthService);
  private UserService = inject(UserService);

  ngOnInit() {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.loadTicket(Number(id));
      this.loadCategories();
    }
  }

  loadTicket(id: number) {
    this.isLoading = true;
    this.ticketService.getTicket(id).subscribe({
      next: (data) => {
        this.ticket = data;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.errorMessage = 'No se pudo cargar el ticket.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  loadCategories() {
    this.categoryService.getCategories().subscribe(data => this.categories = data);
  }

  // --- ACCIONES TÉCNICAS (CAMBIO DE ESTADO) ---

  cambiarEstado(nuevoEstado: string) {
    if(!confirm(`¿Cambiar estado a "${nuevoEstado.toUpperCase()}"?`)) return;

    this.isLoading = true;
    // Solo enviamos el status, el resto se mantiene
    this.ticketService.updateTicket(this.ticket.id, { status: nuevoEstado }).subscribe({
        next: (res) => {
            this.ticket = res; 
            this.isLoading = false;
            this.cdr.detectChanges();
        },
        error: (err) => {
            alert('Error al cambiar estado.');
            this.isLoading = false;
        }
    });
  }

  // Acción: Técnico presiona "Registrar Solución"
  abrirFormularioResolucion() {
    this.isClosing = true; 
    // Scroll al formulario
    setTimeout(() => window.scrollTo(0, document.body.scrollHeight), 100);
  }

  // Acción: Guardar el formulario (Técnico termina trabajo)
  confirmClose() {
    if (!this.closureData.solution_notes || !this.closureData.minutes_spent || !this.closureData.category_id) {
        alert('Por favor complete todos los campos técnicos de la solución.');
        return;
    }

    this.isUpdating = true;
    
    // Al guardar la solución, el estado pasa a RESUELTO (esperando confirmación)
    // OJO: Si prefieres cerrarlo directo, cambia 'resuelto' por 'cerrado'
    const payload = {
        status: 'resuelto',
        ...this.closureData
    };

    this.ticketService.updateTicket(this.ticket.id, payload).subscribe({
        next: (res) => {
            this.ticket = res;
            this.isClosing = false;
            this.isUpdating = false;
            this.cdr.detectChanges();
            alert('Solución registrada. El ticket está ahora RESUELTO.');
        },
        error: (err) => {
            alert('Error al guardar solución.');
            this.isUpdating = false;
        }
    });
  }

  // --- ACCIONES DE USUARIO FINAL / ADMIN ---

  // Acción: Usuario confirma que ya funciona (Cierre definitivo)
  cerrarTicketDirecto() {
    if(!confirm('¿Confirmas que el problema está solucionado y deseas cerrar el ticket definitivamente?')) return;
    
    this.isUpdating = true;
    const payload = {
        status: 'cerrado',
        solution_notes: this.ticket.solution_notes || 'Cierre confirmado por usuario',
        minutes_spent: this.ticket.minutes_spent || 0,
        category_id: this.ticket.category_id || 1
    };

    this.ticketService.updateTicket(this.ticket.id, payload).subscribe({
        next: (res) => {
            this.ticket = res;
            this.isUpdating = false;
            this.cdr.detectChanges();
            alert('¡Gracias! El ticket ha sido cerrado.');
        },
        error: (err) => {
            this.isUpdating = false;
            alert('Error al cerrar.');
        }
    });
  }

  // Acción: Usuario dice que sigue fallando (Reabrir)
  reabrirTicket() {
    if(!confirm('¿El problema persiste? El ticket volverá a estado "EN PROCESO".')) return;
    
    this.ticketService.updateTicket(this.ticket.id, { status: 'en_proceso' }).subscribe({
        next: (res) => {
            this.ticket = res;
            this.cdr.detectChanges();
            alert('Ticket reabierto. El técnico será notificado.');
        }
    });
  }

  // Acción: Usuario cancela su solicitud (Auto-cierre)
  cancelTicketByUser() {
    if (!confirm('¿Está seguro de cancelar esta solicitud? Se marcará como cerrada.')) return;

    this.isLoading = true;
    const payload = {
        status: 'cerrado',
        solution_notes: 'Cancelado por el usuario (Solicitud retirada).',
        minutes_spent: 0,
    };

    this.ticketService.updateTicket(this.ticket.id, payload).subscribe({
        next: (res) => {
            this.ticket = res;
            this.isLoading = false;
            this.cdr.detectChanges();
            alert('Su solicitud ha sido cancelada.');
        },
        error: (err) => {
            console.error(err);
            this.isLoading = false;
            this.errorMessage = 'No se pudo cancelar el ticket.';
            this.cdr.detectChanges();
        }
    });
  }

  // --- HELPERS ---

  esImagen(ruta: string): boolean {
      if (!ruta) return false;
      return ruta.match(/\.(jpeg|jpg|png|gif)$/) != null;
  }

  goBack() {
    this.router.navigate(['/tickets']);
  }

getPriorityClass(priority: string): string {
    switch (priority?.toLowerCase()) {
        case 'critica': return 'bg-critica pulse-animation'; // Clase personalizada
        case 'alta':     return 'bg-danger text-white';       // Rojo
        case 'media':    return 'bg-warning text-dark';       // Naranja (texto oscuro para contraste)
        case 'baja':     return 'bg-success text-white';      // Verde
        default:         return 'bg-secondary text-white';
    }
}
  // Helpers de Rol (Copiados para no depender del localstorage directo en html)
  isUserFinal(): boolean {
    const user = this.getUser();
    return user && user.role === 'usuario';
  }

  isTechnicianOrAdmin(): boolean {
    const user = this.getUser();
    return user && (user.role === 'tecnico' || user.role === 'admin');
  }
  isAdmin(): boolean {
    const user = this.getUser();
    return user && user.role === 'admin';
  }
   isTechnician(): boolean {
    const user = this.getUser();
    return user && user.role === 'tecnico';
  }
  
  
  private getUser() {
      const u = localStorage.getItem('user');
      return u ? JSON.parse(u) : null;
  }

  // NFUNCIÓN DE CONTROL DE ACCESO
  canManageTicket(): boolean {
    const user = this.getUser();
    if (!user || !this.ticket) return false;

    // 1. Si es Admin, tiene poder absoluto
    if (user.role === 'admin') return true;

    // 2. Si es Técnico...
    if (user.role === 'tecnico') {
      // Puede si el ticket es suyo O si nadie lo tiene asignado (para tomarlo)
      return this.ticket.assigned_to === user.id || this.ticket.assigned_to === null;
    }

    return false;
  }

  // 1. Permiso: ¿Quién puede añadir o quitar colaboradores?
// R: Solo el Admin o el Técnico que tiene el ticket asignado.
canManageCollaborator(): boolean {
    const user = this.userService.currentUserValue; // O como obtengas tu usuario
    if (!user) return false;
    
    if (user.role === 'admin') return true;
    if (user.role === 'tecnico' && this.ticket.assigned_to === user.id) return true;
    
    return false;
}

// --- 1. ABRIR MODAL Y CARGAR DATOS ---
openCollaboratorModal() {
    this.showCollaboratorModal = true;
    this.isLoadingCollaborators = true;
    this.cdr.detectChanges();
    this.UserService.getUsers().subscribe({
        next: (users) => {
            // FILTRO DE LÓGICA DE NEGOCIO:
            // 1. Solo usuarios con rol 'tecnico'
            // 2. Que NO sea el técnico principal actual (assigned_to)
            // 3. Que NO sea el colaborador actual (si estamos cambiando)
            
            this.tecnicosDisponibles = users.filter(u => 
                u.role === 'tecnico'|| u.role === 'admin' && 
                u.id !== this.ticket.assigned_to &&
                u.id !== this.ticket.colaborador_id
            );

            this.selectedCollaboratorId = null; // Resetear selección
            this.isLoadingCollaborators = false;
            this.cdr.detectChanges();
        },
        error: (err) => {
            console.error(err);
            this.isLoading = false;
            alert('Error al cargar la lista de Agentes.');
        }
    });
}

// --- 2. CERRAR MODAL ---
closeCollaboratorModal() {
    this.showCollaboratorModal = false;
    this.selectedCollaboratorId = null;
}

// --- 3. GUARDAR (ENVIAR AL BACKEND) ---
assignCollaborator() {
    if (!this.selectedCollaboratorId) return;
    this.isSavingCollaborator = true;
    // Enviamos solo el ID del nuevo colaborador
    const payload = {
        colaborador_id: this.selectedCollaboratorId
    };
    this.ticketService.updateTicket(this.ticket.id, payload).subscribe({
        next: (updatedTicket) => {
            this.isSavingCollaborator = false;
            this.closeCollaboratorModal();
            this.ticket = updatedTicket; // Actualizamos la vista
            this.cdr.detectChanges();
            alert('Colaborador asignado correctamente.');
        },
        error: (err) => {
            console.error(err);
            this.isSavingCollaborator = false;
            alert('No se pudo asignar el colaborador.');
        }
    });
}

// 3. Quitar Colaborador
removeCollaborator() {
    if(!confirm('¿Quitar al colaborador de este ticket?')) return;
    
    this.ticketService.updateTicket(this.ticket.id, { colaborador_id: null }).subscribe(res => {
        this.ticket = res;
        this.cdr.detectChanges();
        alert('Colaborador retirado.');
    });
}

liberarTicket() {
    if (!confirm('¿Estás seguro de liberar este ticket? Volverá a la bolsa de trabajo y dejarás de ser el responsable.')) {
        return;
    }

    this.isLoading = true;

    // Llamamos al servicio pasando el parámetro de acción
    this.ticketService.updateTicket(this.ticket.id, {}, 'release').subscribe({
        next: (res) => {
            this.ticket = res; // El backend devuelve el mensaje de éxito o el ticket actualizado
            this.isLoading = false;
            this.cdr.detectChanges();
            alert('Has liberado el ticket. Ahora es visible para todos en la bolsa de trabajo.');
        },
        error: (err) => {
            this.isLoading = false;
            console.error(err);
            alert('No se pudo liberar el ticket.');
        }
    });
}
tomarTicket() {
    if (!confirm('¿Deseas asignarte este ticket y comenzar a trabajar?')) return;

    this.isLoading = true;
    
    // Llamamos al servicio pasando la acción 'take'
    this.ticketService.updateTicket(this.ticket.id, {}, 'take').subscribe({
        next: (res) => {
            this.ticket = res; // El backend devuelve el ticket con assignedUser cargado
            this.isLoading = false;
            this.cdr.detectChanges();
            // Opcional: una pequeña notificación visual de éxito
        },
        error: (err) => {
            this.isLoading = false;
            alert(err.error.message || 'Error al tomar el ticket.');
        }
    });
}

// 1. Asegúrate de inyectar el Router en el constructor o vía inject()
// private router = inject(Router);

deleteTicketFromDetail() {
    const id = this.ticket.id;
    const confirmacion = confirm(
        `⚠️ ¿Desea enviar el Ticket #${id} a la papelera?\n\nLos usuarios y Agentes ya no podrán verlo en la lista`
    );

    if (!confirmacion) return;

    this.isLoading = true;

    this.ticketService.deleteTicket(id).subscribe({
        next: (res) => {
            this.isLoading = false;
            // Mostramos una alerta rápida antes de salir
            alert(res.message);
            
            // REDIRECCIÓN: El ticket ya no existe en la vista activa
            this.router.navigate(['/tickets']); 
        },
        error: (err) => {
            this.isLoading = false;
            this.errorMessage = err.error.message || 'Error de permisos o conexión.';
            this.cdr.detectChanges();
        }
    });
}
// 1. Abrir modal y cargar técnicos
openAssignModal() {
  this.showAssignModal = true;
  this.isLoadingAssign = true;
  this.cdr.detectChanges();
  this.UserService.getUsers().subscribe({
    next: (users) => {
      // Filtramos: Solo técnicos y administradores
      // Excluimos al técnico que ya está asignado (si es reasignación)
      this.tecnicosParaAsignar = users.filter(u => 
        (u.role === 'tecnico' || u.role === 'admin') && 
        u.id !== this.ticket.assigned_to
      );
      this.selectedTechId = null;
      this.isLoadingAssign = false;
      this.showAssignModal = true;
      this.cdr.detectChanges();
    },
    error: (err) => {
      console.error(err);
      this.isLoadingAssign = false;
      alert('Error al obtener la lista de personal.');
    }
  });
}

// 2. Ejecutar la asignación/reasignación
confirmAssignment() {
  if (!this.selectedTechId) return;

  this.isAssigning = true;
  const payload = {
    assigned_to: this.selectedTechId,
    // El backend se encargará de poner el status en 'en_proceso' 
    // y registrar el assigned_by_id gracias a tu lógica.
  };
  this.cdr.detectChanges();

  this.ticketService.updateTicket(this.ticket.id, payload).subscribe({
    next: (updatedTicket) => {
      this.ticket = updatedTicket;
      this.isAssigning = false;
      this.showAssignModal = false;
      this.cdr.detectChanges();
      alert(this.ticket.assigned_to ? 'Ticket reasignado correctamente.' : 'Técnico asignado correctamente.');
    },
    error: (err) => {
      console.error(err);
      this.isAssigning = false;
      alert('Error al procesar la asignación.');
    }
  });
}



cambiarPrioridad(nuevaPrioridad: string) {
    if (this.ticket.priority === nuevaPrioridad) return;
    this.cdr.detectChanges();
    this.isUpdatingPriority = true;
    this.ticketService.updateTicket(this.ticket.id, { priority: nuevaPrioridad }).subscribe({
        next: (updatedTicket) => {
            this.ticket.priority = nuevaPrioridad; // Actualizamos la vista
            this.isUpdatingPriority = false;
            console.log('Prioridad actualizada a ' + nuevaPrioridad);
            this.cdr.detectChanges();
        },
        error: (err) => {
            this.isUpdatingPriority = false;
            alert('Error al cambiar la prioridad.');
            this.cdr.detectChanges();
            console.error(err);
        }
    });
}
}