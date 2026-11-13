# 📦 Especificación Modular: Pacientes y Admisión

- **Módulo:** `pacientes`
- **Estado:** `En Implementación`
- **Última Actualización:** 2026-11-13
- **Subtareas asociadas:** `CEN-34` (formulario de admisión), `CEN-36` (barrios y selector con autocompletado)
- **Fuente de diseño:** Figma «Centinela – UX/UI», pantalla *02 · Admisión*.

---

## 1. Alcance y Propósito del Módulo
Registro demográfico de pacientes en la mesa de entrada del CAPS (nodo local). Los datos identificatorios (DNI, nombre, domicilio, teléfono) **nunca salen del nodo** (Ley N° 25.326).

Fuera de esta especificación: selector de barrios con autocompletado y carga de polígonos (`CEN-36`), evaluación de triage (`CEN-35`), búsqueda de pacientes por DNI y lista de espera de triage (no planificadas en el backlog).

---

## 2. Requerimientos Funcionales (EARS)
- [x] **RF-PAC-01:** El sistema debe ofrecer en `/pacientes/nuevo` un formulario Livewire para registrar pacientes, accesible solo para usuarios autenticados con el Gate `access-admission`.
- [x] **RF-PAC-02:** Mientras el usuario completa el formulario, el sistema debe validar cada campo en tiempo real y mostrar el error debajo del campo (`@error`).
- [x] **RF-PAC-03:** Cuando el formulario es válido y se envía, el sistema debe persistir el paciente en `patients`, mostrar un aviso de éxito y ofrecer iniciar el triage de ese paciente.
- [x] **RF-PAC-04:** Si el DNI ya está registrado, el sistema debe rechazar el alta e informar el error en el campo DNI.

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: Carga exitosa de paciente con validación reactiva
  Dado un usuario autenticado con rol "admission"
  Cuando completa el formulario con datos válidos y selecciona un barrio existente
  Entonces el paciente se persiste en la tabla "patients"
  Y el sistema emite notificación flash de éxito y permite iniciar triage de inmediato

Escenario: DNI duplicado
  Dado un paciente ya registrado con DNI "23456789"
  Cuando el personal de admisión intenta registrar otro paciente con el mismo DNI
  Entonces el paciente no se guarda
  Y el campo DNI muestra un error de validación

Escenario: Acceso restringido
  Dado un usuario autenticado con rol "triage_nurse"
  Cuando intenta ingresar a "/pacientes/nuevo"
  Entonces el sistema responde con código HTTP 403 Forbidden
```

---

## 4. Modelo de Datos y Validaciones
- **Tabla:** `patients` (creada en `CEN-14`).

| Campo | Tipo | Regla de validación |
| :--- | :--- | :--- |
| `dni` | string(20) | requerido, solo dígitos, máx. 20, único en `patients` |
| `first_name` | string(100) | requerido, máx. 100 |
| `last_name` | string(100) | requerido, máx. 100 |
| `birth_date` | date | requerida, fecha válida, `before_or_equal:today` |
| `gender` | string(20) | requerido, uno de `masculino`, `femenino`, `otro` |
| `phone` | string(50) | opcional, máx. 50 |
| `address` | string(255) | requerido, máx. 255 |
| `neighborhood_id` | foreignId | requerido, existente en `neighborhoods` |

---

## 5. Rutas
| Ruta | Nombre | Middleware |
| :--- | :--- | :--- |
| `GET /pacientes/nuevo` | `patients.create` | `auth`, `verified`, `can:access-admission` |
