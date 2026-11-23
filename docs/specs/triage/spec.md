# 📦 Especificación Modular: Módulo de Triage Clínico (Sistema Manchester - MTS)

- **Módulo:** `triage`
- **Estado:** `En Implementación`
- **Última Actualización:** 2026-11-18
- **Subtareas asociadas:**
  - [x] `CEN-35` (Programación de la lógica del árbol de decisión clínico Manchester - MTS)
  - [x] `CEN-37` (Emisión y visualización de ticket digital con color de prioridad asignado)
  - [x] `CEN-38` (Pruebas de integración de clasificación clínica automatizada)
  - [x] `CEN-39` (Componentes UX y modal de Clinical Override rápido con justificación diferida)
  - [x] `CEN-40` (Desarrollo de la tabla inmutable triage_audit_logs y lógica de reclasificación)
  - [x] `CEN-41` (Testing de casos de discrepancia y reordenamiento de cola por override clínico)
  - [ ] `CEN-73` / `T4.B1` (Permiso de PrintTicket desde admisión manteniendo Override bajo `access-triage`)
  - [x] `CEN-75` / `T4.B3` (Cálculo y visualización de posición estimada `#N` en cola y ticket 80 mm)
  - [ ] `CEN-76` / `T4.B4` (Evolución de `/triage`: búsqueda por DNI, pestaña "En espera" con edición auditada y pestaña "Historial")
  - [ ] `CEN-77` / `T4.B5` (Remediación UI de unidades `pr-*`/`pointer-events-none`, `wire:target="save"` y tokens de error)
  - [ ] `CEN-78` / `T4.B6` (Suite de pruebas Feature/Livewire para flujo unificado y gestión en `/triage`)

---

## 1. Alcance y Propósito del Módulo

El Módulo de Triage Clínico constituye el núcleo asistencial de Centinela en los Centros de Atención Primaria de la Salud (CAPS) de Formosa Capital. Implementa el **Sistema Manchester de Triage (MTS)** para clasificar objetiva y determinísticamente a los pacientes que ingresan por demanda espontánea o derivación de mesa de entrada, asignando uno de los 5 niveles internacionales de prioridad clínica y su tiempo objetivo máximo de espera médica.

Asimismo, contempla el mecanismo de **Clinical Override** (reclasificación médica motivada) respaldado por la bitácora inmutable `triage_audit_logs`, garantizando trazabilidad médico-legal y respeto al criterio clínico profesional.

---

## 2. Requerimientos Funcionales (EARS)

