# 📦 Especificación Modular: Pacientes y Admisión

- **Módulo:** `pacientes`
- **Estado:** `En Implementación`
- **Última Actualización:** 2026-11-18
- **Subtareas asociadas:** `CEN-34` (formulario de admisión), `CEN-36` (barrios y selector con autocompletado), `CEN-73` (RBAC enfermería y padrón `/pacientes`), `CEN-74` (admisión + signos vitales unificados en `/pacientes/nuevo`), `CEN-75` (posición estimada en cola), `CEN-77` (remediación UI), `CEN-78` (tests)
- **Fuente de diseño:** Figma «Centinela – UX/UI», pantalla *02 · Admisión*.

---

## 1. Alcance y Propósito del Módulo
Registro demográfico de pacientes en el nodo local del CAPS junto con la toma inicial de signos vitales, motivo de consulta y cálculo de prioridad Manchester (MTS), además de la consulta del padrón de pacientes del centro (`/pacientes`). Los datos identificatorios (DNI, nombre, domicilio, teléfono) **nunca salen del nodo** (Ley N° 25.326).

---

## 2. Requerimientos Funcionales (EARS)
- [x] **RF-PAC-01:** El sistema debe ofrecer en `/pacientes` el padrón/buscador de pacientes del CAPS y en `/pacientes/nuevo` el formulario Livewire unificado de admisión + triage, accesibles para usuarios autenticados con el Gate `access-admission` (`admission`, `triage_nurse` y `director`).
- [x] **RF-PAC-02:** Mientras el usuario completa el formulario, el sistema debe validar cada campo en tiempo real y mostrar el error debajo del campo (`@error`) con bordes `border-danger` y accesibilidad `aria-invalid`/`aria-describedby`.
- [x] **RF-PAC-03:** Cuando el formulario es válido y se envía, el sistema debe persistir el paciente (o actualizar sus datos editables si es recurrente) y registrar el triage en una transacción atómica (`DB::transaction`), mostrar la confirmación con prioridad y posición estimada `#N` en cola y emitir el ticket digital (`PrintTicket`).
- [x] **RF-PAC-04:** Cuando se ingresa un DNI en `/pacientes/nuevo`, si ya está registrado en `patients` (incluyendo `withTrashed()`), el sistema debe precargar sus datos personales editables para crear un nuevo triage sin duplicar el paciente; si ya posee un triage activo (`pending_consultation`, `called`, `in_consultation`), debe alertar con el `#TR-XXXXX` activo y bloquear el doble ingreso en cola.
- [x] **RF-PAC-05:** El formulario debe incluir un selector reactivo con autocompletado y búsqueda rápida en tiempo real para seleccionar el barrio de residencia dentro del nomenclador oficial de Formosa Capital con delimitación poligonal PostGIS (SRID 4326).
- [x] **RF-PAC-06:** El formulario `/pacientes/nuevo` debe exigir los 6 signos vitales cuantitativos y el motivo de consulta (ubicado arriba de los discriminadores MTS opcionales), mostrando en tiempo real la clasificación MTS calculada por `ManchesterTriageService`.
- [x] **RF-PAC-07:** El ticket digital abierto tras guardar (`PrintTicket`) debe estar habilitado tanto para `access-admission` como para `access-triage`, ocultando el botón de *Reclasificar (Override)* cuando el usuario no cuente con `access-triage`.
- [x] **RF-PAC-08:** La pantalla `/pacientes` (`PatientDirectory`) debe permitir buscar pacientes por DNI o nombre, visualizar su barrio y cantidad de atenciones, e iniciar una nueva atención en `/pacientes/nuevo?dni=...` (reemplazando el placeholder previo).

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: Carga exitosa de paciente nuevo con signos vitales y posición en cola
  Dado un usuario autenticado con rol "admission" o "triage_nurse" en "/pacientes/nuevo"
  Cuando ingresa un DNI inexistente, completa datos personales, barrio, los 6 signos vitales obligatorios, motivo de consulta (antes de los discriminadores) y discriminadores opcionales
  Entonces el paciente y el triage se persisten atómicamente en "patients" y "triages"
  Y el sistema muestra la confirmación con prioridad MTS y posición estimada #N en espera
  Y abre el ticket digital TR-XXXXX con la posición estimada en lista de espera

Escenario: Paciente recurrente por DNI sin triage activo
  Dado un paciente ya registrado con DNI "23456789" sin triage activo
  Cuando el operador ingresa "23456789" en "/pacientes/nuevo"
  Entonces el formulario precarga sus datos editables sin arrojar error de DNI único
  Y permite completar los signos vitales y registrar una nueva atención de triage

Escenario: Bloqueo de doble ingreso por triage activo existente
  Dado un paciente con un triage activo en estado "pending_consultation", "called" o "in_consultation"
  Cuando el operador ingresa su DNI en "/pacientes/nuevo"
  Entonces el sistema muestra una alerta con el ticket activo (#TR-XXXXX)
  Y bloquea registrar un nuevo triage duplicado en la cola

Escenario: Acceso por rol a admisión
  Dado un usuario autenticado con rol "triage_nurse"
  Cuando navega a "/pacientes" o "/pacientes/nuevo"
  Entonces el sistema responde con HTTP 200
  Y un usuario con rol "doctor" recibe código HTTP 403 Forbidden en "/pacientes/nuevo"
```

---

## 4. Modelo de Datos y Validaciones
- **Tabla:** `patients` (creada en `CEN-14`).

| Campo | Tipo | Regla de validación |
| :--- | :--- | :--- |
| `dni` | string(20) | requerido, solo dígitos, máx. 20; si ya existe en `patients`, precarga/actualiza sin duplicar (si no tiene triage activo) |
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
| `GET /pacientes` | `patients.index` | `auth`, `verified`, `can:access-admission` |
| `GET /pacientes/nuevo` | `patients.create` | `auth`, `verified`, `can:access-admission` |
