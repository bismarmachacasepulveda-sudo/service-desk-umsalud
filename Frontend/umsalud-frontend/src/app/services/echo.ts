import { Injectable } from '@angular/core';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js'; // 🟢 Importación directa y limpia
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class EchoService {

  private echo: Echo<any> | undefined;

  constructor() {
    // Definimos Pusher globalmente de forma segura para Echo
    (window as any).Pusher = Pusher;

    this.echo = new Echo({
      broadcaster: 'reverb',
      key: environment.ws_key,
      wsHost: '192.168.1.50',
      wsPort: 8081,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: 'http://192.168.1.50:8000/api/broadcasting/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('token')}`
        }
      }
    });
  }
join(channelName: string) {
    if (!this.echo) {
        console.error('Echo no ha sido inicializado aún.');
        return null;
    }
    return this.echo.join(channelName);
}
  listen(channelName: string, eventName: string, callback: Function) {
    if (this.echo) {
      // Usamos el punto antes del nombre del evento si en Laravel usaste broadcastAs()
      return this.echo.private(channelName).listen(`.${eventName}`, (data: any) => callback(data));
    }
    return null;
  }

  leave(channelName: string) {
    if (this.echo) {
      this.echo.leave(channelName);
    }
  }
  
}