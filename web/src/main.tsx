import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import 'leaflet/dist/leaflet.css'
import './index.css'
import App from './App.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    // Whether this load was already controlled by a service worker — if not,
    // this is a first visit, and clients.claim() taking over for the first
    // time isn't a real update, so it shouldn't trigger a reload below.
    const hadController = Boolean(navigator.serviceWorker.controller)

    navigator.serviceWorker.register('/sw.js').catch(() => {
      // Installability/offline-shell caching is a progressive enhancement —
      // a registration failure shouldn't affect the app itself.
    })

    // A new service worker taking over means this tab's already-loaded JS is
    // stale — reload once so a deploy doesn't sit invisible in a long-open
    // tab until the user happens to refresh it themselves. This has bitten
    // us more than once: fixes shipped hours earlier still weren't visible
    // in a session that had been open since before the deploy.
    let reloaded = false
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (!hadController || reloaded) return
      reloaded = true
      window.location.reload()
    })
  })
}
