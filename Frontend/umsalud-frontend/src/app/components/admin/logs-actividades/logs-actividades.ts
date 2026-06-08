import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { LogsActividadesService } from '../../../services/logs-actividades';

@Component({
  selector: 'app-logs-actividades',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './logs-actividades.html',
  styleUrl: './logs-actividades.css'
})
export class LogsActividadesComponent implements OnInit {
  // Variables de Estado
  logs: any[] = [];
  mapeos: any = { users: {}, areas: {}, categories: {}, ambientes: {} };
  paginacion: any = null;
  paginaActual = 1;
  cargando = false;

  // Filtros para incluir las nuevas acciones
  filtros = { action: '', tipo: '' };

  private logsService = inject(LogsActividadesService);
  private cdr = inject(ChangeDetectorRef);
  // Ciclo de vida
  ngOnInit(): void {
    this.cargarLogs();
  }
  /**==== Carga de logs ====*/ 
  cargarLogs(page: number = 1) {
    this.cargando = true;
    this.paginaActual = page;

    this.logsService.getLogs(page, this.filtros).subscribe({
      next: (res: any) => {
        this.logs = res.logs.data; 
        this.paginacion = res.logs;
        this.mapeos = res.mapeos;
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error en auditoría:', err);
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  /**==== Humanizar Valor====*/
  humanizarValor(campo: string, valor: any): string {
    if (valor === null || valor === undefined) return '---';
    // 1. Manejo de Estados (Ambientes y Reservas Unificados)
    if (campo === 'estado') {
        const diccionarioEstados: any = {
            // Estados de Ambiente
            'activo': '✅ Activo (Disponible)',
            'mantenimiento': '🛠️ En Mantenimiento',
            // Estados de Reserva
            'pendiente': '⏳ Pendiente de Revisión',
            'aprobada':  '✅ Aprobada / Confirmada',
            'rechazada': '❌ Rechazada por Admin',
            'cancelada': '🚫 Cancelada por Usuario',
            'finalizada': '🏁 Concluida',
            // Estados de Usuario
            'aprobado': 'Aprobado',
            'rechazado': 'Rechazado'
        };
        return diccionarioEstados[valor] || valor.toString().toUpperCase();
    }

    // 2. Mapeo de IDs de Usuarios (Incluyendo aprobadores/procesadores)
    const camposUsuario = [
        'assigned_to', 'assigned_by_id', 'user_id', 
        'closed_by_id', 'colaborador_id', 'created_by',
        'approved_by_id', 'rejected_by_id', 'processed_by_id'
    ];
    if (camposUsuario.includes(campo)) {
      return this.mapeos.users[valor.toString()] || `ID: ${valor}`;
    }

    // 3. Mapeo de otras entidades
    if (campo === 'area_id') return this.mapeos.areas[valor.toString()] || `Área ID: ${valor}`;
    if (campo === 'category_id') return this.mapeos.categories[valor.toString()] || `Categoría ID: ${valor}`;
    if (campo === 'ambiente_id') return this.mapeos.ambientes[valor.toString()] || `Ambiente ID: ${valor}`;

    // 4. Formateo de Atributos Técnicos
    if (campo === 'active') return valor ? 'Activo (Visible)' : 'Inactivo (Oculto)';
    if (campo === 'capacidad') return valor + ' personas';
    if (campo === 'peso_bytes') {
        const bytes = Number(valor);
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(2) + ' MB' : (bytes / 1024).toFixed(2) + ' KB';
    }

    // 5. Manejo de Fechas y Enums
    if (campo.includes('_at') || campo === 'inicio' || campo === 'fin') {
        return new Date(valor).toLocaleString('es-ES');
    }
    // 6. Formateo de Campos Específicos
    if (['status', 'priority', 'impacto', 'urgencia', 'tipo', 'visibilidad'].includes(campo)) {
        return valor.toString().replace('_', ' ').toUpperCase();
    }

    return valor;
  }
  /**==== Formatear Entidad ====*/ 
  formatearEntidad(tipo: string): string {
    if (!tipo) return 'Sistema';
    const mapeoEntidades: any = {
        'Ticket': 'Tickets',
        'User': 'Usuarios',
        'Area': 'Área / Infraestructura',
        'SolicitudRegistro': 'Solicitud de Acceso',
        'Category': 'Categorías',
        'Repositorio': 'Repositorio',
        'Ambiente': 'Ambientes',
        'Reserva': 'Reserva de Espacio'
    };

    const encontrado = Object.keys(mapeoEntidades).find(k => tipo.includes(k));
    return encontrado ? mapeoEntidades[encontrado] : (tipo.split('\\').pop() || tipo);
  }

  /**==== Obtener Clase de Badge (Acción) ====*/ 
  getBadgeClass(accion: string): string {
    const clases: any = {
      'created': 'bg-success-subtle text-success border-success',
      'updated': 'bg-warning-subtle text-warning-emphasis border-warning',
      'deleted': 'bg-danger-subtle text-danger border-danger',
      'approved': 'bg-info-subtle text-info-emphasis border-info',
      'rejected': 'bg-dark-subtle text-dark border-dark'
    };
    return clases[accion] || 'bg-light text-dark border';
  }

  /**==== Obtener Campos Cambiados ====*/ 
  obtenerCamposCambiados(log: any): string[] {
    const data = log.new_values || log.old_values || {};
    const ignorar = ['updated_at', 'created_at', 'id', 'deleted_at'];
    return Object.keys(data).filter(k => !ignorar.includes(k));
  }
}