/* global self, clients */
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting())
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const targetUrl = '/calendar'
  event.waitUntil((async () => {
    const windowClients = await self.clients.matchAll({
      type: 'window',
      includeUncontrolled: true,
    })

    for (const client of windowClients) {
      if ('focus' in client) {
        await client.focus()
        if ('navigate' in client && !client.url.includes(targetUrl))
          await client.navigate(targetUrl)
        return
      }
    }

    if (self.clients.openWindow)
      await self.clients.openWindow(targetUrl)
  })())
})
