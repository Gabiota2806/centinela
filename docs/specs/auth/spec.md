# 📦 Especificación Modular: Autenticación y Roles

- **Módulo:** `auth`
- **Estado:** `Implementado`
- **Última Actualización:** 2026-11-18
- **Subtareas asociadas:**
  - [x] `CEN-15` (implementación de autenticación y autorización por roles)
  - [x] `CEN-16` (pruebas unitarias y de integración de modelos, PostGIS, inmutabilidad, enum y gates)
  - [ ] `CEN-73` / `T4.B1` (habilitación de `triage_nurse` en `access-admission` según relevamiento CAPS Codda)

---

## 1. Alcance y Propósito del Módulo
Autenticación del personal en el nodo local del CAPS (Laravel Breeze) y autorización por rol (RBAC) con un Enum nativo y Gates de Laravel. Define a qué pantalla va cada rol después de iniciar sesión y qué rutas puede ver.

Las rutas `/pacientes`, `/triage` y `/consultas` se crean en esta tarea como **pantallas mínimas** («módulo en construcción») solo para cumplir la redirección y el control de acceso; su funcionalidad se especifica en los módulos `pacientes`, `triage` y `consultas` (Sprints 3 y 4).

---

## 2. Requerimientos Funcionales (EARS)
- [x] **RF-AUTH-01:** El sistema debe representar los roles con el Enum `App\Enums\UserRole` (`admission`, `triage_nurse`, `doctor`, `director`) y su etiqueta en español (`label()`).
- [x] **RF-AUTH-02:** La columna `users.role` debe tener valor por defecto `admission` y castearse a `UserRole` en el modelo `User`, que expone `isAdmission()`, `isTriageNurse()`, `isDoctor()` e `isDirector()`.
- [x] **RF-AUTH-03:** El sistema debe definir los Gates de acceso de la matriz de la sección 4.
- [x] **RF-AUTH-04:** Cuando un usuario inicia sesión, el sistema debe redirigirlo a la pantalla de su rol (sección 4).
- [x] **RF-AUTH-05:** Si un usuario autenticado accede a una ruta cuyo Gate no tiene, el sistema debe responder `403 Forbidden`.
- [x] **RF-AUTH-06:** El layout autenticado debe mostrar el nombre y la etiqueta del rol del usuario, y la barra lateral solo debe mostrar los ítems cuyo Gate el usuario tiene.

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: Inicio de sesión exitoso y redirección por rol
  Dado un usuario registrado con rol "triage_nurse"
  Cuando ingresa sus credenciales válidas en "/login"
  Entonces es autenticado en el sistema
  Y es redirigido a la pantalla "/triage"
  Y la vista muestra el nombre del profesional y su rol "Enfermero de Triage"

Escenario: Denegación de acceso cruzado
  Dado un usuario autenticado con rol "admission"
  Cuando intenta navegar a la ruta "/consultas"
  Entonces el sistema responde con código HTTP 403 Forbidden
```

---

## 4. Matriz de Roles, Gates y Redirección

| Rol (`value`) | Etiqueta | Pantalla post-login | `access-admission` | `access-triage` | `access-doctor` | `access-analytics` |
| :--- | :--- | :--- | :---: | :---: | :---: | :---: |
| `admission` | Admisión / Mesa de Entrada | `/pacientes` | ✔ | | | |
| `triage_nurse` | Enfermero de Triage | `/triage` | ✔ | ✔ | | |
| `doctor` | Médico de Guardia | `/consultas` | | ✔ | ✔ | |
| `director` | Dirección de CAPS | `/dashboard` | ✔ | ✔ | ✔ | ✔ |

| Ruta | Nombre | Middleware |
| :--- | :--- | :--- |
| `/pacientes` | `patients.index` | `auth`, `verified`, `can:access-admission` |
| `/triage` | `triage.index` | `auth`, `verified`, `can:access-triage` |
| `/consultas` | `consultations.index` | `auth`, `verified`, `can:access-doctor` |

Los Gates de dominio previos (`admit-patient`, `classify-triage`, `clinical-override`, `attend-patient`, `view-epidemiology`) se conservan con los nuevos casos del Enum; sus reglas se revisan en las tareas de los Sprints 3 y 4.

---

## 5. Modelo de Datos y Entidades
- **Tabla afectada:** `users`
- **Campo:** `role` — `string`, default `'admission'`.
- **Migración:** `update_role_values_on_users_table` cambia el default y convierte valores previos (`admision`→`admission`, `triage`→`triage_nurse`, `medico`→`doctor`, `ministerio`→`director`).
