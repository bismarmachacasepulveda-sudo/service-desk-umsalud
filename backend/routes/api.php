<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RepositorioController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\LogsActividadesController;

// RUTA PÚBLICA: Login
Route::post('/login', [AuthController::class, 'login']);
Route::post('/registrarse', [AuthController::class, 'registrarse']);
Route::get('/areas', [AreaController::class, 'index']); // Solo ver lista
Route::post('/chatbot/preguntar', [ChatbotController::class, 'chat']);
    // Auditoría e Historial


// =========================================================================
// GRUPO 2: SOLO ADMIN (Gestión Total)
// =========================================================================
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
  
    // Gestión de Usuarios
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/areas', [AreaController::class, 'store']);      // Crear
    Route::put('/areas/{id}', [AreaController::class, 'update']); // Editar
    Route::delete('/areas/{id}', [AreaController::class, 'destroy']); // Borrar

    // Gestión de Categorías
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Gestión de Ambientes
    Route::post('/ambientes', [AmbienteController::class, 'store']);
    Route::put('/ambientes/{id}', [AmbienteController::class, 'update']);
    Route::delete('/ambientes/{id}', [AmbienteController::class, 'destroy']);

    // Rutas de Aprobación
    Route::get('/users/pendientes', [UserController::class, 'getPendientes']);
    Route::post('/users/{id}/aprobar', [UserController::class, 'aprobarUsuario']);
    Route::post('/users/{id}/rechazar', [UserController::class, 'rechazarUsuario']);

//Historial
Route::get('/users/solicitudes-historial', [UserController::class, 'getHistorialSolicitudes']);
// --- RUTAS DE LA PAPELERA ---
Route::get('/users/trashed/all', [UserController::class, 'trashed']);
Route::post('/users/{id}/restore', [UserController::class, 'restore']);
Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDelete']);

    Route::get('tickets/trashed', [TicketController::class, 'trashed']);
    Route::post('tickets/{id}/restore', [TicketController::class, 'restore']);
    Route::delete('tickets/{id}/force-delete', [TicketController::class, 'forceDelete']);

    //Adutoria general
    Route::get('/admin/logs-actividades', [LogsActividadesController::class, 'index']);
    // RUTAS DE PAPELERA DE CATEGORÍAS 
    Route::get('/categories/trashed', [CategoryController::class, 'trashed']);
    Route::post('/categories/{id}/restore', [CategoryController::class, 'restore']);
    Route::delete('/categories/{id}/force-delete', [CategoryController::class, 'forceDelete']);

    Route::get('/repositorio/trashed', [RepositorioController::class, 'trashed']);
Route::post('/repositorio/{id}/restore', [RepositorioController::class, 'restore']);
Route::delete('/repositorio/{id}/force-delete', [RepositorioController::class, 'forceDelete']);

Route::get('/areas/trashed', [AreaController::class, 'trashed']);     // Listar borradas
Route::post('/areas/{id}/restore', [AreaController::class, 'restore']);   // Restaurar
Route::delete('/areas/{id}/force-delete', [AreaController::class, 'forceDelete']); // Borrar físico

// Papelera de Ambientes
Route::get('/ambientes/trashed', [AmbienteController::class, 'trashed']);
Route::post('/ambientes/{id}/restore', [AmbienteController::class, 'restore']);
Route::delete('/ambientes/{id}/force-delete', [AmbienteController::class, 'forceDelete']);
// PAPELERA DE RESERVAS
Route::get('/reservas/trashed', [App\Http\Controllers\ReservaController::class, 'trashed']);
Route::post('/reservas/{id}/restore', [App\Http\Controllers\ReservaController::class, 'restore']);
Route::delete('/reservas/{id}/force-delete', [App\Http\Controllers\ReservaController::class, 'forceDelete']);
});

// 🛡️ GRUPO 3: STAFF (Técnicos y Admin) - Reportes
// =========================================================================.
Route::middleware(['auth:sanctum', 'role:admin,tecnico'])->group(function () {
    Route::get('/reportes/generar', [ReportesController::class, 'generar']);
    Route::get('/reportes/excel', [ReportesController::class, 'exportarExcel']);
});



// =========================================================================
// GRUPO 1: RUTAS PROTEGIDAS (Técnicos, Usuarios, Admin)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Listas de Lectura (Para llenar dropdowns)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/users', [UserController::class, 'index']); // Solo ver lista


    // Módulos principales
    Route::apiResource('incidents', IncidentController::class);
    Route::apiResource('tickets', TicketController::class);
        // Ver mensajes: GET /api/tickets/5/chat
    Route::get('/tickets/{id}/chat', [ChatController::class, 'index']);
    
    // Enviar mensaje: POST /api/tickets/5/chat
    Route::post('/tickets/{id}/chat', [ChatController::class, 'store']);
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    Route::get('/repositorio', [RepositorioController::class, 'index']); // Ver lista
    Route::post('/repositorio', [RepositorioController::class, 'store']); // Subir archivo
    Route::get('/repositorio/{id}/download', [RepositorioController::class, 'download']); // Descargar
    Route::delete('/repositorio/{id}', [RepositorioController::class, 'destroy']); // Borrar (El controlador ya valida que seas dueño o admin)
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    Route::get('/ambientes', [AmbienteController::class, 'index']);
    Route::apiResource('reservas', ReservaController::class);
    
    //  RUTAS DE NOTIFICACIONES
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/user/toggle-whatsapp', [UserController::class, 'toggleWhatsapp']);
    //Rutas de Perfil
    Route::get('/profile', [PerfilController::class, 'show']);
    Route::put('/profile', [PerfilController::class, 'update']);
    Route::put('/profile/password', [PerfilController::class, 'updatePassword']);

    Route::post('/tickets/{ticketId}/presence', [ChatController::class, 'setPresence']);
});