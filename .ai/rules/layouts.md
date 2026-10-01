---
paths:
  - 'resources/views/layouts/**'
---

# Layouts

## One shell for the whole product
Signed-in pages use the public site's shell: layouts::app wraps layouts::marketing (the same top nav, footer, and assistant), and there is no sidebar. Every app page opens with <x-app.page-header> (the white title band, which includes the area tabs) and puts its body in <x-app.content> (the grey 7xl column; `width` narrows it). For signed-in users the header shows their areas (AppNavigation::areas(): Renting for renters, Hosting for owners, Administration for admins) plus Browse vehicles on every page, public ones included, so the way back is always visible. Guests get the marketing links instead. The header links, account menu, and page tabs are all built from App\Support\AppNavigation::sections(), so add new pages there. Everything is light-only: no class="dark", no @fluxAppearance, no appearance setting. Flux's accent is the brand (--color-accent brand-600, accent-content brand-700 in app.css).

## Dashboards
/dashboard only redirects (DashboardController) to renter.dashboard, owner.dashboard, or admin.dashboard by role; link to those routes directly. Build dashboards from x-dashboard.greeting (the page header), x-dashboard.stat, and x-dashboard.panel.
