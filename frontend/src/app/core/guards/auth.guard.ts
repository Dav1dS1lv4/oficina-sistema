import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Impede o acesso a rotas protegidas se o utilizador não tiver token guardado.
 * Usado no app.routes.ts, ex: { path: 'dashboard', canActivate: [authGuard], ... }
 */
export const authGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (authService.isLoggedIn()) {
    return true;
  }

  router.navigate(['/login']);
  return false;
};
