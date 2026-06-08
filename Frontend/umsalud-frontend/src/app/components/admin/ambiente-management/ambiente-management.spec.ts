import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AmbienteManagement } from './ambiente-management';

describe('AmbienteManagement', () => {
  let component: AmbienteManagement;
  let fixture: ComponentFixture<AmbienteManagement>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AmbienteManagement]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AmbienteManagement);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
