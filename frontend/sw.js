/* Service worker mínimo — habilita instalação PWA; rede sempre para API/dados dinâmicos */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
