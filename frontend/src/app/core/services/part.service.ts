import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Part {
  id: number;
  name: string;
  category: string | null;
  quantity: number;
  price: number;
  supplier: string | null;
  low_stock_threshold: number;
}

export interface PaginatedParts {
  data: Part[];
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
export class PartService {
  private apiUrl = 'http://localhost/oficina-backend/api/parts';

  constructor(private http: HttpClient) {}

  getParts(page: number = 1, perPage: number = 10, search: string = ''): Observable<PaginatedParts> {
    let url = `${this.apiUrl}?page=${page}&per_page=${perPage}`;
    if (search) {
      url += `&search=${encodeURIComponent(search)}`;
    }
    return this.http.get<PaginatedParts>(url);
  }

  createPart(data: { name: string; category?: string; quantity: number; price: number; supplier?: string; low_stock_threshold?: number }): Observable<any> {
    return this.http.post(this.apiUrl, data);
  }

  updatePart(id: number, data: { name: string; category?: string; quantity: number; price: number; supplier?: string; low_stock_threshold?: number }): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, data);
  }

  deletePart(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}