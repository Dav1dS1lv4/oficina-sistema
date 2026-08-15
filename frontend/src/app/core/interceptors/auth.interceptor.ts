import { HttpInterceptorFn } from '@angular/common/http';

/**
 * Interceta TODOS os pedidos HTTP feitos pela aplicação e, se existir um token
 * guardado, adiciona automaticamente o cabeçalho "Authorization: Bearer <token>".
 * Assim não precisamos de repetir esse código em cada chamada à API.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = localStorage.getItem('token');

  if (token) {
    const clonedRequest = req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`
      }
    });
    return next(clonedRequest);
  }

  return next(req);
};
