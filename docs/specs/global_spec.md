# 🌐 Especificación Técnica Global: Centinela

## 1. Arquitectura General y Topología
- **Nombre del Sistema:** Centinela (Sistema de Gestión de Triage Digital y Alerta Temprana Epidemiológica para CAPS)
- **Institución:** UTN FRRe – Extensión Áulica Formosa Capital (IPP) / Red de Centros de Atención Primaria de la Salud (CAPS).
- **Patrón Arquitectónico:** **Distribuida "Edge-to-Hub"**.
  * **Nodo Periférico (Edge - Este Repositorio):** Laravel 13 con Livewire, Alpine.js y Tailwind CSS instalado en servidores locales de cada CAPS para funcionamiento offline-first.
  * **Motor Central (Hub):** FastAPI (Python 3.12) con Scikit-learn para analítica geoespacial y correlación meteorológica (Open-Meteo).
  * **Protocolo de Sincronización:** Outbox Pattern asíncrono con reintentos vía endpoint seguro `POST /api/v1/telemetry` autenticado con API Key (`X-CAPS-Key`). Los datos identificatorios del paciente nunca salen de la red local del CAPS (Ley N° 25.326).
- **Entorno de Ejecución:** Contenerizado con Docker Compose (`centinela_app` con PHP 8.5.12, Composer 2.11.2, Node 26.13.0 y `centinela_postgis` con PostgreSQL 18 + PostGIS 3.7).
- **Ramas Git:** Base de producción `main`, integración `develop`.

---

## 2. Convenciones Transversales del Proyecto

### A. Autenticación y Matriz RBAC (4 Roles)
Implementado mediante Enums nativos de PHP 8.3 (`App\Enums\UserRole`) desacoplados con Laravel Policies y Gates:
1. **Admisión / Mesa de Entrada**: Registro demográfico de pacientes junto con signos vitales iniciales y apertura de triage/guardia.
2. **Enfermero de Triage (MTS)**: Admisión de pacientes, toma/edición de signos vitales, clasificación clínica Manchester (5 niveles de prioridad), seguimiento de espera/historial y reclasificación auditada.
3. **Médico de Guardia**: Atención de la consulta médica, diagnóstico, prescripción y *Clinical Override*.
4. **Autoridad Sanitaria / Director de CAPS**: Visualización epidemiológica agregada y métricas de atención.

### B. Protocolo Clínico de Triage (Manchester Triage System - MTS)
- 5 niveles estandarizados por colores y tiempos objetivo de espera:
  * 🔴 **Rojo (Nivel 1 - Emergencia / Reanimación):** Atención inmediata (0 min).
  * 🟠 **Naranja (Nivel 2 - Muy Urgente):** Máximo 10 min.
  * 🟡 **Amarillo (Nivel 3 - Urgente):** Máximo 60 min.
  * 🟢 **Verde (Nivel 4 - Poco Urgente):** Máximo 120 min.
  * 🔵 **Azul (Nivel 5 - No Urgente):** Máximo 240 min.
- **Clinical Override**: Si el médico o enfermero discrepan con la clasificación algorítmica, pueden reclasificar justificando el motivo clínico. Se registra obligatoriamente en la tabla de auditoría inmutable `triage_audit_logs`.

