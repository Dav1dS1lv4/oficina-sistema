import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Client {
  id: number;
  name: string;
  nif: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
}

@Injectable({
  providedIn: 'root'
})
export class ClientService {
  private apiUrl = 'http://localhost/oficina-backend/api/clients';

  constructor(private http: HttpClient) {}

  getClients(search: string = ''): Observable<{ data: Client[] }> {
    let url = this.apiUrl;
    if (search) {
      url += `?search=${encodeURIComponent(search)}`;
    }
    return this.http.get<{ data: Client[] }>(url);
  }

  createClient(data: { name: string; nif?: string; email?: string; phone?: string; address?: string }): Observable<any> {
    return this.http.post(this.apiUrl, data);
  }

  updateClient(id: number, data: { name: string; nif?: string; email?: string; phone?: string; address?: string }): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, data);
  }

  deleteClient(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}