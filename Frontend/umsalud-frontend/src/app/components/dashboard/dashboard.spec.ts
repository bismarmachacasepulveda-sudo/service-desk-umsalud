import { ComponentFixture, TestBed } from '@angular/core/testing';
// ARREGLO 1: La clase exportada es DashboardComponent
import { DashboardComponent } from './dashboard';

describe('DashboardComponent', () => { // ARREGLO 2: El nombre del bloque debe coincidir
    let component: DashboardComponent;
    let fixture: ComponentFixture<DashboardComponent>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            // ARREGLO 3: El componente standalone debe ser la clase
            imports: [DashboardComponent] 
        })
        .compileComponents();

        fixture = TestBed.createComponent(DashboardComponent);
        component = fixture.componentInstance;
        await fixture.whenStable();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});