### C. Base de Datos y Persistencia Espacial
- Motor: **PostgreSQL 18 + PostGIS 3.7** (`postgis/postgis:18-3.7`).
- Tipos de geometrías espaciales: coordenadas de CAPS y geometrías poligonales de barrios de Formosa Capital en SRID 4326 con índices espaciales GIST.
- Convención de nombres: tablas en plural snake_case (`patients`, `triages`, `consultations`, `neighborhoods`, `triage_audit_logs`).
- Especificación formal del esquema y diagrama Entidad-Relación (ERD): ver [database_dictionary.md](file:///opt/lampp/htdocs/centinela/docs/database_dictionary.md).

### D. Frontend, Estilos e Iconografía
- Ecosistema: **Laravel Blade + Livewire + Alpine.js**.
- CSS: **Tailwind CSS v3** con configuración en `tailwind.config.js`.
- Iconografía: **SVGs vectoriales exclusivos** (Heroicons / Lucide) con clases `w-4 h-4` o `w-5 h-5`. **TERMINANTEMENTE PROHIBIDO** el uso de emojis de texto en vistas o modales.
- Design System (tokens, tipografía local Plus Jakarta Sans, logo y layouts): ver `docs/specs/ui/spec.md`.

### E. Control de Versiones, Pull Requests e Integración con Jira
- **Regla estricta de alcance de claves Jira (`CEN-xxx`)**: El repositorio está vinculado a Jira Cloud con automatizaciones que transicionan tarjetas al crear ramas (`En curso`), abrir Pull Requests (`En revisión`) y fusionar Pull Requests (`Listo`).
  * **Superficies alcanzadas:** Nombre de rama, título y **cuerpo** de commits, y título y **descripción** de Pull Requests.
  * **Nombre de rama:** Solo puede contener **una** clave `CEN-xxx` (la clave de la Historia de Usuario o sub-vertical asignada en `.github/jira-scopes.conf`, o **ninguna** en ramas de mantenimiento/documentación).
  * **Commits y Pull Requests:** Solo pueden figurar las claves autorizadas para esa rama en `.github/jira-scopes.conf` (o, en su defecto, la clave de la HU y sus subtareas hijas en `docs/backlog.md`). Esto permite una rama por HU/sub-vertical con commits etiquetados por subtarea (`feat(CEN-48): ...`, `feat(CEN-49): ...`) manteniendo bloqueadas épicas, otras HUs y tarjetas de otros integrantes.
  * **En tareas sin ticket Jira** (mantenimiento, documentación, contratos base): **cero** claves `CEN-<número>`.
  * **Cómo citar contexto fuera del alcance:** Utilizar nombres técnicos o funcionales (p. ej. "lógica de reclasificación", "paleta corporativa", "Sprint 3") o códigos locales del proyecto (`RF-TRIAGE-10`, `T4.x`) sin el prefijo `CEN-`.
  * **Archivos del repositorio:** Dentro de `docs/specs/`, `docs/backlog.md` y `docs/changelog.md` las referencias cruzadas siguen permitidas porque Jira no escanea el contenido de archivos.
- **Controles automáticos y auditoría:** El workflow `.github/workflows/jira-key-guard.yml` valida de forma bloqueante cada PR contra `.github/jira-scopes.conf` (omitiendo únicamente el merge de release `develop -> main`). Además, el repositorio provee hooks locales portables en `.githooks/` (`git config core.hooksPath .githooks`) para frenar infracciones antes del push. En `/auditoria` y `@reviewer`, cualquier clave `CEN-xxx` fuera del alcance autorizado implica `VEREDICTO: CAMBIOS NECESARIOS`.

---

## 3. Registro de Módulos del Sistema

- **Contratos Transversales (Sprints 4 a 7):** `docs/specs/00_contracts.md` *(fuente canónica de estados, evento `ConsultationCompleted`, tablas base y APIs Edge↔Hub)*.
- **Tablero de Backlog Activo:** `docs/backlog.md` (Sprint Activo siempre al inicio).
- **Archivo Histórico de Sprints:** `docs/backlog_archive.md` (Sprints 1, 2 y 3 finalizados).

| Módulo | Directorio de la Spec | Estado | Subtareas / HUs Asociadas |
| :--- | :--- | :--- | :--- |
| **Contratos Transversales** | `docs/specs/00_contracts.md` | *Congelado* | Sprints 4 a 7 (Edge ↔ Hub) |
| **Autenticación y Roles** | `docs/specs/auth/spec.md` | *En Progreso* | `CEN-10`, `CEN-14`, `CEN-15`, `CEN-16` (Sprint 2), `CEN-73` (Sprint 4 Bugfix) |
| **Design System y Layouts** | `docs/specs/ui/spec.md` | *Implementado* | `CEN-12` (Sprint 2) |
| **Pacientes y Admisión** | `docs/specs/pacientes/spec.md` | *En Progreso* | `CEN-24`, `CEN-34`, `CEN-36` (Sprint 3), `CEN-72`–`CEN-78` (Sprint 4 Bugfix) |
| **Triage MTS y Auditoría** | `docs/specs/triage/spec.md` | *En Progreso* | `CEN-24`, `CEN-25`, `CEN-35` a `CEN-41` (Sprint 3), `CEN-72`–`CEN-78` (Sprint 4 Bugfix) |
| **Consultas Médicas** | `docs/specs/consultas/spec.md` | *En Progreso* | `CEN-26`, `CEN-27` (Sprint 4 Activo) |
| **Geoespacial y Telemetría** | `docs/specs/geo/spec.md` | *En Progreso* | `CEN-28` a `CEN-31` (Sprints 5 a 7) |
