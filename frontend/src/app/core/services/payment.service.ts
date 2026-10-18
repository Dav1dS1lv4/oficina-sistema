import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface PaymentPartLine {
  part_id: number;
  part_name: string;
  quantity_used: number;
  unit_price: number;
}

export interface Payment {
  id: number;
  reference: string;
  maintenance_id: number;
  status: 'Pendente' | 'Pago' | 'Anulado';
  payment_method: string | null;
  paid_at: string | null;
  created_at: string;
  issued_by_name: string | null;

  // Preenchidos quando o pagamento é anulado
  cancelled_at: string | null;
  cancelled_by_name: string | null;
  cancel_reason: string | null;

  maintenance_type: string;
  service_date: string;
  start_time: string | null;
  end_time: string | null;
  hourly_rate: number;
  labor_hours: number;
  labor_cost: number;
  subtotal: number;
  tax_amount: number;
  total_amount: number;
  notes: string | null;

  license_plate: string;
  brand: string;
  model: string;
  year: number;

  client_name: string;
  client_nif: string | null;
  client_email: string | null;
  client_phone: string | null;
  client_address: string | null;

  parts?: PaymentPartLine[];
}

export interface PaginatedPayments {
  data: Payment[];
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
export class PaymentService {
  private apiUrl = 'http://localhost/oficina-backend/api/payments';

  constructor(private http: HttpClient) {}

  getPayments(page: number = 1, perPage: number = 10, search: string = ''): Observable<PaginatedPayments> {
    let url = `${this.apiUrl}?page=${page}&per_page=${perPage}`;
    if (search) {
      url += `&search=${encodeURIComponent(search)}`;
    }
    return this.http.get<PaginatedPayments>(url);
  }

  getByReference(reference: string): Observable<{ data: Payment }> {
    return this.http.get<{ data: Payment }>(`${this.apiUrl}/ref/${encodeURIComponent(reference)}`);
  }

  createPayment(maintenanceId: number): Observable<{ message: string; data: Payment }> {
    return this.http.post<{ message: string; data: Payment }>(this.apiUrl, { maintenance_id: maintenanceId });
  }

  markPaid(reference: string, paymentMethod: string, paidAt: string): Observable<{ data: Payment }> {
    return this.http.patch<{ data: Payment }>(
      `${this.apiUrl}/ref/${encodeURIComponent(reference)}/pay`,
      { payment_method: paymentMethod, paid_at: paidAt }
    );
  }

  reopen(reference: string): Observable<{ data: Payment }> {
    return this.http.patch<{ data: Payment }>(`${this.apiUrl}/ref/${encodeURIComponent(reference)}/reopen`, {});
  }

  cancel(reference: string, reason: string): Observable<{ data: Payment }> {
    return this.http.patch<{ data: Payment }>(
      `${this.apiUrl}/ref/${encodeURIComponent(reference)}/cancel`,
      { reason }
    );
  }
}