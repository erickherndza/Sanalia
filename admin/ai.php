<?php
/**
 * Sanalia CRM — Endpoint de IA (Groq)
 * POST /admin/ai.php
 * Acciones: whatsapp_message | lead_summary
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['sanalia_admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'no autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

/* ── Cargar config (donde vive GROQ_API_KEY) ── */
$config_path = __DIR__ . '/../api/config.php';
if (file_exists($config_path)) require_once $config_path;

if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '') {
    echo json_encode(['ok' => false, 'error' => 'GROQ_API_KEY no configurada en api/config.php']);
    exit;
}

/* ═══════════════════════════════════════════════
   Helper: llamada a Groq (API compatible OpenAI)
═══════════════════════════════════════════════ */
function groq(string $system, string $user, int $max_tokens = 350): string
{
    $payload = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
        'temperature' => 0.72,
        'max_tokens'  => $max_tokens,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) throw new RuntimeException('cURL: ' . $err);

    $data = json_decode($raw, true);
    if (isset($data['error']['message'])) throw new RuntimeException($data['error']['message']);
    if (!isset($data['choices'][0]['message']['content'])) throw new RuntimeException('Respuesta inesperada de Groq');

    return trim($data['choices'][0]['message']['content']);
}

/* ── Mapa de interés → etiqueta legible ── */
$INTERES = [
    'vida'                  => 'Seguro de Vida',
    'salud'                 => 'Seguro de Salud',
    'viajes'                => 'Asistencia en Viaje',
    'vehiculos'             => 'Seguro de Vehículos',
    'accidentes-personales' => 'Seguro de Accidentes Personales',
    'internacionales'       => 'Seguro Médico Internacional',
    'riesgos-generales'     => 'Riesgos Generales Empresariales',
    'mascotas'              => 'Seguro de Mascotas',
    'exequial'              => 'Cobertura Exequial',
    'otro'                  => 'seguros en general',
];

$action = trim($_POST['action'] ?? '');

try {

    /* ═══════════════════════════════════════
       ACCIÓN: whatsapp_message
       Genera un mensaje corto listo para WA
    ═══════════════════════════════════════ */
    if ($action === 'whatsapp_message') {

        $nombre  = trim($_POST['nombre']  ?? '');
        $interes = trim($_POST['interes'] ?? '');
        $estado  = trim($_POST['estado']  ?? 'nuevo');
        $fuente  = trim($_POST['fuente']  ?? 'web');
        $notas   = trim($_POST['notas']   ?? '');
        $campana = trim($_POST['campana'] ?? '');
        $dias    = max(0, (int)($_POST['dias_sin_contacto'] ?? 0));

        if ($nombre === '') {
            echo json_encode(['ok' => false, 'error' => 'nombre requerido']);
            exit;
        }

        $interes_label = $INTERES[$interes] ?? 'seguros';

        $etapa_ctx = match($estado) {
            'nuevo'       => 'Primer contacto — el lead acaba de entrar al pipeline, nunca ha sido contactado.',
            'contactado'  => "Ya fue contactado una vez. Lleva $dias días sin novedad. Mensaje de seguimiento amigable.",
            'seguimiento' => "Está en seguimiento activo. Lleva $dias días en esta etapa. Recordatorio / push suave para cerrar.",
            'ganado'      => 'Ya cerró. Mensaje de bienvenida como cliente o seguimiento postventa.',
            'perdido'     => 'Lead frío o perdido. Intento de reactivación sin presión, recordando que siguen disponibles.',
            default       => "Estado: $estado.",
        };

        $fuente_ctx = match($fuente) {
            'facebook'  => 'El prospecto llegó por un anuncio de Facebook.',
            'instagram' => 'El prospecto llegó por Instagram.',
            'whatsapp'  => 'El prospecto inició contacto por WhatsApp.',
            'referido'  => 'El prospecto fue referido por un cliente existente.',
            default     => 'El prospecto llegó por el sitio web.',
        };

        $system = <<<SYS
Eres un asesor de seguros de Sanalia & Asociados, S.R.L., correduría de seguros en Santo Domingo, República Dominicana.
Escribes mensajes de WhatsApp profesionales pero cálidos, en español dominicano natural.
Reglas estrictas:
- Máximo 5 líneas de texto.
- 1 emoji como máximo, solo si aporta.
- Sin asteriscos, sin markdown, sin títulos.
- Sin mencionar nombres de aseguradoras específicas.
- Sin inventar precios, fechas ni datos que no se te proporcionaron.
- Terminar siempre con una pregunta o llamada a la acción concreta (agendar llamada, confirmar disponibilidad, etc.).
- Tono: cercano, sin ser informal en exceso. Como un asesor de confianza.
SYS;

        $user = "Genera un mensaje de WhatsApp para este prospecto.

Nombre: $nombre
Producto de interés: $interes_label
Situación: $etapa_ctx
$fuente_ctx" .
($campana ? "\nCampaña de origen: $campana" : '') .
($notas   ? "\nNotas del equipo: $notas"    : '') . "

El mensaje debe usar su nombre, mencionar el producto de interés naturalmente y terminar con una acción clara.";

        $msg = groq($system, $user, 300);
        echo json_encode(['ok' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ═══════════════════════════════════════
       ACCIÓN: lead_summary
       Resumen de 2-3 oraciones del lead
    ═══════════════════════════════════════ */
    if ($action === 'lead_summary') {

        $nombre   = trim($_POST['nombre']   ?? '');
        $interes  = trim($_POST['interes']  ?? '');
        $estado   = trim($_POST['estado']   ?? '');
        $fuente   = trim($_POST['fuente']   ?? '');
        $campana  = trim($_POST['campana']  ?? '');
        $notas    = trim($_POST['notas']    ?? '');
        $mensaje  = trim($_POST['mensaje']  ?? '');
        $prox     = trim($_POST['fecha_proximo_contacto'] ?? '');
        $dias     = max(0, (int)($_POST['dias_sin_contacto'] ?? 0));

        if ($nombre === '') {
            echo json_encode(['ok' => false, 'error' => 'nombre requerido']);
            exit;
        }

        $interes_label = $INTERES[$interes] ?? ($interes ?: 'no especificado');

        $estados_esp = [
            'nuevo'       => 'nuevo (sin contactar)',
            'contactado'  => 'contactado',
            'seguimiento' => 'en seguimiento',
            'ganado'      => 'ganado (cliente)',
            'perdido'     => 'perdido',
        ];
        $estado_label = $estados_esp[$estado] ?? $estado;

        $system = <<<SYS
Eres un asistente de CRM para una correduría de seguros en República Dominicana.
Genera resúmenes concisos (máximo 2 oraciones) de leads/prospectos para que un asesor entienda la situación en 5 segundos.
Sin viñetas. Solo texto corrido. Sin inventar información. Incluye: origen, interés, tiempo en pipeline y próximo paso sugerido.
SYS;

        $user = "Resume este lead en 2 oraciones:

Nombre: $nombre | Interés: $interes_label | Estado: $estado_label
Fuente: $fuente" . ($campana ? " · campaña «$campana»" : '') . "
Días en pipeline: $dias" . ($prox ? " | Próximo contacto programado: $prox" : '') .
($mensaje ? "\nMensaje original del prospecto: $mensaje" : '') .
($notas   ? "\nNotas del equipo: $notas" : '');

        $summary = groq($system, $user, 160);
        echo json_encode(['ok' => true, 'summary' => $summary], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'acción desconocida']);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
