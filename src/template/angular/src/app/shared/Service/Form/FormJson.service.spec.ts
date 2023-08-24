/* tslint:disable:no-unused-variable */

import { TestBed, async, inject } from '@angular/core/testing';
import { FormJsonService } from './FormJson.service';

describe('Service: FormJson', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [FormJsonService]
    });
  });

  it('should ...', inject([FormJsonService], (service: FormJsonService) => {
    expect(service).toBeTruthy();
  }));
});
