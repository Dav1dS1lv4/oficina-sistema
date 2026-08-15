import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { Router } from '@angular/router';

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface LoginResponse {
  message: string;
  token: string;
  user: User;
}

interface RegisterResponse {
  message: string;
  user_id: number;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  // Base da API. Ajusta se o caminho do teu backend for diferente.
  private apiUrl = 'http://localhost/oficina-backend/api/auth';

  // signal() é uma forma moderna do Angular de guardar estado reativo.
  // Qualquer componente que use currentUser() é atualizado automaticamente quando isto muda.
  currentUser = signal<User | null>(null);

  constructor(private http: HttpClient, private router: Router) {
    this.loadUserFromStorage();
  }

  register(name: string, email: string, password: string): Observable<RegisterResponse> {
    return this.http.post<RegisterResponse>(`${this.apiUrl}/register`, { name, email, password });
  }

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, { email, password }).pipe(
      tap(response => {
        // Guarda o token no localStorage para sobreviver a recarregar a página.
        localStorage.setItem('token', response.token);
        localStorage.setItem('user', JSON.stringify(response.user));
        this.currentUser.set(response.user);
      })
    );
  }

  logout(): void {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    this.currentUser.set(null);
    this.router.navigate(['/login']);
  }

  forgotPassword(email: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/forgot-password`, { email });
  }

  resetPassword(token: string, password: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/reset-password`, { token, password });
  }

  getToken(): string | null {
    return localStorage.getItem('token');
  }

  isLoggedIn(): boolean {
    return !!this.getToken();
  }

  private loadUserFromStorage(): void {
    const storedUser = localStorage.getItem('user');
    if (storedUser) {
      this.currentUser.set(JSON.parse(storedUser));
    }
  }
}
