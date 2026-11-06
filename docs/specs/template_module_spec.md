# 📦 Especificación Modular: [Nombre del Módulo]

- **Módulo:** `[identificador_modulo]`
- **Estado:** `Borrador` | `Aprobado` | `En Implementación` | `Cerrado`
- **Última Actualización:** 2026-11-06

---

## 1. Alcance y Propósito del Módulo
Descripción de las responsabilidades funcionales y de negocio que cubre este módulo en Centinela.

---

## 2. Requerimientos Funcionales (EARS)
- **RF-[MOD]-01:** Descripción clara y no ambigua del requerimiento.
- **RF-[MOD]-02:** Descripción clara y no ambigua del requerimiento.

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: [Nombre descriptivo del escenario]
  Dado [contexto inicial o estado del sistema]
  Cuando [acción que realiza el usuario o el sistema]
  Entonces [resultado observable y verificable]

Escenario: [Caso de error o límite clínico]
  Dado [contexto inicial con datos inválidos o paciente crítico]
  Cuando [el usuario intenta realizar la acción]
  Entonces [se deniega la operación o se activa alerta correspondiente]
```

---

## 4. Modelo de Datos y Entidades
- **Tablas Afectadas:** `nombre_tabla`
- **Campos Clave y Tipos:**
  - `id`: bigint (PK)
  - `created_at`, `updated_at`
- **Relaciones con otros Módulos:**
  - `BelongsTo` / `HasMany` hacia entidades de otros módulos.

---

## 5. Endpoints y Componentes de Interfaz
- **Rutas / Controladores:**
  - `routes/web.php`
- **Componentes Livewire:**
  - `App\Livewire\Modulo\ComponenteEjemplo`
- **Políticas de Autorización (Policies/Gates):**
  - Permiso requerido: `can('access-module')`

---

## 6. Subtareas Técnicas Atómicas Asociadas
- [ ] `CEN-XX` / `T1`: [Descripción atómica] (SP: 2) — Cubre: `RF-[MOD]-01`
- [ ] `CEN-YY` / `T2`: [Descripción atómica] (SP: 3) — Cubre: `RF-[MOD]-02`
