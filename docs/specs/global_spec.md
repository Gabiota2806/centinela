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
1. **Admisión / Mesa de Entrada**: Registro demográfico de pacientes y apertura de guardia/consulta.
2. **Enfermero de Triage (MTS)**: Clasificación clínica Manchester (5 niveles de prioridad).
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

---

## 3. Registro de Módulos del Sistema

- **Tablero de Backlog Activo:** [backlog.md](file:///mnt/Datos/Documentos/TUP/Cuarto Cuatrimestre/TIF/centinela/docs/backlog.md) (Sprint Activo siempre al inicio).
- **Archivo Histórico de Sprints:** [backlog_archive.md](file:///mnt/Datos/Documentos/TUP/Cuarto Cuatrimestre/TIF/centinela/docs/backlog_archive.md) (Sprints 1 y 2 finalizados).

| Módulo | Directorio de la Spec | Estado | Subtareas / HUs Asociadas |
| :--- | :--- | :--- | :--- |
| **Autenticación y Roles** | `docs/specs/auth/spec.md` | *Implementado* | `CEN-10`, `CEN-14`, `CEN-15`, `CEN-16` (Sprint 2) |
| **Design System y Layouts** | `docs/specs/ui/spec.md` | *Implementado* | `CEN-12` (Sprint 2) |
| **Pacientes y Admisión** | `docs/specs/pacientes/spec.md` | *En Progreso* | `CEN-24` (Sprint 3 Activo), `CEN-34` |
| **Triage MTS y Auditoría** | `docs/specs/triage/spec.md` | *En Progreso* | `CEN-24`, `CEN-25` (Sprint 3 Activo) |
| **Consultas Médicas** | `docs/specs/consultas/spec.md` | *Planificado* | `CEN-26`, `CEN-27` (Sprint 4) |
| **Geoespacial y Telemetría** | `docs/specs/geo/spec.md` | *Planificado* | `CEN-28` a `CEN-31` (Sprints 5 a 7) |
