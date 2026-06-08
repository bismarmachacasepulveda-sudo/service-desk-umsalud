import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TicketService } from '../../services/ticket';   
import { CategoryService } from '../../services/category'; 
import { RepositorioService } from '../../services/repositorio';
import { AreaService } from '../../services/area';
import { AmbienteService } from '../../services/ambiente';
import { ReservaService } from '../../services/reserva';
import { UserService } from '../../services/user';
import { Observable } from 'rxjs';

@Component({
  selector: 'app-papelera',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './papelera.html'
})
export class PapeleraComponent implements OnInit {
  
  // Control de pestañas
  tabActual: 'tickets' | 'areas' | 'categorias'| 'repositorio' | 'ambientes'| 'reservas'| 'usuarios' = 'tickets';
  
  // Datos
  itemsBorrados: any[] = [];
  cargando = false;

  private ticketService = inject(TicketService);
  private categoryService = inject(CategoryService);
  private repoService = inject(RepositorioService);
  private areaService = inject(AreaService);
  private ambienteService = inject(AmbienteService);
  private reservaService = inject(ReservaService);
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);

  ngOnInit(): void {
    // Carga inicial (Tickets)
    this.cargarDatos();
  }

  cambiarTab(tab: 'tickets' | 'areas' | 'repositorio' |'categorias'|'ambientes'| 'reservas'| 'usuarios') {
    this.tabActual = tab;
    this.itemsBorrados = []; // Limpiar vista anterior
    this.cargarDatos();
  }

cargarDatos() {
    this.cargando = true;
    this.cdr.detectChanges();

    // 🟢 Tipado explícito para evitar errores de "Subscribe does not exist on void"
    let obs: Observable<any[]>;

    if (this.tabActual === 'tickets') obs = this.ticketService.getTrashedTickets();
    else if (this.tabActual === 'categorias') obs = this.categoryService.getTrashedCategories();
    else if (this.tabActual === 'repositorio') obs = this.repoService.getTrashedFiles(); // Asegúrate que el servicio se llame así
    else if (this.tabActual === 'areas') obs = this.areaService.getTrashedAreas();
    else if (this.tabActual === 'ambientes') obs = this.ambienteService.getTrashedAmbientes();
    else if (this.tabActual === 'usuarios') obs = this.userService.getTrashedUsers();
    else obs = this.reservaService.getTrashedReservas();

    obs.subscribe({
        next: (data) => this.procesarRespuesta(data),
        error: () => this.procesarError()
    });
  }
  procesarRespuesta(data: any[]) {
    this.itemsBorrados = data;
    this.cargando = false;
    this.cdr.detectChanges();
  }

  procesarError() {
    this.cargando = false;
    alert('Error al cargar la papelera.');
    this.cdr.detectChanges();
  }

  // --- LÓGICA DE RESTAURACIÓN / ELIMINACIÓN ---

 restaurar(id: number) {
    if (!confirm(`¿Restaurar este registro de ${this.tabActual}?`)) return;

    // 🟢 Solución limpia con Observable dinámico (evita ternarios anidados confusos)
    let observable;
    if (this.tabActual === 'tickets') observable = this.ticketService.restoreTicket(id);
    else if (this.tabActual === 'categorias') observable = this.categoryService.restoreCategory(id);
    else if (this.tabActual === 'repositorio') observable = this.repoService.restoreFile(id);
    else if (this.tabActual === 'areas') observable = this.areaService.restoreArea(id);
    else if (this.tabActual === 'ambientes') observable = this.ambienteService.restoreAmbiente(id);
    else if (this.tabActual === 'reservas') observable = this.reservaService.restoreReserva(id);
    else if (this.tabActual === 'usuarios') observable = this.userService.restoreUser(id);
    else return;

    observable.subscribe(() => {
        this.itemsBorrados = this.itemsBorrados.filter(item => item.id !== id);
        alert('Registro restaurado correctamente.');
        this.cdr.detectChanges();
    });
  }

eliminarPermanente(id: number) {
    if (!confirm('⚠️ ACCIÓN IRREVERSIBLE: ¿Borrar físicamente de la base de datos?')) return;

    let observable;
    if (this.tabActual === 'tickets') observable = this.ticketService.permanentDeleteTicket(id);
    else if (this.tabActual === 'categorias') observable = this.categoryService.deleteCategoryPermanently(id);
    else if (this.tabActual === 'repositorio') observable = this.repoService.permanentDeleteFile(id);
    else if (this.tabActual === 'areas') observable = this.areaService.deleteAreaPermanently(id);
    else if (this.tabActual === 'ambientes') observable = this.ambienteService.deleteAmbientePermanently(id);
    else if (this.tabActual === 'reservas') observable = this.reservaService.deletePermanently(id);
    else if (this.tabActual === 'usuarios') observable = this.userService.deleteUserPermanently(id);
    else return;

observable.subscribe({
        next: () => {
            this.itemsBorrados = this.itemsBorrados.filter(item => item.id !== id);
            alert('Registro eliminado permanentemente.');
            this.cdr.detectChanges();
        },
        error: (err) => {
            // Manejo especial para errores de integridad (foreign keys)
            if (err.status === 409) {
                alert('⛔ ' + err.error.message);
            } else {
                alert('Error al eliminar.');
            }
        }
    });
  }
}