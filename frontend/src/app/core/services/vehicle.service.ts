import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Vehicle {
  id: number;
  license_plate: string;
  brand: string;
  model: string;
  year: number;
  client_id: number;
  client_name: string;
  created_by: number | null;
  created_by_name: string | null;
  created_at: string;
}

export interface PaginatedVehicles {
  data: Vehicle[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

@Injectable({
  providedIn: 'root'
})
export class VehicleService {
  private apiUrl = 'http://localhost/oficina-backend/api/vehicles';

  constructor(private http: HttpClient) {}

  getVehicles(page: number = 1, perPage: number = 10, search: string = ''): Observable<PaginatedVehicles> {
    let url = `${this.apiUrl}?page=${page}&per_page=${perPage}`;
    if (search) {
      url += `&search=${encodeURIComponent(search)}`;
    }
    return this.http.get<PaginatedVehicles>(url);
  }

  createVehicle(data: { license_plate: string; brand: string; model: string; year: number; client_id: number }): Observable<any> {
    return this.http.post(this.apiUrl, data);
  }

  updateVehicle(id: number, data: { license_plate: string; brand: string; model: string; year: number; client_id: number }): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, data);
  }

  deleteVehicle(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}