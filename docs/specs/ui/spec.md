# 📦 Especificación Modular: Design System y Layouts

- **Módulo:** `ui`
- **Estado:** `En Implementación`
- **Última Actualización:** 2026-11-10
- **Subtareas asociadas:** `CEN-12`
- **Fuente de diseño:** Figma «Centinela – UX/UI» (página *Design System* y pantallas *01 · Login* y *05 · Cola médica*).

---

## 1. Alcance y Propósito del Módulo
Define la base visual compartida del nodo local (Edge): tokens de Tailwind CSS v3, tipografía, iconografía, logo y los layouts `guest` (pantallas sin sesión) y `app` (pantallas con sesión).

Quedan **fuera** de este módulo y se especifican en sus propias tareas: rol del usuario y menú por rol (`CEN-15`), contenido del formulario de login (`CEN-15`), badge/ticket MTS (Sprint 3), cola médica (Sprint 4), indicador de conexión offline (Sprint 5) y alertas (Sprint 6).

---

## 2. Requerimientos Funcionales (EARS)
- [x] **RF-UI-01:** El sistema debe exponer en `tailwind.config.js` (Tailwind v3) los tokens de color de la sección 4, incluidos los 5 colores MTS del backlog.
- [x] **RF-UI-02:** El sistema debe usar la tipografía **Plus Jakarta Sans** empaquetada localmente con Vite, sin depender de CDNs externos, para funcionar sin conexión (RNF06).
- [x] **RF-UI-03:** Cuando un usuario no autenticado accede a una pantalla `guest`, el sistema debe mostrar el layout de **pantalla dividida**: panel de marca (logo negativo, nombre, propuesta de valor) y tarjeta de contenido. En anchos menores a 1024 px el panel se oculta y el logo se muestra sobre la tarjeta.
- [x] **RF-UI-04:** Cuando un usuario autenticado accede a una pantalla `app`, el sistema debe mostrar una barra superior con el logo, el **nombre del nodo CAPS** (`config('centinela.node_name')`) y el **perfil activo** (nombre del usuario).
- [x] **RF-UI-05:** El layout `app` debe incluir una barra lateral de navegación que en escritorio (≥ 1024 px) pueda **colapsarse a íconos** y en tablet/celular se abra como **menú deslizable**.
- [x] **RF-UI-06:** La barra lateral solo debe mostrar los ítems de `config('centinela.navigation')` cuya ruta exista (`Route::has`).
- [x] **RF-UI-07:** Ningún layout debe mostrar el logo de Laravel ni emojis; los íconos son SVG vectoriales.

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: Login con branding de Centinela
  Dado un usuario no autenticado
  Cuando ingresa a "/login"
  Entonces ve el nombre "Centinela" y el panel de marca
  Y la página no carga tipografías desde CDNs externos

Escenario: Layout autenticado con nodo y perfil
  Dado un usuario autenticado llamado "Lucía Gómez"
  Y el nodo configurado como "CAPS B° San Miguel"
  Cuando ingresa a "/dashboard"
  Entonces ve la barra lateral de navegación con el control para colapsarla
  Y ve el nodo "CAPS B° San Miguel" y el nombre "Lucía Gómez"

Escenario: Ítems de menú sin ruta
  Dado un ítem de navegación cuya ruta todavía no existe
  Cuando un usuario autenticado ve la barra lateral
  Entonces ese ítem no se muestra
```

---

## 4. Tokens de Diseño (Figma → Tailwind)

| Grupo | Token Tailwind | Valor | Variable Figma |
| :--- | :--- | :---: | :--- |
| MTS (backlog) | `mts-red` | `#DC2626` | `mts/1-rojo` |
| | `mts-orange` | `#EA580C` | `mts/2-naranja` |
| | `mts-yellow` (texto negro) | `#EAB308` | `mts/3-amarillo` |
| | `mts-green` | `#16A34A` | `mts/4-verde` |
| | `mts-blue` | `#2563EB` | `mts/5-azul` |
| Marca | `brand-petroleo` | `#0B4F55` | `brand/petroleo` |
| | `brand-turquesa` | `#12808A` | `brand/turquesa` |
| | `brand-noche` | `#0A2E33` | `brand/noche` |
| | `brand-50` | `#E3F1F2` | `brand/petroleo-50` |
| Neutros | `ink` / `ink-muted` / `ink-subtle` | `#12292C` / `#56706F` / `#8AA0A0` | `neutral/text*` |
| | `line` / `canvas` | `#DCE6E6` / `#F5F8F8` | `neutral/border` / `neutral/bg` |
| Feedback | `ok` / `warn` / `danger` | `#198754` / `#B35C00` / `#C92A2A` | `feedback/*` |
| Riesgo | `risk-low` / `risk-mid` / `risk-high` | `#9FD8C6` / `#F4B860` / `#C2410C` | `riesgo/*` |

- **Reglas de uso:** los colores MTS se reservan exclusivamente para la prioridad clínica. Los nombres de los niveles siguen el estándar MTS establecido en `global_spec.md`.
- **Radios:** `rounded-lg` (8 px), `rounded-xl` (12 px), `rounded-2xl` (16 px). **Sombras:** `shadow-card`, `shadow-modal`.

---

## 5. Iconografía
- Se permiten **Heroicons y Lucide**, siempre como **SVG inline** en Blade (sin emojis ni fuentes de íconos).
- Tamaños con clases utilitarias `w-4 h-4` o `w-5 h-5`; color con `currentColor` y clases `text-*`.
- Lucide se usa para íconos que Heroicons no cubre (p. ej. clínicos: termómetro, gota, pulso).

---

## 6. Archivos del Módulo
- `tailwind.config.js`, `resources/css/app.css`, `config/centinela.php`
- `resources/views/layouts/guest.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/partials/`
- `resources/views/components/` (logo, enlaces de navegación, botones y campos con los tokens)
- Pruebas: `tests/Feature/Ui/GuestLayoutTest.php`, `tests/Feature/Ui/AppLayoutTest.php`
