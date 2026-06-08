import { Component, inject, ViewChild, ElementRef, ChangeDetectorRef, OnInit } from '@angular/core'; // 
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ChatbotService } from '../../services/chatbot';
import { Router } from '@angular/router';
import { marked } from 'marked';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
@Component({
  selector: 'app-asistente-virtual',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './asistente-virtual.html',
  styleUrl: './asistente-virtual.css'
})
export class AsistenteVirtualComponent implements OnInit{
  
  isOpen = false; // Estado del chat (abierto/cerrado)
  // Historial de mensajes con formato extendido para archivos adjuntos
  mensajes: { 
      emisor: 'user' | 'bot', 
      texto: string, 
      archivos?: any[] // Nuevo campo opcional
  }[] = [
    { emisor: 'bot', texto: '¡Hola! Soy Umsi el asistente de UMSALUD. ¿En qué te puedo ayudar hoy?' }
  ];
  
  nuevoMensaje = '';
  pensando = false;
  private sanitizer = inject(DomSanitizer);
  private chatbotService = inject(ChatbotService);
  private cdr = inject(ChangeDetectorRef); 
  private router = inject(Router);
  private readonly APP_CHAT_KEY = 'umsalud_chat_history';
  // Configuración de marked para procesar Markdown
  constructor() {
    marked.setOptions({
        breaks: true,  // Convierte \n en <br> automáticamente
        gfm: true      // Habilita GitHub Flavored Markdown (negritas, tablas, etc.)
    });
  }

  /**==== Ciclo de vida ====*/
  ngOnInit() {
    this.cargarHistorial();
  }
  // Carga el historial del chat desde localStorage o muestra el mensaje de bienvenida
  private cargarHistorial() {
    const guardado = localStorage.getItem(this.APP_CHAT_KEY);
    if (guardado) {
      this.mensajes = JSON.parse(guardado);
    } else {
      // Mensaje de bienvenida por defecto si no hay nada guardado
      this.mensajes = [
        { emisor: 'bot', texto: '¡Hola! Soy Umsi el asistente de UMSALUD. ¿En qué te puedo ayudar hoy?' }
      ];
    }
  }
  // Guarda el historial del chat en localStorage cada vez que se actualiza
  private guardarEnLocal() {
    localStorage.setItem(this.APP_CHAT_KEY, JSON.stringify(this.mensajes));
  }
  /**==== Alternar estado del chat ====*/ 
  toggleChat() {
    this.isOpen = !this.isOpen;
    if (this.isOpen) {
        setTimeout(() => this.scrollToBottom(), 100);
    }
  }

  // Referencia al contenedor del chat para bajar el scroll
  @ViewChild('scrollContainer') private scrollContainer!: ElementRef;
  renderizarMarkdown(texto: string): SafeHtml {
    if (!texto) return '';
    // Procesamos el markdown y lo marcamos como seguro para Angular
    const htmlRaw = marked.parse(texto);
    return this.sanitizer.bypassSecurityTrustHtml(htmlRaw as string);
}

  /**==== Enviar mensaje ====*/ 
  async enviar() { // Cambiado a async para manejar el stream
    if (!this.nuevoMensaje.trim()) return;
    const pregunta = this.nuevoMensaje; // Guardamos la pregunta antes de enviar para que aparezca inmediatamente en el chat
    const historialParaEnviar = this.mensajes.slice(-6).map(msg => ({// Solo enviamos los últimos 6 mensajes para contexto, puedes ajustar esto
        role: msg.emisor === 'user' ? 'user' : 'assistant',
        content: msg.texto
    }));

    this.mensajes.push({ emisor: 'user', texto: pregunta }); // Agrega la pregunta del usuario al chat
    this.nuevoMensaje = '';
    this.pensando = true;
    this.guardarEnLocal(); // Guardar pregunta del usuario
    // Agrega un mensaje del bot vacío que se irá llenando con el stream
    const botMsgIndex = this.mensajes.push({ emisor: 'bot', texto: '', archivos: [] }) - 1;
    
    this.cdr.detectChanges();
    this.scrollToBottom(); // Baja el scroll al enviar la pregunta
    // Enviar la pregunta al backend y manejar la respuesta en streaming
    try {
        const response = await fetch('http://192.168.1.50:8000/api/chatbot/preguntar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mensaje: pregunta, historial: historialParaEnviar })
        });
        // Manejo del stream de respuesta
        const reader = response.body?.getReader();
        const decoder = new TextDecoder();
        this.pensando = false;
        // Lee el stream en chunks y actualiza el mensaje del bot en tiempo real
        while (true) {
            const { done, value } = await reader!.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                const content = line.substring(6); 
                
                if (!content) {
                    this.mensajes[botMsgIndex].texto += '\n';
                    continue;
                }

                try {
                    const json = JSON.parse(content);
                    if (json && json.archivos) {
                        this.mensajes[botMsgIndex].archivos = json.archivos;
                    }
                } catch (e) {
                    this.mensajes[botMsgIndex].texto += content;
                    this.cdr.detectChanges();
                    this.scrollToBottom();
                }
            }
            //Guardar el progreso del stream
            this.guardarEnLocal();
        }
    } catch (err) {
        this.mensajes[botMsgIndex].texto = 'Error de conexión.';
        this.pensando = false;
        this.guardarEnLocal();
        this.cdr.detectChanges();
    }
  }
  // Función para bajar el scroll al final del chat
  scrollToBottom() {
    try {
        if (this.scrollContainer) {
            this.scrollContainer.nativeElement.scrollTop = this.scrollContainer.nativeElement.scrollHeight;
        }
    } catch(err) { }
  }
  /**==== Crear ticket de chat ====*/ 
  crearTicketDeChat() {
    // 1. Convertir el historial del chat a texto plano
    let resumenChat = "HISTORIAL DE CONVERSACIÓN CON IA:\n\n";
    // Recorremos los mensajes y formateamos cada uno con su autor
    this.mensajes.forEach(msg => {
        const autor = msg.emisor === 'user' ? 'Usuario' : 'Bot';
        resumenChat += `[${autor}]: ${msg.texto}\n`;
    });

    resumenChat += "\n---------------------\nSolicito ayuda de un técnico porque la IA no pudo resolver mi problema.";

    // 2. Cerrar el chat
    this.isOpen = false;

    // 3. Navegar a la pantalla de tickets enviando el resumen
    // Usamos el 'state' del router para pasar datos sin que se vean en la URL
    this.router.navigate(['/tickets'], { 
        state: { descripcionAutomatica: resumenChat } 
    });
  }
}