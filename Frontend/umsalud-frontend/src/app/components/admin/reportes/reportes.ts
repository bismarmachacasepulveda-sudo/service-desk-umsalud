import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { UserService } from '../../../services/user'; 
import { HttpClient } from '@angular/common/http';
@Component({
  selector: 'app-reportes',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './reportes.html',
  styleUrl: './reportes.css'
})
export class ReportesComponent implements OnInit {
  
  // Variables de Formulario
  fechaInicio: string = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  fechaFin: string = new Date().toISOString().split('T')[0];
  tipoReporte: string = 'actividades';
  tecnicoId: number | null = null;
  
  // Variables de Estado
  isAdmin = false;
  currentUserId: number | null = null;
  tecnicos: any[] = [];
  isGenerating: boolean = false; 
  isLoadingData: boolean = true; 
  isExporting = false;
  nombreDecano: string = '';
  nombreJefe: string = '';
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);
  private http = inject(HttpClient);
  /**==== Ciclo de vida ====*/ 
  ngOnInit() {
    this.userService.getCurrentUser().subscribe({
        next: (user) => {
            this.isAdmin = user.role === 'admin';
            this.currentUserId = user.id;
            
            if (this.isAdmin) {
                this.cargarTecnicos();
            }
            
            this.isLoadingData = false;
            this.cdr.detectChanges();
        },
        error: (err) => {
            console.error(err);
            this.isLoadingData = false;
            this.cdr.detectChanges();
        }
    });
  }
  /**==== Carga de técnicos ====*/ 
  cargarTecnicos() {
    this.userService.getUsers().subscribe(users => {
        this.tecnicos = users.filter((u: any) => u.role === 'tecnico');
        this.cdr.detectChanges();
    });
  }
  /**==== Generar reporte ====*/ 
  generarReporte() {
    this.isGenerating = true;
    // ENVIAMOS LOS NOMBRES Y EL ID DEL GENERADOR EN LA URL
    // Usamos encodeURIComponent para que los espacios y puntos no rompan el link
    const params = [
        `fecha_inicio=${this.fechaInicio}`,
        `fecha_fin=${this.fechaFin}`,
        `tipo=${this.tipoReporte}`,
        `generador_id=${this.currentUserId}`,
        `nombre_decano=${encodeURIComponent(this.nombreDecano)}`,
        `nombre_jefe=${encodeURIComponent(this.nombreJefe)}`
    ];

    let url = `http://192.168.1.50:8000/api/reportes/generar?${params.join('&')}`;
    
    if (this.isAdmin && this.tecnicoId && this.tipoReporte === 'actividades') {
        url += `&tecnico_id=${this.tecnicoId}`;
    }
    // Usar HttpClient con responseType 'blob'
    // Esto asegura que el Token viaje en la cabecera (gracias a tu Interceptor de Auth)
    this.http.get(url, { responseType: 'blob' }).subscribe({
        next: (blob) => {
            // Creamos una URL temporal para el archivo descargado
            const fileUrl = URL.createObjectURL(blob);
            // Abrimos el PDF en una nueva pestaña
            window.open(fileUrl, '_blank');
            this.isGenerating = false;
            this.cdr.detectChanges();
        },
        error: (err) => {
            console.error('Error generando reporte:', err);
            alert('Error al generar el reporte. Verifique su sesión.');
            this.isGenerating = false;
            this.cdr.detectChanges();
        }
    });
  }
  /**==== Descargar Excel ====*/ 
  descargarExcel() {
    this.isExporting = true;

    // Construir URL (igual que arriba)
    let baseUrl = `http://192.168.1.50:8000/api/reportes/excel?fecha_inicio=${this.fechaInicio}&fecha_fin=${this.fechaFin}&tipo=${this.tipoReporte}&generador_id=${this.currentUserId}`;
    
    if (this.isAdmin && this.tecnicoId && this.tipoReporte === 'actividades') {
        baseUrl += `&tecnico_id=${this.tecnicoId}`;
    }

    //PARA EXCEL
    this.http.get(baseUrl, { responseType: 'blob' }).subscribe({
        next: (blob) => {
            //para forzar la descarga del archivo Excel
            const a = document.createElement('a');
            const objectUrl = URL.createObjectURL(blob);
            a.href = objectUrl;
            a.download = `Reporte_${this.tipoReporte}_${this.fechaInicio}.xlsx`; // Nombre del archivo
            a.click();
            URL.revokeObjectURL(objectUrl);// Limpiar URL temporal
            
            this.isExporting = false;
            this.cdr.detectChanges();
        },
        error: (err) => {
            console.error('Error descargando Excel:', err);
            alert('Error al descargar Excel.');
            this.isExporting = false;
            this.cdr.detectChanges();
        }
    });
  }
}