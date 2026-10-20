import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse, HealthData } from '../models/api-response';

@Injectable({ providedIn: 'root' })
export class HealthApiService {
  private readonly http = inject(HttpClient);

  getHealth(): Observable<ApiResponse<HealthData>> {
    return this.http.get<ApiResponse<HealthData>>(`${environment.apiBaseUrl}/health`);
  }
}
