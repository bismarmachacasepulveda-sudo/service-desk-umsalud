import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TicketService } from '../../services/ticket';
import { AreaService } from '../../services/area';    
import { UserService } from '../../services/user';    
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth';
import { NgxPaginationModule } from 'ngx-pagination';
// Interfaces user para tipar los datos
interface User {
  id: number;
  name: string;
  email: string;
  role: 'usuario' | 'tecnico' | 'admin';
  area_id?: number;
}
// Interface para áreas
interface Area {
  id: number;
  name: string;
}
// Interface para tickets (con campos adicionales para impacto y urgencia) 
interface Ticket {
  id: number;
  area_id: number;
  user_id: number;
  assigned_to?: number | null;
  colaborador_id?: number | null;
  subject: string;
  description: string;
  priority: 'baja' | 'media' | 'alta' | 'critica';
  status: 'abierto' | 'en_proceso' | 'en_espera' | 'resuelto' | 'cerrado';
  created_at: string;
  
  impacto?: string;
  urgencia?: string;

  user?: User;
  area?: Area;
  assigned_user?: User;
  colaborador?: User;
}
// Componente Principal de Gestión de Tickets
@Component({
  selector: 'app-ticket-management',
  standalone: true,
  imports: [CommonModule, FormsModule, NgxPaginationModule],
  templateUrl: './ticket-management.html',
  // styleUrl: './ticket-management.css' 
})
// Clase del Componente

export class TicketManagementComponent implements OnInit {
  // Inyectamos los servicios necesarios
  private authService = inject(AuthService);
  private ticketService = inject(TicketService);
  private areaService = inject(AreaService);
  private userService = inject(UserService); 
  private changeDetectorRef = inject(ChangeDetectorRef);
  private router = inject(Router);
  // Filtros
  searchText: string = '';
  filterStatus: string = '';
  filterPriority: string = '';
  filterMyTickets: boolean = false;
  archivoSeleccionado: File | null = null;
  filteredTickets: Ticket[] = [];
  p: number = 1;
  // --- DATOS ---
  tickets: Ticket[] = [];
  areas: Area[] = [];
  usersList: User[] = []; 
  techniciansList: User[] = []; 
  // Estados de carga y mensajes
  isLoading = false;
  successMessage = '';
  errorMessage = '';
 // Variables de control visual
canViewUserColumn: boolean = false;
canTakeTicket: boolean = false;
canDeleteTicket: boolean = false;
  // --- ROL Y USUARIO ACTUAL ---
currentUser: any = this.authService.currentUserSnapshot;
userRole: 'usuario' | 'tecnico' | 'admin' = this.currentUser?.role || 'usuario';
  // MODELO DEL FORMULARIO 
  newTicket = {
    area_id: null as number | null,
    subject: '',
    description: '',

    impacto: '',  // individual, departamental, general
    urgencia: '', // baja, media, alta
    
    status: 'abierto',
    user_id: null as number | null, 
    assigned_to: null as number | null, 
    
  };

 // cargo inicial de datos
  ngOnInit(): void {
    this.loadCurrentUser(); // Carga el usuario actual y luego los tickets
    this.loadAreas(); // Carga las áreas para el dropdown del formulario
    
    // VERIFICAR SI VENIMOS DEL CHATBOT
    const navigation = history.state;
    const user = this.authService.currentUserSnapshot; // Usando tu nuevo método del servicio
    const role = user?.role;
    
    this.canViewUserColumn = ['admin', 'tecnico'].includes(role);
    this.canTakeTicket = role === 'tecnico';
    this.canDeleteTicket = role === 'admin';
    if (navigation && navigation.descripcionAutomatica) {
        this.newTicket.description = navigation.descripcionAutomatica;
        this.newTicket.subject = "Solicitud de ayuda desde Chatbot";
        // Valores por defecto para chatbot
        this.newTicket.impacto = 'individual'; 
        this.newTicket.urgencia = 'media';
    }
  }
  isTechnician(): boolean { return this.userRole === 'tecnico'; }
  isAdmin(): boolean { return this.userRole === 'admin'; }
  isUserFinal() { return this.userRole === 'usuario'; }
  isTechnicianOrAdmin() {return this.userRole === 'admin' || this.userRole === 'tecnico';}


trackByTicket(index: number, ticket: any): number {
  return ticket.id; // Angular usa el ID para rastrear la fila
}
  viewTicket(id: number) {
    this.router.navigate(['/tickets', id]);
  }

  // ----------------------------------------------------
  // --- LÓGICA DE USUARIO Y ROLES ---
  // ----------------------------------------------------