- **RF-TRIAGE-01:** El sistema debe clasificar determinísticamente la prioridad clínica del paciente evaluando discriminadores generales y signos vitales (`temperature`, `systolic_bp`, `diastolic_bp`, `heart_rate`, `respiratory_rate`, `spo2`) según el Sistema Manchester de Triage (MTS).
- **RF-TRIAGE-02:** El sistema debe asignar uno de los 5 niveles de prioridad representados por el Enum `App\Enums\TriagePriority` (`RED`, `ORANGE`, `YELLOW`, `GREEN`, `BLUE`) con sus respectivos tiempos objetivo de espera (0, 10, 60, 120, 240 minutos) y colores hexadecimales estandarizados.
- **RF-TRIAGE-03:** Ante cualquier signo vital o discriminador de riesgo vital inminente (ej. SpO2 < 85%, apnea, vía aérea obstruida, pulso no detectable, shock descompensado), el sistema debe clasificar ineludiblemente al paciente en prioridad **ROJO (Emergencia / 0 min)**.
- **RF-TRIAGE-04:** Ante sospecha febril de dengue (fiebre persistente > 39°C asociada a cefalea o mialgias) en contexto endémico de Formosa Capital, o SpO2 entre 93-94%, el sistema debe clasificar al paciente en prioridad **AMARILLO (Urgente / 60 min)**.
- **RF-TRIAGE-05:** La interfaz reactiva de triage debe permitir al personal de enfermería o médico cargar signos vitales, seleccionar discriminadores clínicos y previsualizar en tiempo real el cálculo de prioridad y tiempo de espera sin recargas de página.
- **RF-TRIAGE-06:** La persistencia de la evaluación debe registrarse en la tabla `triages` con `patient_id`, `user_id`, signos vitales en formato JSONB, prioridad algorítmica, prioridad final y estado `pending_consultation`.
- **RF-TRIAGE-07 (CEN-37):** Cuando se registra un triage, el sistema debe mostrar de inmediato un ticket digital con: número de turno `TR-` seguido del id con 5 dígitos, badge del nivel MTS (número, nombre y color oficial), nombre, DNI y barrio del paciente, fecha y hora exacta de ingreso, tiempo máximo de espera y nombre del profesional evaluador.
- **RF-TRIAGE-08 (CEN-37):** El ticket debe poder imprimirse en ticketeras térmicas de 80 mm (`@media print`), imprimiendo solo el ticket. En papel el nivel se identifica por número y nombre (no solo por color), ya que la impresión térmica es monocromática.
- **RF-TRIAGE-09 (CEN-37):** Tras registrar el triage, el formulario debe quedar limpio para el siguiente paciente (evita duplicados) y la confirmación debe persistir en pantalla con la opción "Ver ticket" para reimprimir. Si la validación falla, los errores deben mostrarse junto al botón "Registrar Triage Clínico". Los signos vitales usan campos de texto con teclado numérico (sin flechas ni cambio por rueda del mouse) y la temperatura acepta coma o punto decimal.
- **RF-TRIAGE-10 (CEN-39):** Los roles con Gate `access-triage` (enfermero de triage, médico y dirección) deben poder reclasificar la prioridad de un triage (*Clinical Override*) desde un modal con selector de los 5 niveles MTS estandarizados. El nivel actual no es seleccionable. Sin justificación diferida, `override_reason` es obligatorio con un mínimo de 10 caracteres.
- **RF-TRIAGE-11 (CEN-39):** El modal debe ofrecer "Justificación diferida (emergencia crítica)" para guardar el nuevo nivel en un clic, sin redactar el motivo. La auditoría inmutable registra el motivo como pendiente de redacción. La persistencia usa `Triage::overridePriority()` (CEN-40), conservando `algorithmic_priority` y recalculando `target_wait_minutes`. En la pantalla de triage el override se ofrece desde la confirmación del último registro y desde el ticket, y ambos reflejan la nueva prioridad.
- [ ] **RF-TRIAGE-12 (CEN-76):** La vista `/triage` debe ofrecer un buscador reactivo por DNI o nombre y dos pestañas: **"En espera"** (registros `pending_consultation` con su posición `#N` y registros `called` con badge "Llamado") e **"Historial"** (registros `in_consultation`, `completed` y `cancelled` filtrables por DNI/nombre).
- [ ] **RF-TRIAGE-13 (CEN-76):** Desde la pestaña **"En espera"**, los roles con `access-triage` deben poder editar los signos vitales, el motivo de consulta y los discriminadores de un triage pendiente (`pending_consultation`), recalculando la prioridad MTS; si la prioridad final cambia, el sistema debe registrar una entrada inmutable en `triage_audit_logs` conservando la trazabilidad.
- [x] **RF-TRIAGE-14 (CEN-75):** El sistema debe calcular y exponer la posición estimada en lista de espera (`#N` dentro de `pending_consultation` según jerarquía MTS y FIFO `created_at`/`id`) en la confirmación de `/pacientes/nuevo`, en el ticket digital/80 mm (`PrintTicket`) —aclarando que puede variar según urgencias entrantes— y actualizarla tras un override o edición.
- [ ] **RF-TRIAGE-15 (CEN-77):** Los campos de signos vitales en `/pacientes/nuevo` y `/triage` deben reservar padding derecho (`pr-9` para `°C`/`%`, `pr-12` para `lpm`/`rpm`, `pr-14` para `mmHg`) con `pointer-events-none` en la unidad, aislar el estado de carga del botón principal con `wire:target="save"` y utilizar los tokens de error de `docs/specs/ui/spec.md`.

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: Clasificación algorítmica determinística en prioridad ROJO
  Dado un paciente con signos vitales ingresados
  Cuando la saturación de oxígeno (SpO2) es inferior al 85% o se detecta vía aérea obstruida
  Entonces el servicio Manchester asigna prioridad "RED"
  Y el tiempo objetivo máximo de espera es de 0 minutos
  Y el discriminador registrado refleja la condición crítica de mayor gravedad.

