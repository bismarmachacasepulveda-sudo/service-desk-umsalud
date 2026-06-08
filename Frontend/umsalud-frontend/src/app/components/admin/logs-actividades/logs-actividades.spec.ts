import { ComponentFixture, TestBed } from '@angular/core/testing';

import { LogsActividades } from './logs-actividades';

describe('LogsActividades', () => {
  let component: LogsActividades;
  let fixture: ComponentFixture<LogsActividades>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LogsActividades]
    })
    .compileComponents();

    fixture = TestBed.createComponent(LogsActividades);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
