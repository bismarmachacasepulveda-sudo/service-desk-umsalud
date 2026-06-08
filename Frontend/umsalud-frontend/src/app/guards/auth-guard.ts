// auth.guard.ts
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

export const authGuard: CanActivateFn = (route, state) => {
  const router = inject(Router);
  const token = localStorage.getItem('token');

  // Verificación estricta: que exista y que no sea un string "null" o "undefined"
  if (token && token !== 'null' && token !== 'undefined') {
    return true; 
  }

  // Si no hay token real, redirigimos
  console.warn('Acceso denegado: Redirigiendo a login...');
  router.navigate(['/login']);
  return false;
};