import { Component, Input, OnInit, OnDestroy, inject, ElementRef, ViewChild, AfterViewChecked, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ChatService } from '../../services/chat';
import { UserService } from '../../services/user';
import { EchoService } from '../../services/echo';

@Component({
  selector: 'app-chat',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './chat.html',
  styleUrl: './chat.css'
})
export class ChatComponent implements OnInit, OnDestroy, AfterViewChecked {
  
  @Input() ticketId!: number;
  @ViewChild('scrollMe') private myScrollContainer!: ElementRef;
  // Variables de Estado
  mensajes: any[] = [];
  nuevoMensaje: string = '';
  errorMessage = ''

  // Información del usuario actual
  usuarioActual: any = null;
  usuarioActualId: number | null = null;
  // Variables para manejo de archivos
  archivoSeleccionado: File | null = null;
  cargando = true;
  enviando = false;
  
  private chatService = inject(ChatService);
  private userService = inject(UserService);
  private cdr = inject(ChangeDetectorRef);
  private echoService = inject(EchoService); // Para suscribirse al canal de Echo
  private presenceInterval: any; // Para almacenar el intervalo del pulso de presencia
  usuariosOnline: any[] = [];    // Para mostrar quién está en el chat
  /**==== Ciclo de vida ====*/
  ngOnInit(): void {
    this.userService.getCurrentUser().subscribe(user => {
      // Guardamos todo el usuario para usar su nombre/rol en el mensaje optimista
      this.usuarioActual = user;
      this.usuarioActualId = user.id;
      
      this.cargarMensajes();
      this.suscribirseAlCanal();
      this.iniciarPulsoPresencia();
    });
  }
  /**==== Suscribirse al canal de chat ====*/ 
  suscribirseAlCanal() {
    if (!this.ticketId) return;
    // Nos unimos al canal privado de Echo para este ticket
    this.echoService.join(`tickets.${this.ticketId}`)
      // Escuchamos quiénes están en el canal
      .here((users: any[]) => {
        this.usuariosOnline = users;
        this.cdr.detectChanges();
      })
      // Escuchamos eventos de quién entra y sale del canal
      .joining((user: any) => {
        // Se ejecuta cuando ALGUIEN MÁS entra
        this.usuariosOnline.push(user);
        this.cdr.detectChanges();
      })
      // Escuchamos eventos de quién sale del canal
      .leaving((user: any) => {
        // Se ejecuta cuando ALGUIEN SALE (o cierra la pestaña)
        this.usuariosOnline = this.usuariosOnline.filter(u => u.id !== user.id);
        this.cdr.detectChanges();
      })
      // Escuchamos eventos de nuevos mensajes
      .listen('.MessageSent', (data: any) => {
        if (data.chat.user_id === this.usuarioActualId) return;
        
        this.mensajes.push(data.chat);
        this.cdr.detectChanges();
        setTimeout(() => this.scrollToBottom(), 100);
      });
  }
  /**==== Iniciar pulso de presencia ====*/ 
  iniciarPulsoPresencia() {
    // 1. Enviamos el primer pulso de inmediato
    this.enviarPulso();

    // 2. Configuramos el intervalo cada 30 segundos
    this.presenceInterval = setInterval(() => {
      this.enviarPulso();
    }, 30000); 
  }
  /**==== Enviar pulso de presencia ====*/ 
  enviarPulso() {
    if (!this.ticketId) return;
    this.chatService.sendPresence(this.ticketId).subscribe({
      error: (err) => console.error('Error en heartbeat de presencia', err)
    });
  }
  /**==== Limpiar recursos al destruir el componente ====*/ 
  ngOnDestroy(): void {
    //  Detener el pulso al salir del componente
    if (this.presenceInterval) {
      clearInterval(this.presenceInterval);
    }
    if (this.ticketId) {
      this.echoService.leave(`tickets.${this.ticketId}`);
    }
  }
  /**==== ver cambios en la vista ====*/ 
  ngAfterViewChecked(): void {
    this.scrollToBottom();
  }
  /**==== Cargar mensajes del chat ====*/ 
  cargarMensajes(silencioso = false) {
    // si no hay ticketId, no tiene sentido cargar mensajes
    if (!this.ticketId) return;
    // Mostramos el indicador de carga
    if (!silencioso) {
        this.cargando = true;
        this.cdr.detectChanges();
    }
    // Llamamos al servicio para obtener los mensajes del chat
    this.chatService.getMessages(this.ticketId).subscribe({
      next: (data) => {
        this.mensajes = data;
        if (!silencioso) this.cargando = false;
        this.cdr.detectChanges(); 
        // Solo hacemos scroll si es la carga inicial
        if (!silencioso) setTimeout(() => this.scrollToBottom(), 100);
      },
      error: (err) => {
        console.error(err);
        this.cargando = false;
      }
    });
  }

