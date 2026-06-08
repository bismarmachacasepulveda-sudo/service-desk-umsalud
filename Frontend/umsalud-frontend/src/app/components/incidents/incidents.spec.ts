import { ComponentFixture, TestBed } from '@angular/core/testing';
// 1. NOMBRE CORREGIDO
import { IncidentsComponent } from './incidents';
import { HttpClientTestingModule } from '@angular/common/http/testing';

describe('IncidentsComponent', () => {
  let component: IncidentsComponent;
  let fixture: ComponentFixture<IncidentsComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      // 2. Módulos necesarios para pruebas
      imports: [IncidentsComponent, HttpClientTestingModule]
    })
    .compileComponents();

    fixture = TestBed.createComponent(IncidentsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});