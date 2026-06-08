import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AreaManagement } from './area-management';

describe('AreaManagement', () => {
  let component: AreaManagement;
  let fixture: ComponentFixture<AreaManagement>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AreaManagement]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AreaManagement);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
