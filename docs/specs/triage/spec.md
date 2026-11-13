# 📦 Especificación Modular: Módulo de Triage Clínico (Sistema Manchester - MTS)

- **Módulo:** `triage`
- **Estado:** `En Implementación`
- **Última Actualización:** 2026-11-12
- **Subtareas asociadas:**
  - [x] `CEN-35` (Programación de la lógica del árbol de decisión clínico Manchester - MTS)
  - [ ] `CEN-37` (Emisión y visualización de ticket digital con color de prioridad asignado)
  - [ ] `CEN-38` (Pruebas de integración de clasificación clínica automatizada)
  - [ ] `CEN-39` (Componentes UX y modal de Clinical Override rápido con justificación diferida)
  - [ ] `CEN-40` (Desarrollo de la tabla inmutable triage_audit_logs y lógica de reclasificación)
  - [ ] `CEN-41` (Testing de casos de discrepancia y reordenamiento de cola por override clínico)

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
```

---

## 4. Modelo de Datos y Entidades

- **Tablas Afectadas:**
  - `triages`: Registro de la evaluación clínica de triage (`algorithmic_priority`, `final_priority`, `vital_signs`, `symptoms`, `status`).
  - `triage_audit_logs`: Bitácora inmutable de reclasificación clínica (*Clinical Override*).
- **Enums Involucrados:**
  - `App\Enums\TriagePriority`: RED, ORANGE, YELLOW, GREEN, BLUE.
  - `App\Enums\TriageStatus`: PENDING_CONSULTATION, IN_CONSULTATION, COMPLETED, CANCELLED.
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
  - `App\Livewire\Triage\EvaluatePatient`: Formulario reactivo de toma de signos vitales, selección de discriminadores y persistencia.
- **Vistas:**
  - `resources/views/livewire/triage/evaluate-patient.blade.php`: Plantilla Blade con Tailwind v3, badges MTS e iconografía SVG Heroicons.
  - `resources/views/triage/index.blade.php`: Contenedor principal del módulo en layout de Centinela.

---

## 6. Subtareas Técnicas Atómicas Asociadas

- [x] `CEN-35` / `T3.2`: Programación de la lógica del árbol de decisión clínico Manchester (MTS) (SP: 4) — Cubre: `RF-TRIAGE-01`, `RF-TRIAGE-02`, `RF-TRIAGE-03`, `RF-TRIAGE-04`, `RF-TRIAGE-05`, `RF-TRIAGE-06`.
- [ ] `CEN-37` / `T3.4`: Emisión y visualización de ticket digital con color de prioridad asignado (SP: 4).
- [ ] `CEN-38` / `T3.7`: Pruebas de integración de clasificación clínica automatizada (SP: 4).
- [ ] `CEN-39` / `T3.5`: Componentes UX y modal de Clinical Override rápido con justificación diferida (SP: 4).
- [ ] `CEN-40` / `T3.6`: Desarrollo de la tabla inmutable triage_audit_logs y lógica de reclasificación (SP: 4).
- [ ] `CEN-41` / `T3.8`: Testing de casos de discrepancia y reordenamiento de cola por override clínico (SP: 8).
