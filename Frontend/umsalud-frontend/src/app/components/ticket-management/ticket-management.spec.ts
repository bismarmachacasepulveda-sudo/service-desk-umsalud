import { ComponentFixture, TestBed } from '@angular/core/testing';
// CORRECCIÓN: El nombre de la clase suele terminar en Component
import { TicketManagementComponent } from './ticket-management';
import { HttpClientTestingModule } from '@angular/common/http/testing'; // Necesario para servicios HTTP

describe('TicketManagementComponent', () => {
  let component: TicketManagementComponent;
  let fixture: ComponentFixture<TicketManagementComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      // Importamos el componente y el módulo de testing HTTP
      imports: [TicketManagementComponent, HttpClientTestingModule] 
    })
    .compileComponents();

    fixture = TestBed.createComponent(TicketManagementComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
