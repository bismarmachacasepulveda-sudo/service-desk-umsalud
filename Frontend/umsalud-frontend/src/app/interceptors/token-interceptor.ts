import { HttpInterceptorFn } from '@angular/common/http';

export const tokenInterceptor: HttpInterceptorFn = (req, next) => {
  // 1. Obtener el token guardado en el navegador
  const token = localStorage.getItem('token');
  
  // 2. Si hay token, clonar la petición e inyectar el header
  if (token) {
    const authReq = req.clone({
      headers: req.headers.set('Authorization', `Bearer ${token}`)
    });
    return next(authReq);
  }

  // 3. Si no hay token, enviar la petición original sin modificaciones
  return next(req);
};