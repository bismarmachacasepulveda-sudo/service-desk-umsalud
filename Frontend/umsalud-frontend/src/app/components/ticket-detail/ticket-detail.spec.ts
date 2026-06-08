import { ComponentFixture, TestBed } from '@angular/core/testing';
// 1. CORRECCIÓN: El nombre correcto es TicketDetailComponent
import { TicketDetailComponent } from './ticket-detail'; 
import { HttpClientTestingModule } from '@angular/common/http/testing'; // Necesario para servicios HTTP
import { RouterTestingModule } from '@angular/router/testing'; // Necesario para rutas

describe('TicketDetailComponent', () => {
  let component: TicketDetailComponent;
  let fixture: ComponentFixture<TicketDetailComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      // 2. Importamos módulos de prueba para que no fallen las inyecciones
      imports: [TicketDetailComponent, HttpClientTestingModule, RouterTestingModule] 
    })
    .compileComponents();

    fixture = TestBed.createComponent(TicketDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});