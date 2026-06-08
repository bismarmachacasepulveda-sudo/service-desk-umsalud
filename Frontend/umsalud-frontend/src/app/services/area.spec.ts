import { TestBed } from '@angular/core/testing';

import { Area } from './area';

describe('Area', () => {
  let service: Area;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Area);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
