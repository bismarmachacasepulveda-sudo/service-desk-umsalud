import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common'; // Importante para el pipe async
import { Router } from '@angular/router';
import { NotificationService } from '../../services/notification';

@Component({
  selector: 'app-notification',
  standalone: true,
  imports: [CommonModule], 
  templateUrl: './notification.html',
  styleUrls: ['./notification.css']
})
export class NotificationComponent implements OnInit {
  
  notifications: any[] = [];
  isOpen = false;

  private notiService = inject(NotificationService);
  private router = inject(Router);

  // 1. Creamos una referencia directa al Observable del servicio
  unreadCount$ = this.notiService.unreadCount$; 
esMensajeChat(noti: any): boolean {
    // Laravel guarda los datos custom dentro de noti.data
    // Verificamos si definimos el tipo 'chat' en el backend o si la clase de la noti contiene 'Message'
    return noti.data.type === 'chat' || noti.type.includes('Message');
  }
  ngOnInit() {
    // 2. Eliminamos el .subscribe() manual de unreadCount.
    // Solo llamamos a refrescar el dato inicial.
    this.notiService.refreshCount();
    this.cargarLista();
  }

  toggleMenu() {
    this.isOpen = !this.isOpen;
    if (this.isOpen) {
        // Opcional: Al abrir, podrías querer resetear el contador visualmente o traer la lista nueva
        this.cargarLista();
    }
  }

  cargarLista() {
    this.notiService.getNotifications().subscribe(data => {
        this.notifications = data;
        // Ojo: Asegúrate que al traer la lista, no estés sobreescribiendo el contador incorrectamente en el servicio
    });
  }

  irANotificacion(noti: any) {
    if (!noti.read_at) {
        this.notiService.markAsRead(noti.id).subscribe(() => {
            this.notiService.refreshCount();
            noti.read_at = new Date();
        });
    }
    this.isOpen = false;
    this.router.navigateByUrl(noti.data.link);
  }

  marcarTodas() {
    this.notiService.markAllRead().subscribe(() => {
        this.notifications.forEach(n => n.read_at = new Date());
        this.notiService.refreshCount();
    });
  }
  getIcono(noti: any): string {
    if (this.esMensajeChat(noti)) return 'bi-chat-dots-fill text-primary';
    if (noti.data.type === 'alert') return 'bi-exclamation-circle-fill text-danger';
    return 'bi-info-circle-fill text-info'; // Default
  }
}