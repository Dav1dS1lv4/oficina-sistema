// src/app/core/services/user.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface OficinaUser {
  id: number;
  name: string;
  email: string;
  nif: string | null;
  active: number;
  role_id: number;
  role_name: string;
  created_at: string;
}

export interface PaginatedUsers {
  data: OficinaUser[];
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
export class UserService {
  private apiUrl = 'http://localhost/oficina-backend/api/users';

  constructor(private http: HttpClient) {}

  getUsers(page: number = 1, perPage: number = 10, search: string = ''): Observable<PaginatedUsers> {
    let url = `${this.apiUrl}?page=${page}&per_page=${perPage}`;
    if (search) {
      url += `&search=${encodeURIComponent(search)}`;
    }
    return this.http.get<PaginatedUsers>(url);
  }

  createUser(data: { name: string; email: string; password: string; role_id: number; nif?: string }): Observable<any> {
    return this.http.post(this.apiUrl, data);
  }

  updateUser(id: number, data: { name: string; email: string; role_id: number; password?: string; nif?: string }): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, data);
  }

  toggleActive(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/toggle-active`, {});
  }

  deleteUser(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}