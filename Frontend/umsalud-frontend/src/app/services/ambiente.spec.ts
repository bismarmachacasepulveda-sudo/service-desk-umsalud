import { TestBed } from '@angular/core/testing';

import { Ambiente } from './ambiente';

describe('Ambiente', () => {
  let service: Ambiente;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Ambiente);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
