import { Component, OnInit,OnDestroy, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { DashboardService } from '../../services/dashboard'; 
import { CategoryService } from '../../services/category';
import { BaseChartDirective } from 'ng2-charts';
import ChartDataLabels from 'chartjs-plugin-datalabels';
import {Chart, ChartConfiguration, ChartData, ChartType } from 'chart.js';
import { EchoService } from '../../services/echo';


@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink, BaseChartDirective], 
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css'
})
export class DashboardComponent implements OnInit,OnDestroy{
  constructor() {
    // Registrar el plugin globalmente para este componente
    Chart.register(ChartDataLabels);
  }
  // Variables de Estado
  user: any = null;
  stats: any = null;
  categorias: any[] = [];
  isLoadingStats = true;
  today: Date = new Date();
  // --- CONFIGURACIÓN DE GRÁFICOS ---
  // 1. Gráfico de Barras: Tendencia Mensual
  public barChartOptions: ChartConfiguration['options'] = {
    responsive: true,
    plugins: { 
      legend: { display: false },
      datalabels: {
        anchor: 'end', // Posición al final de la barra
        align: 'top',   // Alineado arriba
        formatter: (value) => value + ' tickets', // Texto que se mostrará
        font: { weight: 'bold', size: 12 },
        color: '#003366' // Color del número
      },
      tooltip: { enabled: true }
    },
    scales: {
      y: { beginAtZero: true }
    }
  };
  // Tipo de gráfico y datos iniciales (vacíos)
  public barChartType: ChartType = 'bar';
  public barChartData: ChartData<'bar'> = { labels: [], datasets: [] };

  // 2. Gráfico de Dona: Distribución por Categoría
  public doughnutChartOptions: ChartConfiguration['options'] = {
    responsive: true,
    plugins: {
      legend: { position: 'bottom' },
      datalabels: {
        color: '#fff', // Texto blanco para que resalte
        font: { weight: 'bold', size: 14 },
        formatter: (value, ctx) => {
          // Si el valor es muy pequeño, no mostrarlo para no amontonar
          return value > 0 ? value : ''; 
        }
      }
    }
  };
  public doughnutChartType: ChartType = 'doughnut'; // Tipo de gráfico
  public doughnutChartData: ChartData<'doughnut'> = { labels: [], datasets: [] };// Datos iniciales vacíos
  private router = inject(Router);
  private dashboardService = inject(DashboardService);
  private catService = inject(CategoryService);
  private cdr = inject(ChangeDetectorRef);
  private echo = inject(EchoService);

  ngOnInit() {
    const userString = localStorage.getItem('user');
    if (userString) {
      this.user = JSON.parse(userString);
      this.loadStats();
      this.iniciarWebSocket();
      // Si es usuario, cargamos categorías para su RAG personal
      if (this.user.role === 'usuario') {
          this.loadCategories(); 
      }
    } else {
      this.router.navigate(['/login']);
    }
  }
  /**=== Cargar estadísticas ===*/
  loadStats() {
    this.isLoadingStats = true;
    this.dashboardService.getStats().subscribe({
      next: (data) => {
        this.stats = data;
        // Configuramos gráficos solo si el usuario es Admin
        if (this.user.role === 'admin') {
            this.setupCharts(data);
        }
        this.isLoadingStats = false;
        this.cdr.detectChanges(); 
      },
      error: (err) => {
        console.error('Error cargando estadísticas', err);
        this.isLoadingStats = false;
        this.cdr.detectChanges();
      }
    });
  }
  /**==== Cargar categorías ====*/ 
  loadCategories() {
    this.catService.getCategories('repositorio').subscribe({
        next: (data) => {
            this.categorias = data;
            this.cdr.detectChanges();
        },
        error: (err) => console.error('Error cargando categorías', err)
    });
  }
  /**==== Configurar gráficos ====*/ 
  setupCharts(data: any) {
  // 1. Gráfico de Barras (Tendencia Mensual)
  // tendencia_mensual viene con formato: [{ mes: "2026-02", total: 20 }, ...]
  if (data.tendencia_mensual && data.tendencia_mensual.length > 0) {
    this.barChartData = {
      labels: data.tendencia_mensual.map((d: any) => this.formatearMes(d.mes)),
      datasets: [{ 
        data: data.tendencia_mensual.map((d: any) => d.total), 
        label: 'Incidencias', 
        backgroundColor: '#003366', // Azul Medianoche Institucional
        hoverBackgroundColor: '#004080',
        borderRadius: 5,
        borderWidth: 1
      }]
    };
  }

  // 2. Gráfico de Dona (Categorías)
  // Usamos 'tickets_por_categoria'
  if (data.tickets_por_categoria && data.tickets_por_categoria.length > 0) {
    this.doughnutChartData = {
      labels: data.tickets_por_categoria.map((d: any) => d.category?.name || 'General'),
      datasets: [{ 
        data: data.tickets_por_categoria.map((d: any) => d.total),
        backgroundColor: [
          '#800000', // Guindo UMSALUD
          '#003366', // Azul
          '#f59e0b', // Naranja
          '#10b981', // Verde
          '#6366f1'  // Indigo
        ],
        hoverOffset: 10
      }]
    };
  }
  this.cdr.detectChanges();
}

  // Helper para convertir "2026-02" a "Feb"
  formatearMes(mesString: string): string {
    const [year, month] = mesString.split('-');
    const date = new Date(parseInt(year), parseInt(month) - 1);
    return date.toLocaleString('es-ES', { month: 'short' }).toUpperCase();
  }
  /**==== Obtener icono por extensión ====*/ 
  obtenerIcono(ext: string): string {
    const e = (ext || '').toLowerCase();
    if (e === 'pdf') return 'bi-file-earmark-pdf text-danger';
    if (e === 'doc' || e === 'docx') return 'bi-file-earmark-word text-primary';
    if (e === 'xls' || e === 'xlsx' || e === 'csv') return 'bi-file-earmark-excel text-success';
    if (['jpg', 'jpeg', 'png', 'webp'].includes(e)) return 'bi-file-earmark-image text-info';
    if (['zip', 'rar'].includes(e)) return 'bi-file-earmark-zip text-warning';
    return 'bi-file-earmark-text text-secondary';
  }

  /**==== Método para redirección rápida ====*/ 
  navegar(ruta: string) {
    this.router.navigate([ruta]);
  }
  /**==== Iniciar WebSocket ====*/ 
  iniciarWebSocket() {
    // 1. Unirse al canal privado 'dashboard'
    this.echo.join('dashboard');
    // 2. Escuchar el evento 'stats.updated'
    this.echo.listen('dashboard', 'stats.updated', (e: any) => {
        console.log('🔄 Cambio detectado en el sistema. Actualizando Dashboard...');
        this.loadStats(); //Recarga los datos solo
    });
  }

  // IMPORTANTE: Desconectar al salir de la página para ahorrar recursos
  ngOnDestroy() {
    this.echo.leave('dashboard');
  }
}