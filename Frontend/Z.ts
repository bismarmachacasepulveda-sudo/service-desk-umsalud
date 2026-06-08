server {
    listen 80;
    server_name soporte.umsalud.bo;

    # Dirección de los archivos estáticos del Frontend (Angular)
    location / {
        root /var/www/public/dist/browser;
        try_files $uri $uri/ /index.html;
    }

    # Desvío de peticiones de API hacia el Backend (Laravel)
    location /api {
        proxy_pass http://app:8000; # Redirección interna de Docker
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}