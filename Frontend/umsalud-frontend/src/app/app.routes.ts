import { Routes } from '@angular/router';
import { LoginComponent } from './components/login/login'; 
import { DashboardComponent } from './components/dashboard/dashboard'; 
import { IncidentsComponent } from './components/incidents/incidents';
import { UsersComponent } from './components/users/users';
import { AreaManagementComponent } from './components/admin/area-management/area-management';
import { TicketManagementComponent } from './components/ticket-management/ticket-management';
import { TicketDetailComponent } from './components/ticket-detail/ticket-detail';
import { RepositorioComponent } from './components/repositorio/repositorio';
import { CategoryManagementComponent } from './components/admin/category-management/category-management';
import { ReportesComponent } from './components/admin/reportes/reportes';
import { AmbienteManagementComponent } from './components/admin/ambiente-management/ambiente-management';
import { CalendarioComponent } from './components/reservas/calendario/calendario';
import { RegistroComponent } from './components/registro/registro';
import { authGuard } from './guards/auth-guard';
import { roleGuard } from './guards/role-guard';
import { PerfilComponent } from './components/perfil/perfil';
import { PapeleraComponent } from './components/papelera/papelera';
import { LogsActividadesComponent } from './components/admin/logs-actividades/logs-actividades';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  { path: 'dashboard', component: DashboardComponent },
  { path: 'incidents', component: IncidentsComponent },
  { path: 'repositorio', component: RepositorioComponent },
  { path: '', redirectTo: 'login', pathMatch: 'full' }, 
  
  { path: 'users', component: UsersComponent }, 
  { path: 'areas-admin', component: AreaManagementComponent }, 
  { path: 'tickets', component: TicketManagementComponent,canActivate: [authGuard] }, 
  { path: 'tickets', component: TicketManagementComponent },
  { path: 'tickets/:id', component: TicketDetailComponent },
  { path: 'categorias', component: CategoryManagementComponent },
  { path: 'reportes', component: ReportesComponent},
  { path: 'ambientes', component: AmbienteManagementComponent },
  { path: 'reservas', component: CalendarioComponent },
  { path: 'registro', component: RegistroComponent },
  { path: 'perfil', component: PerfilComponent, },
  { path: 'papelera',component: PapeleraComponent, canActivate: [authGuard, roleGuard], data: { roles: ['admin']} },
  {path: 'admin/auditoria',component: LogsActividadesComponent,
    // canActivate: [AdminGuard] //
  },
]; 
