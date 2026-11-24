# 📦 Especificación Modular: Consultas Médicas y Cola en Tiempo Real

- **Módulo:** `consultas`
- **Estado:** `En Implementación`
- **Última Actualización:** 2026-11-24
- **Contratos base:** `docs/specs/00_contracts.md`, `docs/specs/global_spec.md`
- **Historias y Subtareas asociadas:**
  - `HU03` (`CEN-26`): Cola Médica en Tiempo Real (`CEN-42`, `CEN-43`, `CEN-44`, `CEN-45`, `CEN-46`)
  - `HU04` (`CEN-27`): Registro Diagnóstico al Cierre de Consulta (`CEN-47`)

---

## 1. Alcance y Propósito del Módulo

Gestiona la sala de espera médica de guardia del CAPS en tiempo real bajo el Sistema Manchester de Triage (MTS) y la atención clínica en consultorio. Garantiza que los casos críticos (Rojo/Naranja) encabecen siempre la cola, que el médico pueda gestionar el ciclo de llamado (`called` ➔ `in_consultation`, retorno a espera o ausencia) y que el cierre de consulta registre el diagnóstico presuntivo obligatorio disparando el evento `ConsultationCompleted` para telemetría epidemiológica.

---

## 2. Requerimientos Funcionales (EARS)

### Cola Médica en Tiempo Real (`HU03` — `MedicalQueue`)
- **RF-CONS-01 (`CEN-42`):** La ruta `/consultas` (protegida por `can:access-doctor`) debe renderizar el componente Livewire `App\Livewire\Doctor\MedicalQueue` mostrando los pacientes activos ordenados por prioridad MTS (Rojo → Naranja → Amarillo → Verde → Azul) y FIFO (`created_at ASC, id ASC`), con posición estimada `#N`, código de ticket `TR-XXXXX`, barrio y signos vitales clave.
- **RF-CONS-02 (`CEN-42`):** Cada tarjeta de paciente en espera debe mostrar un indicador de tiempo de espera transcurrido/restante frente a `target_wait_minutes` (0, 10, 60, 120, 240 min), destacando visualmente con tokens de peligro cuando se supera el límite objetivo, utilizando exclusivamente SVGs vectoriales (cero emojis).
- **RF-CONS-03 (`CEN-43`):** El componente `MedicalQueue` debe refrescarse automáticamente mediante eventos Livewire (`#[On('patient-triaged')]`, `#[On('triage-overridden')]`, `#[On('triage-updated')]`) y sondeo reactivo (`wire:poll.5s`) para sincronizar múltiples terminales en la LAN del CAPS.
- **RF-CONS-04 (`CEN-42`):** Desde cada tarjeta de la cola, el médico o director (`can:access-triage`) debe poder invocar el modal de *Clinical Override* (`open-clinical-override`) reordenando inmediatamente la cola sin recargar la página.
- **RF-CONS-05 (`CEN-44`):** El modelo `Triage` debe encapsular las transiciones de estado válidas con validación de negocio:
  - `callForConsultation()` (`pending_consultation` ➔ `called`)
  - `startConsultation(User $doctor)` (`called` ➔ `in_consultation`, creando `Consultation` con `status = in_progress` y `started_at = now()`)
  - `returnToQueue()` (`called` ➔ `pending_consultation`, preservando `created_at` FIFO)
  - `markAsAbsent()` (`called` ➔ `cancelled`, liberando al paciente para futura re-admisión)
  - Toda transición inválida debe lanzar una excepción de dominio (`InvalidArgumentException` o `DomainException`).
- **RF-CONS-06 (`CEN-42`, `CEN-44`):** Sobre pacientes en estado `called`, la cola debe ofrecer acciones selectivas: **"Ingresar a consulta"** (redirige a `/consultas/{triage}`), **"Volver a espera"** y **"Cancelar / Ausente"**.
- **RF-CONS-07 (`CEN-45`):** La consulta principal de cola activa debe respaldarse en el índice compuesto `idx_triages_active_queue (status, final_priority, created_at)` sin consultas N+1 (`with(['patient.neighborhood', 'user', 'consultation'])`).

### Consultorio y Cierre Diagnóstico (`HU04` — `ConsultationRoom`)
- **RF-CONS-08 (`CEN-47`):** La ruta `/consultas/{triage}` (protegida por `can:access-doctor`) debe renderizar `App\Livewire\Doctor\ConsultationRoom` mostrando el resumen clínico completo del paciente (datos demográficos, barrio, prioridad MTS, motivo de consulta, discriminadores y los 6 signos vitales registrados en admisión o editados en `/triage`).
- **RF-CONS-09 (`CEN-47`):** El formulario de cierre debe exigir obligatoriamente la selección de un **diagnóstico presuntivo estandarizado** (catálogo de `docs/specs/00_contracts.md` §5.1), permitir cargar `clinical_notes`, `prescription` y elegir el estado final de la consulta (`completed` o `referred_sipec`).
- **RF-CONS-10 (`CEN-47`):** Al guardar el cierre de atención dentro de una transacción (`DB::transaction`), `Consultation` debe persistir `diagnosis`, `clinical_notes`, `prescription`, `status` y `completed_at = now()`; `Triage` debe pasar a `completed` (saliendo de la cola activa hacia el historial de `/triage`); y debe despacharse el evento `App\Events\ConsultationCompleted`.

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: Ordenamiento dinámico de la cola médica por prioridad MTS y FIFO
  Dado que existen pacientes en estado "pending_consultation" con prioridades Verde, Rojo y Amarillo
  Cuando un médico autenticado accede a "/consultas"
  Entonces el paciente Rojo aparece en la posición "#1", seguido por Amarillo y luego Verde
  Y cada tarjeta muestra su número de ticket "TR-XXXXX", tiempo objetivo y acciones de gestión.

Escenario: Ciclo selectivo de paciente llamado (retorno a espera y ausencia)
  Dado un paciente en estado "pending_consultation" en la cola médica
  Cuando el médico presiona "Llamar a Consultorio"
  Entonces el triage pasa a estado "called"
  Y si el médico presiona "Volver a espera", retorna a "pending_consultation" manteniendo su posición FIFO original
  Y si el médico presiona "Cancelar / Ausente", pasa a "cancelled" y deja de bloquear una nueva admisión.

Escenario: Ingreso a consultorio y cierre con diagnóstico presuntivo obligatorio
  Dado un triage en estado "called" que el médico ingresa a consulta
  Cuando accede a "/consultas/{triage}", intenta cerrar sin diagnóstico presuntivo
  Entonces el sistema muestra error de validación y no cierra la consulta
  Cuando selecciona "sospecha_dengue", completa indicaciones y guarda el cierre
  Entonces la consulta queda en "completed" con timestamp "completed_at"
  Y el triage pasa a estado "completed" saliendo de la cola activa
  Y se dispara el evento de dominio "ConsultationCompleted".

Escenario: Autorización estricta de acceso a /consultas
  Dado un usuario autenticado con rol "admission" o "triage_nurse"
  Cuando intenta acceder a "/consultas" o "/consultas/{triage}"
  Entonces el sistema retorna HTTP 403 Forbidden.
```
