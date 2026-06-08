import { TestBed } from '@angular/core/testing';

import { Papelera } from './papelera';

describe('Papelera', () => {
  let service: Papelera;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Papelera);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
