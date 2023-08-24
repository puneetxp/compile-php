/* tslint:disable:no-unused-variable */

import { TestBed, async, inject } from '@angular/core/testing';
import { FormDataService } from './FormData.service';

describe('Service: FormData', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [FormDataService]
    });
  });

  it('should ...', inject([FormDataService], (service: FormDataService) => {
    expect(service).toBeTruthy();
  }));
});
