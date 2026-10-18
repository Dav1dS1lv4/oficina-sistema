import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface MaintenancePartLine {
  part_id?: number;
  name?: string;
  quantity: number;
  unit_price: number;
}

export interface MaintenanceRecord {
  id: number;
  vehicle_id: number;
  license_plate: string;
  brand: string;
  model: string;
  client_name: string;
  user_id: number;
  user_name: string;
  type: string;
  service_date: string;
  start_time: string | null;
  end_time: string | null;
  hourly_rate: number;
  labor_hours: number;
  cost: number;          // mão de obra calculada
  subtotal: number;
  tax_amount: number;
  total_amount: number;
  notes: string | null;
  payment_reference: string | null;
  payment_status: 'Pendente' | 'Pago' | null;
  last_cancelled_reference: string | null;
  edit_count: number;
  parts?: Array<{ part_id: number; part_name: string; quantity_used: number; unit_price: number }>;
}

export interface PaginatedMaintenance {
  data: MaintenanceRecord[];
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
export class MaintenanceService {
  private apiUrl = 'http://localhost/oficina-backend/api/maintenance';

  constructor(private http: HttpClient) {}

  getMaintenance(page: number = 1, perPage: number = 10, search: string = ''): Observable<PaginatedMaintenance> {
    let url = `${this.apiUrl}?page=${page}&per_page=${perPage}`;
    if (search) {
      url += `&search=${encodeURIComponent(search)}`;
    }
    return this.http.get<PaginatedMaintenance>(url);
  }

  createMaintenance(data: {
    vehicle_id: number;
    type: string;
    service_date: string;
    start_time?: string;
    end_time?: string;
    hourly_rate: number;
    notes?: string;
    parts: MaintenancePartLine[];
  }): Observable<any> {
    return this.http.post(this.apiUrl, data);
  }

  deleteMaintenance(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}