Escenario: Clasificación por sospecha febril de dengue (Formosa Capital)
  Dado un paciente ingresado en guardia
  Cuando presenta temperatura superior a 39.0°C acompañada de cefalea o mialgias
  Entonces el servicio Manchester asigna prioridad "YELLOW"
  Y el tiempo objetivo de espera es de 60 minutos
  Y el discriminador registrado indica "Sospecha febril de dengue: fiebre > 39°C con cefalea o mialgias".

Escenario: Consulta administrativa o de rutina sin signos de alarma
  Dado un paciente que asiste por retiro de puntos de sutura o chequeo periódico
  Cuando los signos vitales se encuentran dentro de rangos fisiológicos normales
  Entonces el servicio Manchester asigna prioridad "BLUE"
  Y el tiempo de espera máximo recomendado es de 240 minutos.

Escenario: Evaluación reactiva de triage en pantalla
  Dado un usuario autenticado con rol "triage_nurse" o "doctor" en "/triage"
  Cuando ingresa los signos vitales y síntomas del paciente
  Entonces la interfaz actualiza reactivamente el badge con el color MTS correspondiente
  Y al presionar "Registrar Triage" el registro se persiste con estado "pending_consultation".

Escenario: Ticket digital al concluir la evaluación (CEN-37)
  Dado un usuario con rol "triage_nurse" que registra el triage de un paciente
  Cuando el triage se guarda
  Entonces se muestra de inmediato el ticket con el número "TR-" del triage, el color y nivel MTS asignado y la espera máxima
  Y el ticket puede imprimirse en formato de 80 mm.

Escenario: Pantalla lista para el siguiente paciente (CEN-37)
  Dado que el profesional registró el triage de un paciente
  Cuando cierra el ticket
  Entonces el formulario queda vacío y sin paciente seleccionado
  Y se mantiene visible la confirmación con la opción "Ver ticket".

Escenario: Registro con datos faltantes (CEN-37)
  Dado que el profesional no seleccionó paciente ni cargó el motivo de consulta
  Cuando presiona "Registrar Triage Clínico"
  Entonces junto al botón se muestra "No se pudo registrar el triage" con los errores
  Y no se emite ningún ticket.

Escenario: Override clínico de emergencia en un clic con justificación diferida (CEN-39)
  Dado un paciente evaluado inicialmente con prioridad "Verde"
  Cuando el profesional activa el Clinical Override a "Rojo" marcando "Justificación Diferida"
  Entonces el paciente pasa inmediatamente a prioridad "Rojo" en la cola
  Y se registra en auditoría con estado de motivo pendiente de redacción.

Escenario: Override clínico regular con justificación (CEN-39)
  Dado un paciente evaluado con prioridad "Amarillo"
  Cuando el profesional lo reclasifica a "Naranja" sin marcar "Justificación Diferida"
  Entonces debe escribir un motivo clínico de al menos 10 caracteres
  Y la auditoría guarda el motivo, el profesional, la prioridad original y la nueva.

Escenario: Gestión en /triage — En espera, edición auditada, historial y búsqueda por DNI (CEN-76)
  Dado un usuario autenticado con rol "triage_nurse" o "doctor" en "/triage"
  Cuando busca un paciente por DNI en el buscador
  Entonces en la pestaña "En espera" ve los triages pendientes ordenados por MTS con su posición #N y puede editar signos/motivo/discriminadores, reclasificar (Override) o ver ticket
  Y si la edición modifica la prioridad final, se registra una nueva fila inmutable en "triage_audit_logs"
  Y en la pestaña "Historial" consulta las atenciones anteriores en estado "in_consultation", "completed" o "cancelled".

Escenario: Posición estimada en lista de espera y corrección UI en signos vitales (CEN-75, CEN-77)
  Dado un triage registrado en prioridad "Verde" detrás de 1 paciente "Rojo" y 1 paciente "Verde" previo
  Cuando se emite la confirmación y el ticket digital TR-XXXXX
  Entonces se muestra "Posición estimada en espera: #3" en confirmación y en el ticket 80 mm
  Y las unidades de signos vitales no se superponen al texto ni bloquean clics sobre los inputs.
