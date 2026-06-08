import { TestBed } from '@angular/core/testing';

import { Repositorio } from './repositorio';

describe('Repositorio', () => {
  let service: Repositorio;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Repositorio);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
