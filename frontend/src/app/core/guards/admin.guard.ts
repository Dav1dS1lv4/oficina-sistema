// src/app/core/guards/admin.guard.ts
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Bloqueia o acesso a rotas exclusivas de Administrador.
 * Diferente do authGuard (que só confirma login), este confirma a role.
 */
export const adminGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const user = authService.currentUser();

  if (user && user.role === 'Administrador') {
    return true;
  }

  router.navigate(['/dashboard']);
  return false;
};