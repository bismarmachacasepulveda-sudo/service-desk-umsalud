import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AsistenteVirtual } from './asistente-virtual';

describe('AsistenteVirtual', () => {
  let component: AsistenteVirtual;
  let fixture: ComponentFixture<AsistenteVirtual>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AsistenteVirtual]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AsistenteVirtual);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
