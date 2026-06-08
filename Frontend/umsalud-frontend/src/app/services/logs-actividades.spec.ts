import { TestBed } from '@angular/core/testing';

import { LogsActividades } from './logs-actividades';

describe('LogActividades', () => {
  let service: LogsActividades;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(LogsActividades);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