  /**==== Manejo de archivos ====*/ 
  alSeleccionarArchivo(event: any) {
    const file = event.target.files[0];
    if (!file) return;

    const limiteMB = 10;
    if (file.size > limiteMB * 1024 * 1024) {
        this.errorMessage = `⚠️ Archivo muy grande. Máx ${limiteMB} MB.`;
        this.archivoSeleccionado = null;
        event.target.value = ''; 
        setTimeout(() => this.errorMessage = '', 6000);
        return;
    }
    this.archivoSeleccionado = file;
    this.errorMessage = ''; 
  }
  /**==== Limpiar archivo seleccionado ====*/ 
  limpiarArchivo() {
    this.archivoSeleccionado = null;
    const fileInput = document.getElementById('inputArchivo') as HTMLInputElement;
    if (fileInput) fileInput.value = '';
  }
  /**==== Activar input de archivo ====*/ 
  activarInputArchivo() {
    const fileInput = document.getElementById('inputArchivo') as HTMLInputElement;
    if (fileInput) fileInput.click();
  }

  scrollToBottom(): void {
    try {
      this.myScrollContainer.nativeElement.scrollTop = this.myScrollContainer.nativeElement.scrollHeight;
    } catch(err) { }
  }

  /**==== Enviar mensaje ====*/ 
  enviarMensaje() {
    // Validamos que haya algo que enviar
    if (!this.nuevoMensaje.trim() && !this.archivoSeleccionado) return;
    const texto = this.nuevoMensaje;
    const archivo = this.archivoSeleccionado;

    // 1. TRUCO DE IMAGEN INSTANTÁNEA (Blob URL)
    let previewUrl = null;
    if (archivo) {
      // Creamos una URL local temporal para que la imagen se vea YA MISMO
      previewUrl = URL.createObjectURL(archivo);
    }

    // 2. CREAMOS EL MENSAJE OPTIMISTA (FALSO)
    const mensajeOptimista = {
      id: Date.now(), // ID temporal
      ticket_id: this.ticketId,
      user_id: this.usuarioActualId,
      user: this.usuarioActual, // Para mostrar mi nombre/avatar
      message: texto,
      
      // Si es un archivo, mostramos la URL local; si no, null
      ruta_archivo: archivo ? previewUrl : null, // Usamos la URL local
      nombre_original: archivo ? archivo.name : null,
      created_at: new Date().toISOString(),
      type: archivo ? 'file' : 'text',
      
      // BANDERAS DE ESTADO
      esTemporal: true, // Para ponerle opacidad o icono de reloj
      esImagenLocal: !!archivo // Para saber que no debemos buscar en /storage/
    };

    // 2. LO AGREGAMOS VISUALMENTE DE INMEDIATO
    this.mensajes.push(mensajeOptimista);
    
    // Limpiamos el formulario INMEDIATAMENTE
    this.nuevoMensaje = ''; 
    this.limpiarArchivo(); 
    this.cdr.detectChanges();
    setTimeout(() => this.scrollToBottom(), 50);

    // 3. ENVIAMOS AL BACKEND
    this.chatService.sendMessage(this.ticketId, texto, archivo).subscribe({
      next: (mensajeReal) => {
        // 4. ÉXITO: REEMPLAZAMOS EL FALSO CON EL REAL
        // Buscamos el mensaje temporal en el array y lo cambiamos por el que devolvió la BD
        const index = this.mensajes.indexOf(mensajeOptimista);
        if (index !== -1) {
          this.mensajes[index] = mensajeReal;
        }
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error enviando', err);
        // Marcamos el mensaje como fallido en lugar de borrarlo
        mensajeOptimista.esTemporal = false;
        (mensajeOptimista as any)['error'] = true; // Nueva bandera para mostrar icono rojo
        this.cdr.detectChanges();
      }
    });
  }
}