  loadCurrentUser() {
    this.userService.getCurrentUser().subscribe({
        next: (user: User) => {
            this.currentUser = user;
            this.userRole = user.role.toLowerCase() as 'usuario' | 'tecnico' | 'admin'; 
            
            if (this.userRole === 'usuario') {
                this.newTicket.user_id = user.id;
                if (user.area_id) {
                    this.newTicket.area_id = user.area_id;
                }
            }

            if (this.userRole !== 'usuario') {
                this.loadUsersAndTechnicians();
            }

            this.loadTickets();
            this.changeDetectorRef.detectChanges();
        },
        error: (err: any) => {
            console.error('Error al cargar usuario actual', err);
            this.userRole = 'usuario';
            this.loadTickets();
        }
    });
  }

  loadUsersAndTechnicians() {
    this.userService.getUsers().subscribe({ 
        next: (data: User[]) => {
            this.usersList = data.filter(u => u.role === 'usuario'); 
            this.techniciansList = data.filter(u => u.role === 'tecnico');
            this.changeDetectorRef.detectChanges();
        },
        error: (err: any) => console.error('Error al cargar listas de usuarios:', err)
    });
  }

  onUserSelect() {
      if (!this.newTicket.user_id) return;
      const selectedUser = this.usersList.find(u => u.id === this.newTicket.user_id);
      
      if (selectedUser && selectedUser.area_id) {
          this.newTicket.area_id = selectedUser.area_id;
      } else {
          this.newTicket.area_id = null;
      }
      this.changeDetectorRef.detectChanges();
  }

  // ----------------------------------------------------
  // --- CRUD TICKETS ---
  // ----------------------------------------------------

  loadTickets() {
    this.ticketService.getTickets().subscribe({
      next: (data: Ticket[]) => {
        if (this.userRole === 'usuario' && this.currentUser) {
            this.tickets = data.filter(t => t.user_id === this.currentUser!.id);
        } else {
            this.tickets = data;
        }
        this.applyFilters(); 
        this.isLoading = false;
        this.changeDetectorRef.detectChanges();
      },
      error: (err) => console.error(err)
    });
  }

  applyFilters() {
    this.filteredTickets = this.tickets.filter(ticket => {
      const matchText = (ticket.subject.toLowerCase().includes(this.searchText.toLowerCase())) || 
                        (ticket.user?.name.toLowerCase().includes(this.searchText.toLowerCase()));

      const matchStatus = this.filterStatus ? ticket.status === this.filterStatus : true;
      const matchPriority = this.filterPriority ? ticket.priority === this.filterPriority : true;
      let matchOwner = true;
      if (this.filterMyTickets && this.currentUser) {
        matchOwner = (ticket.assigned_to === this.currentUser.id) || 
                     (ticket.colaborador_id === this.currentUser.id);
      }
      return matchText && matchStatus && matchPriority && matchOwner;
    });
  }

  loadAreas() {
    this.areaService.getAreas().subscribe({
      next: (data: Area[]) => this.areas = data,
      error: (err: any) => console.error(err)
    });
  }

  createTicket() {
    this.isLoading = true;
    this.successMessage = '';
    this.errorMessage = '';

    // 🟢 1. Validaciones Actualizadas (Chequear Impacto y Urgencia)
    if (!this.newTicket.area_id || !this.newTicket.subject.trim() || !this.newTicket.description.trim()) {
      this.errorMessage = 'Debe completar Asunto, Descripción y Área.';
      this.isLoading = false;
      return;
    }

    if (!this.newTicket.impacto || !this.newTicket.urgencia) {
        this.errorMessage = 'Debe seleccionar el Impacto y la Urgencia del problema.';
        this.isLoading = false;
        return;
    }

    // 2. Validación de Rol
    if (this.isTechnicianOrAdmin() && !this.newTicket.user_id) {
        this.errorMessage = 'Como Técnico/Admin, debe seleccionar el Usuario Afectado.';
        this.isLoading = false;
        return;
    }

    // 3. Preparar los datos finales
    const ticketPayload = {
        ...this.newTicket,
        user_id: this.newTicket.user_id || this.currentUser?.id, 
        assigned_to: this.isAdmin() ? this.newTicket.assigned_to : null,
    };

    // 4. Llamar al Servicio
    this.ticketService.createTicket(ticketPayload, this.archivoSeleccionado).subscribe({
      next: (res) => {
        this.isLoading = false;
        this.successMessage = `Ticket #${res.id} registrado exitosamente`;
        
        this.loadTickets(); 
        this.resetForm();   
        
        this.changeDetectorRef.detectChanges();
        setTimeout(() => {
            this.successMessage = '';
            this.changeDetectorRef.detectChanges();
        }, 5000); 
      },
      error: (err: any) => {
        this.isLoading = false;
        this.errorMessage = err.error?.message || 'Error al registrar el ticket.';
        console.error('Error de registro:', err);
        this.changeDetectorRef.detectChanges();
      }
    });
  }

