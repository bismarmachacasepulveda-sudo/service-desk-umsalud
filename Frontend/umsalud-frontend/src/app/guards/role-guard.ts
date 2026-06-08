// role.guard.ts
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

export const roleGuard: CanActivateFn = (route, state) => {
  const router = inject(Router);
  const userJson = localStorage.getItem('user');
  
  if (!userJson) {
    router.navigate(['/login']);
    return false;
  }

  const user = JSON.parse(userJson);
  const expectedRoles = route.data['roles'] as Array<string>;

  // Verificamos si el rol del usuario está en la lista de roles permitidos de la ruta
  if (!expectedRoles.includes(user.role)) {
    router.navigate(['/dashboard']); // O a una página de "No autorizado"
    return false;
  }

  return true;
};