```

---

## 4. Modelo de Datos y Entidades

- **Tablas Afectadas:**
  - `triages`: Registro de la evaluación clínica de triage (`algorithmic_priority`, `final_priority`, `vital_signs`, `symptoms`, `status`).
  - `triage_audit_logs`: Bitácora inmutable de reclasificación clínica (*Clinical Override*).
- **Enums Involucrados:**
  - `App\Enums\TriagePriority`: RED, ORANGE, YELLOW, GREEN, BLUE.
  - `App\Enums\TriageStatus`: PENDING_CONSULTATION, CALLED (incorporado en `CEN-44`), IN_CONSULTATION, COMPLETED, CANCELLED.
- **Relaciones:**
  - `Triage belongsTo Patient`
  - `Triage belongsTo User` (profesional evaluador)
  - `Triage hasMany TriageAuditLog`
  - `Triage hasOne Consultation`

---

## 5. Endpoints y Componentes de Interfaz

- **Rutas (`routes/web.php`):**
  - `GET /triage`: Panel y formulario de evaluación clínica de triage protegido por middleware `auth`, `verified` y Gate `access-triage`.
- **Servicios:**
  - `App\Services\ManchesterTriageService`: Motor determinístico del árbol de decisión MTS.
- **Componentes Livewire:**
  - `App\Livewire\Triage\EvaluatePatient`: Formulario reactivo de toma de signos vitales, selección de discriminadores y persistencia. Al guardar emite el evento `triage-saved` (`triageId`).
  - `App\Livewire\Triage\PrintTicket` (CEN-37): escucha `triage-saved` y muestra el ticket digital imprimible (Gate `access-triage`).
  - `App\Livewire\Triage\ClinicalOverrideModal` (CEN-39): escucha `open-clinical-override` (`triageId`), aplica `Triage::overridePriority()` y emite `triage-overridden` (`triageId`). Reutilizable desde la cola médica (CEN-42).
- **Vistas:**
  - `resources/views/livewire/triage/evaluate-patient.blade.php`: Plantilla Blade con Tailwind v3, badges MTS e iconografía SVG Heroicons.
  - `resources/views/triage/index.blade.php`: Contenedor principal del módulo en layout de Centinela.
  - `resources/views/livewire/triage/ticket-modal.blade.php` (CEN-37): Modal del ticket digital con estilos de impresión de 80 mm.
  - `resources/views/livewire/triage/clinical-override-modal.blade.php` (CEN-39): Modal de Clinical Override con selector de 5 niveles MTS y justificación diferida.

---

## 6. Subtareas Técnicas Atómicas Asociadas

- [x] `CEN-35` / `T3.2`: Programación de la lógica del árbol de decisión clínico Manchester (MTS) (SP: 4) — Cubre: `RF-TRIAGE-01`, `RF-TRIAGE-02`, `RF-TRIAGE-03`, `RF-TRIAGE-04`, `RF-TRIAGE-05`, `RF-TRIAGE-06`.
- [x] `CEN-37` / `T3.4`: Emisión y visualización de ticket digital con color de prioridad asignado (SP: 4).
- [x] `CEN-38` / `T3.7`: Pruebas de integración de clasificación clínica automatizada (SP: 4).
- [x] `CEN-39` / `T3.5`: Componentes UX y modal de Clinical Override rápido con justificación diferida (SP: 4).
- [x] `CEN-40` / `T3.6`: Desarrollo de la tabla inmutable triage_audit_logs y lógica de reclasificación (SP: 4).
- [x] `CEN-41` / `T3.8`: Testing de casos de discrepancia y reordenamiento de cola por override clínico (SP: 8).
- [ ] `CEN-73` / `T4.B1`: Permiso de `PrintTicket` para `access-admission` manteniendo Override bajo `access-triage` (SP: 3).
- [x] `CEN-75` / `T4.B3`: Cálculo y visualización de posición estimada `#N` en cola y ticket 80 mm (SP: 3).
- [ ] `CEN-76` / `T4.B4`: Evolución de `/triage` con buscador por DNI, pestaña "En espera" (edición con auditoría) y pestaña "Historial" (SP: 5).
- [ ] `CEN-77` / `T4.B5`: Remediación UI/UX de campos de signos vitales, estados de carga y tokens MTS (SP: 2).
- [ ] `CEN-78` / `T4.B6`: Suite de pruebas Feature/Livewire para flujo unificado, bloqueo activo y gestión en `/triage` (SP: 3).