  // ----------------------------------------------------
  // --- UTILIDADES ---


  getPriorityClass(priority: string): string {
    switch (priority) {
      case 'critica': return 'bg-dark text-danger border border-danger fw-bold'; // 🟢 Estilo para crítica
      case 'alta': return 'text-danger fw-bold';
      case 'media': return 'text-warning fw-bold';
      default: return 'text-secondary';
    }
  }

  getStatusClass(status: string): string {
    switch (status) {
      case 'abierto': return 'bg-danger';
      case 'en_proceso': return 'bg-warning text-dark';
      case 'en_espera': return 'bg-info text-dark'; // 🟢 Nuevo
      case 'resuelto': return 'bg-success bg-opacity-75'; // 🟢 Nuevo
      case 'cerrado': return 'bg-secondary';
      default: return 'bg-secondary';
    }
  }
  
  getAreaName(areaId: number): string {
    const area = this.areas.find(a => a.id === areaId);
    return area ? area.name : 'N/A';
  }

  getUserName(userId: number | undefined): string {
      if (!userId) return 'N/A';
      const user = this.usersList.find(u => u.id === userId) || this.techniciansList.find(u => u.id === userId);
      return user ? user.name : `Usuario ${userId}`;
  }

  resetForm() {
    this.newTicket = {
      area_id: (this.isUserFinal() && this.currentUser?.area_id) ? this.currentUser.area_id : null,
      
      subject: '',
      description: '',
      
      // 🟢 Reseteamos los nuevos campos
      impacto: '',
      urgencia: '',
      
      status: 'abierto',
      user_id: (this.isUserFinal() && this.currentUser) ? this.currentUser.id : null,
      assigned_to: null,
    };

    this.archivoSeleccionado = null;
    const input = document.getElementById('fileInputTicket') as HTMLInputElement;
    if(input) {
        input.value = '';
    }
    this.changeDetectorRef.detectChanges();
  }

deleteTicket(id: number) {
    const confirmacion = confirm(`¿Estás seguro de enviar el ticket #${id} a la papelera? Podrá ser recuperado por auditoría si es necesario.`);
    if (!confirmacion) return;

    this.isLoading = true; 

    this.ticketService.deleteTicket(id).subscribe({
        next: (res) => {
            // 1. Notificación de éxito
            this.successMessage = res.message;
            
            // 2. Filtramos el array localmente para que desaparezca YA MISMO
            // Esto es "UI Optimista" también: no esperamos a que loadTickets termine
            this.tickets = this.tickets.filter(t => t.id !== id);
            if (this.filteredTickets) {
        this.filteredTickets = this.filteredTickets.filter(t => t.id !== id);
    }
            this.isLoading = false;
            this.changeDetectorRef.detectChanges();

            // 3. Autolimpieza del mensaje
            setTimeout(() => this.successMessage = '', 4000);
        },
        error: (err) => {
            this.isLoading = false;
            this.errorMessage = err.error.message || 'No se pudo eliminar el ticket.';
            this.changeDetectorRef.detectChanges();
            setTimeout(() => this.errorMessage = '', 5000);
        }
    });
}

  takeTicket(id: number) {
    this.ticketService.takeTicket(id).subscribe({
      next: (updatedTicket) => {
        this.successMessage = `Ticket #${id} asignado a ti.`;
        this.loadTickets(); 
        this.changeDetectorRef.detectChanges();
        setTimeout(() => this.successMessage = '', 4000);
      },
      error: (err: any) => {
        this.errorMessage = err.error.message || 'Error al tomar el ticket.';
        this.changeDetectorRef.detectChanges();
      }
    });
  }

  onFileSelect(event: any) {
    const file = event.target.files[0];
    if (file) {
        this.archivoSeleccionado = file;
    }
  }
  // Simulación local de la matriz para feedback visual
  calcularPrioridadVisual(): string {
    const i = this.newTicket.impacto;
    const u = this.newTicket.urgencia;
    
    if (!i || !u) return '...';

    const matriz: any = {
        'individual':    { 'baja': 'baja',  'media': 'media',   'alta': 'media' },
        'departamental': { 'baja': 'media', 'media': 'alta',    'alta': 'alta' },
        'general':       { 'baja': 'alta',  'media': 'critica', 'alta': 'critica' }
    };

    return matriz[i][u] || 'media';
  }
  
}