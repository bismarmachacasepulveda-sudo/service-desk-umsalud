import { Component, OnInit, inject, ChangeDetectorRef, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { FullCalendarModule } from '@fullcalendar/angular';
import { CalendarOptions } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import { ReservaService } from '../../../services/reserva';
import { AmbienteService } from '../../../services/ambiente';
import { UserService } from '../../../services/user';

@Component({
  selector: 'app-calendario',
  standalone: true,
  imports: [CommonModule, FormsModule, FullCalendarModule],
  templateUrl: './calendario.html',
  styleUrl: './calendario.css'
})
export class CalendarioComponent implements OnInit {

  ambientes: any[] = [];
  reservasOriginales: any[] = [];
  filtroAmbiente: number | null = null; 
  
  // Modales
  mostrarModalCrear = false;
  mostrarModalDetalle = false;
  
  // Modelos
  nuevaReserva = { ambiente_id: null, inicio: '', fin: '', motivo: '' };
  reservaSeleccionada: any = null;
  
  // Seguridad y UI
  isLoading = false;
  isAdmin: boolean | null = null;
  currentUserId: number | null = null;

  private reservaService = inject(ReservaService);
  private ambienteService = inject(AmbienteService);
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);
  private zone = inject(NgZone);

  calendarOptions: CalendarOptions = {
    initialView: 'timeGridWeek', // 🟢 Sugerencia: Semana es mejor para ver choques de horas
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    locale: esLocale,
    allDaySlot: false,
    slotMinTime: '07:00:00', // Coincide con backend
    slotMaxTime: '21:00:00', // Coincide con backend
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    validRange: { start: new Date().toISOString().split('T')[0] },
    selectable: true,
    dayMaxEvents: true,
    
    dateClick: (info) => this.zone.run(() => this.abrirCrear({ startStr: info.dateStr, endStr: info.dateStr })),
    select: (info) => this.zone.run(() => this.abrirCrear(info)),
    eventClick: (info) => this.zone.run(() => this.abrirDetalle(info)),
    events: [] 
  };

  ngOnInit() {
    this.checkRole();
    this.cargarAmbientes();
  }

  checkRole() {
    this.cdr.detectChanges();
    this.userService.getCurrentUser().subscribe(user => {
        this.isAdmin = user.role === 'admin';
        this.currentUserId = user.id;
        this.cargarReservas();
    });
  }

  cargarAmbientes() {
    this.ambienteService.getAmbientes().subscribe(data => {
      // Solo ambientes activos para nuevas reservas
      this.ambientes = data.filter(a => a.estado === 'activo' || this.isAdmin);
      this.cdr.markForCheck();
    });
  }

  cargarReservas() {
    this.cdr.detectChanges();
    this.reservaService.getReservas().subscribe(data => {
      this.reservasOriginales = data;
      this.aplicarFiltros();
    });
  }

  aplicarFiltros() {
    const filtradas = this.filtroAmbiente 
        ? this.reservasOriginales.filter((r: any) => r.ambiente_id === this.filtroAmbiente)
        : this.reservasOriginales;

    this.calendarOptions.events = filtradas.map((reserva: any) => {
        const esMio = reserva.user_id === this.currentUserId;
        
        return {
            id: reserva.id.toString(),
            title: `${reserva.ambiente?.nombre} - ${reserva.motivo}`,
            start: reserva.inicio,
            end: reserva.fin,
            // 🟢 Si no es mío y no soy admin, se ve gris (Privacidad)
            color: (!esMio && !this.isAdmin) ? '#adb5bd' : this.getColorEstado(reserva.estado),
            extendedProps: { 
                ...reserva,
                esMio: esMio
            }
        };
    });
    this.cdr.detectChanges();
  }

  // --- LÓGICA DE GESTIÓN ---

  abrirCrear(selectInfo: any) {
    this.mostrarModalCrear = true; 
    const base = selectInfo.startStr; 
    const hasTime = base.includes('T');
    this.cdr.detectChanges();
    this.nuevaReserva.inicio = hasTime ? base.slice(0, 16) : base + 'T08:00';
    this.nuevaReserva.fin = selectInfo.endStr && selectInfo.endStr !== base 
        ? selectInfo.endStr.slice(0, 16) 
        : (hasTime ? this.sumarHoras(base, 2) : base + 'T10:00');
    
    this.nuevaReserva.ambiente_id = this.filtroAmbiente as any; // Pre-seleccionar si hay filtro
    this.cdr.markForCheck();
  }

  abrirDetalle(clickInfo: any) {
      this.cdr.detectChanges();
      this.reservaSeleccionada = clickInfo.event.extendedProps;
      this.mostrarModalDetalle = true;
      this.cdr.detectChanges();
  }

  guardarReserva() {
    this.cdr.detectChanges();
    if (!this.nuevaReserva.ambiente_id) return alert('Seleccione un ambiente.');
    
    this.isLoading = true;
    this.reservaService.createReserva(this.nuevaReserva).subscribe({
        next: () => {
            this.cerrarModal();
            this.cargarReservas();
            this.isLoading = false;
        },
        error: (err) => {
            this.isLoading = false;
            alert(err.error.message || 'Error al reservar.');
        }
    });
  }

  // 🟢 Función unificada para aprobar/rechazar
  gestionarReserva(nuevoEstado: string) {
    this.cdr.detectChanges();
    let motivo = '';
    
    if (nuevoEstado === 'rechazada') {
        motivo = prompt('Por favor, ingrese el motivo del rechazo:') || '';
        if (!motivo) return alert('El motivo es obligatorio para rechazar.');
    }

    if (!confirm(`¿Cambiar estado a ${nuevoEstado.toUpperCase()}?`)) return;

    this.reservaService.cambiarEstado(this.reservaSeleccionada.id, { 
        estado: nuevoEstado, 
        motivo_rechazo: motivo 
    }).subscribe({
        next: () => {
            this.cerrarModal();
            this.cargarReservas();
        },
        error: (err) => alert(err.error.message)
    });
  }

  eliminarOCancelar() {
    this.cdr.detectChanges();
      const msj = this.reservaSeleccionada.esMio && this.reservaSeleccionada.estado === 'pendiente'
                  ? '¿Deseas cancelar tu solicitud de reserva?'
                  : '¿Mover esta reserva a la papelera?';
                  
      if (!confirm(msj)) return;

      this.reservaService.deleteReserva(this.reservaSeleccionada.id).subscribe({
          next: () => {
              this.cerrarModal();
              this.cargarReservas();
          },
          error: () => alert('No se pudo procesar la acción.')
      });
  }

  cerrarModal() {
    this.cdr.detectChanges();
    this.mostrarModalCrear = false;
    this.mostrarModalDetalle = false;
    this.reservaSeleccionada = null;
  }

  // --- HELPERS ---
  sumarHoras(fecha: string, hrs: number) { 
      let d = new Date(fecha); 
      d.setHours(d.getHours() + hrs); 
      return d.toISOString().slice(0, 16);
  }
  
  getColorEstado(estado: string) {
      const colors: any = {
        'aprobada': '#198754',   // Verde
        'rechazada': '#dc3545',  // Rojo
        'pendiente': '#ffc107',  // Amarillo/Ámbar
        'cancelada': '#6c757d',  // Gris
        'finalizada': '#0d6efd'  // Azul
      };
      return colors[estado] || '#3788d8';
  }
}