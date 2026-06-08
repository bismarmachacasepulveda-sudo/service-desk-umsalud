import { Component, inject, ChangeDetectorRef } from '@angular/core'; // <-- Importamos ChangeDetectorRef
import { CommonModule } from '@angular/common';
// ASEGÚRATE QUE ESTA IMPORTACIÓN SEA CORRECTA (con .service si tu archivo lo tiene)
import { IncidentService } from '../../services/incident'; 

@Component({
  selector: 'app-incidents',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './incidents.html',
  styleUrl: './incidents.css'
})
export class IncidentsComponent {
  incidents: any[] = [];
  isLoading = true; // Empieza cargando
  
  private incidentService = inject(IncidentService);
  private cdr = inject(ChangeDetectorRef); // <-- Inyectamos el detector de cambios

  ngOnInit() {
    this.loadIncidents();
  }

  loadIncidents() {
    this.incidentService.getIncidents().subscribe({
      next: (data) => {
        console.log('✅ Datos recibidos en Angular:', data);
        
        // Asignamos los datos
        this.incidents = data;
        
        // Apagamos el cargando
        this.isLoading = false; 
        
        // FUERZA A ANGULAR A ACTUALIZAR LA PANTALLA
        this.cdr.detectChanges(); 
      },
      error: (err) => {
        console.error('❌ Error en Angular:', err);
        
        // Incluso si falla, apagamos el cargando
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }
  
  getPriorityClass(priority: string): string {
    switch(priority) {
      case 'alta': return 'badge bg-danger';
      case 'media': return 'badge bg-warning text-dark';
      case 'baja': return 'badge bg-success';
      default: return 'badge bg-secondary';
    }
  }
}