<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Repositorio; // Modelo del repositorio de archivos
use Symfony\Component\HttpFoundation\StreamedResponse; // Para respuestas de streaming (SSE)
use GuzzleHttp\Client;  // Para hacer solicitudes HTTP a Ollama

class ChatbotController extends Controller
{
    /**========================================
     * 1. CHAT
     ==========================================*/
    public function chat(Request $request)
    {
        set_time_limit(0); // Evitar timeout en respuestas largas
        $pregunta = $request->input('mensaje'); // La pregunta del usuario
        $historial = $request->input('historial', []); // Historial de la conversación (array de mensajes anteriores)
        // === 1. BÚSQUEDA EN REPOSITORIO (RAG) ===
        $stopWords = ['como', 'puedo', 'hacer', 'tengo', 'este', 'esta', 'problema', 'para', 'ayuda','problemas' ]; // Palabras comunes que no aportan valor técnico, se pueden ajustar según el contexto
        $palabras = explode(' ', strtolower($pregunta)); // Convertimos a minúsculas y separamos por espacios
        $palabrasClave = array_filter($palabras, function($p) use ($stopWords) { // Filtramos palabras clave
        return strlen($p) > 3 && !in_array($p, $stopWords); // Solo palabras mayores a 3 caracteres y que no sean stop words
        });

        $archivosEncontrados = Repositorio::where('visibilidad', 'publico') // Solo archivos públicos para no confundir a la IA con información técnica que no debería usar
        ->where(function($query) use ($palabrasClave) { // Buscamos que el nombre o descripción contenga AL MENOS una palabra clave técnica importante
        foreach ($palabrasClave as $palabra) { // Para cada palabra clave, agregamos condiciones OR
            $query->orWhere('nombre_original', 'LIKE', "%$palabra%") // Coincidencia en el nombre del archivo
                  ->orWhere('descripcion', 'LIKE', "%$palabra%"); // Coincidencia en la descripción del archivo
        }
    })
    ->take(2) // Reducir a 2 para no confundir a la IA con demasiada info
    ->get(); // Obtenemos los archivos encontrados

        //==== 2. CONSTRUIR TEXTO DE ARCHIVOS===
        $textoArchivos = ""; // Inicializamos el texto de archivos
        if ($archivosEncontrados->count() > 0) { // Si encontramos archivos relevantes, construimos un texto descriptivo para la IA
            $textoArchivos = "CONTEXTO DE ARCHIVOS DISPONIBLES:\n"; // Encabezado para la sección de archivos
            foreach ($archivosEncontrados as $archivo) { // Para cada archivo encontrado, agregamos una línea descriptiva con su nombre original y descripción (si existe)
                $textoArchivos .= "- ARCHIVO: " . $archivo->nombre_original . " (Descripción: " . ($archivo->descripcion ?? 'N/A') . ")\n"; // Formato: "- ARCHIVO: [Nombre Original] (Descripción: [Descripción o N/A])"
            }
        }

        //==== 3. CONSTRUIR HISTORIAL DE MEMORIA ====
        $chatHistoryText = ""; // Inicializamos el texto del historial de la conversación para la IA
        foreach ($historial as $msg) { // Para cada mensaje en el historial, agregamos una línea con el rol (Usuario o Asistente) y el contenido del mensaje
            $rol = ($msg['role'] === 'user') ? 'Usuario' : 'Asistente'; // Convertimos el rol técnico a un formato más amigable para la IA (Usuario o Asistente)
            $chatHistoryText .= "$rol: " . $msg['content'] . "\n"; // Formato: "Usuario: [Mensaje del usuario]" o "Asistente: [Mensaje del asistente]"
        }

$promptFinal = "
### INSTRUCCIONES DE ROL (SYSTEM)
Eres 'UmsiBot', el asistente técnico exclusivo de la RED UMSALUD. 
Tu misión es resolver problemas de Nivel 1 (Hardware, Software, Redes) o relacionados a la tecnologia
- no saludes, ve directo la solución o en su defecto las causas para dar solucion.
- Responde siempre en ESPAÑOL.
- Si la solución tiene pasos, usa listas numeradas (1, 2, 3...).
- No te inventes informacion, si desconoces de un tema o alguna informacion dila.
- Si el problema es desconocido, deriva al Piso 5 de la Facultad de Medicina.
- Si hacen una pregunta sobre salud u otro tema que no este relacionado a la tecnologia, responde que no puedes ayudar en ese tema y sugiere contactar a las áreas correspondientes.
- escribe en formato Markdown

### INFORMACIÓN INSTITUCIONAL
- Ubicación: Piso 5 de la Facultad de Medicina.
- Horarios: L-J (08:00-12:00, 15:00-18:00), Viernes (08:00-16:00 continuo).

### CONTEXTO DE ARCHIVOS ADJUNTOS
$textoArchivos

### REGLA DE ARCHIVOS:
- Analiza si en $textoArchivos hay texto con relación DIRECTA con la pregunta del usuario en caso de que Si hay relación sugiere al usuario revisar el archivo adjunto.
- Si los archivos NO tienen relación directa con el problema, IGNÓRALOS y no los menciones.

### MEMORIA DE LA CONVERSACIÓN (HISTORIAL)
A continuación se muestra lo que tú y el usuario ya han hablado anteriormente. Úsalo para dar continuidad:
$chatHistoryText

### TAREA ACTUAL
Basándote en el historial y la información anterior, responde a la siguiente consulta:
Usuario: '$pregunta'
Asistente:";

return new StreamedResponse(function () use ($promptFinal, $archivosEncontrados) { 
            
            // 1. Enviar metadatos de archivos primero
            echo "data: " . json_encode(['archivos' => $archivosEncontrados]) . "\n\n"; // Enviamos la información de los archivos encontrados como un mensaje inicial para que el frontend pueda mostrar sugerencias o enlaces a esos archivos
            if (ob_get_level() > 0) ob_flush(); // Limpiar el buffer de salida para enviar inmediatamente
            flush(); // Aseguramos que se envíe inmediatamente al cliente

            try { // 2. Conectarse a Ollama y enviar el prompt
                $client = new Client(); // Cliente HTTP para hacer la solicitud a Ollama
                $res = $client->post('http://127.0.0.1:11434/api/generate', [ // solicitud POST a la API de generacion ollama
                    'json' => [ // Cuerpo de la solicitud en formato JSON
                        'model' => 'gemma3:4b',  // Modelo IA de Ollama
                        'prompt' => $promptFinal, // prompt completo con instrucciones, archivos y hostorial
                        'stream' => true, // streaming para respuestas en tiempo real
                        'options' => [ // Opciones para la generacion de texto
                            'temperature' => 0.7, // Controla la creatividad de las respuestas
                            'num_predict' => 500 // limite de tokens para la respuesta 
                        ]
                    ],
                    'stream' => true, // streaming
                    'timeout' => 0 // sin timeout para respuestas largas
                ]);

                $body = $res->getBody(); // Obtenemos el cuerpo de la respuesta, que es un stream de datos (SSE) que Ollama envía línea por línea en formato JSON
                
                while (!$body->eof()) { // si no termina el stream, seguimos leyendo
                    $line = $this->readLine($body); // leeemos linea por linea
                    if (empty(trim($line))) continue; // si la liena esta vacio, lo ignoramos
                    $decoded = json_decode($line, true); // decodificamos la linea Json a un array asociativo de PHP
                    if (isset($decoded['response'])) { // Si la respuesta contiene un fragmento de texto generado por Ollama, lo enviamos al cliente como un mensaje SSE (data: ...)
                        echo "data: " . $decoded['response'] . "\n\n"; // Enviamos el fragmento de texto al cliente
                        if (ob_get_level() > 0) ob_flush(); // Limpiamos el buffer de salida para enviar inmediatamente
                        flush(); // Aseguramos que se envíe inmediatamente al cliente para que la experiencia de chat sea fluida y en tiempo real
                    }
                    if (isset($decoded['done']) && $decoded['done'] === true) { // Si recibimos una señal de que la generación ha terminado (done: true), salimos del bucle de lectura
                        break;
                    }
                }

            } 
            catch (\Exception $e) { // Si hay un error, lo capturamos
                //sI HAY ERROR, ENVIARLO COMO DATA (No HTML)
                echo "data: [ERROR: " . $e->getMessage() . "]\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache', // Evitar que el navegador almacene en caché la respuesta
            'Content-Type'  => 'text/event-stream', // Tipo de contenido para SSE
            'Connection'    => 'keep-alive', // Mantener la conexión abierta para streaming
            'X-Accel-Buffering' => 'no', // Desactivar buffering en servidores como Nginx/Apache para respuestas en tiempo real
        ]);
    }
    // Función auxiliar para leer líneas completas de Ollama
    private function readLine($stream) { // Ollama envía datos en formato JSON línea por línea, esta función lee hasta encontrar un salto de línea (\n) para obtener cada fragmento de respuesta completo
        $buffer = ''; // Inicializamos un buffer para acumular los caracteres leídos
        while (!$stream->eof()) { // Mientras no lleguemos al final del stream, seguimos leyendo
            $char = $stream->read(1); // Leemos un carácter a la vez para construir la línea completa
            if ($char === "\n") break; // Si encontramos un salto de línea, significa que hemos leído un fragmento completo de respuesta, así que salimos del bucle
            $buffer .= $char; // Acumulamos el carácter leído en el buffer
        }
        return $buffer; // Devolvemos la línea completa leída del stream, que debería ser un fragmento de respuesta en formato JSON enviado por Ollama
    }
}