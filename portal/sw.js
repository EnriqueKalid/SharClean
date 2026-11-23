/* Service worker mínimo del portal: permite instalarlo como app en el celular.
   NO guarda nada en caché — el portal maneja datos personales y el check-in
   siempre debe hacerse en línea. Sin conexión muestra un aviso en lugar del
   error del navegador. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET' || e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(() => new Response(
    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
    '<body style="font-family:system-ui;padding:40px 24px;text-align:center;color:#334155">' +
    '<h2>Sin conexión</h2><p>Conéctate a internet para registrar tu asistencia.</p>' +
    '<button onclick="location.reload()" style="margin-top:16px;padding:12px 24px;border-radius:10px;border:0;background:#0284C7;color:#fff;font-size:16px">Reintentar</button>',
    { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
});
