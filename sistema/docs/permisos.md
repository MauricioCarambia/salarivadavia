# Permisos del sistema

## Roles existentes (tabla `roles`)

| id | nombre |
|----|--------|
| 1  | Administrador |
| 2  | Recepcionista |

## Accesos existentes (tabla `accesos`)

| id | nombre |
|----|--------|
| 1  | inicio |
| 2  | turnos |
| 3  | pacientes |
| 4  | profesionales |
| 5  | socios |
| 6  | lista_espera |
| 7  | estudios |
| 8  | caja |
| 9  | historia_pacientes |
| 10 | pagos |
| 11 | administrar empleados |
| 12 | salir |
| 14 | administrar_pagos |
| 15 | arrastre |

## Asignación actual (`roles_accesos`)

- **Administrador**: tiene los 14 accesos de la tabla, **pero además** `login.php`
  le asigna `$_SESSION['accesos'] = ['*']` (wildcard) cuando
  `rol_nombre === 'administrador'`. Esto hace que `tieneAcceso()` devuelva
  `true` para *cualquier* permiso, incluidos los que no existen en la tabla
  `accesos` (p. ej. `gestionar_roles`).
- **Recepcionista**: tiene los mismos 14 accesos **excepto** `arrastre`.
  No tiene wildcard `'*'`.

## Permiso `gestionar_roles`

Varios endpoints (`empleado_delete.php`, `empleado_rol.php`, `rol_delete.php`)
verifican `tieneAcceso('gestionar_roles')`. Este nombre **no existe** en la
tabla `accesos` ni está asignado a ningún rol. En la práctica esto significa:

- Administrador → `tieneAcceso('gestionar_roles')` es `true` (por el wildcard `'*'`).
- Recepcionista → siempre `false` (el string no está en su lista de accesos).

Es decir, **estos tres endpoints ya son admin-only de facto**, aunque el
permiso en sí sea "fantasma". Se deja así intencionalmente: crear el acceso
`gestionar_roles` en la tabla y asignarlo solo a Administrador sería más
explícito, pero cambiar la tabla de accesos es un cambio de datos que excede
el alcance de este PR de código.

## Endpoints AJAX (`sistema/ajax/*.php`)

Total: 83 archivos. Todos requieren login (sesión activa) desde el PR de
seguridad anterior. La gran mayoría de las acciones (turnos, pacientes,
caja, pagos, historia clínica, socios, estudios, lista de espera) están
disponibles para **ambos roles**, ya que Recepcionista tiene casi todos los
accesos de la tabla `accesos`.

Los únicos endpoints que añaden una verificación de permiso adicional
(más allá de "estar logueado") son los que tocan **gestión de
empleados/roles**, por ser las únicas operaciones reservadas a Administrador:

- `ajax/empleado_delete.php` → `gestionar_roles`
- `ajax/empleado_rol.php` → `gestionar_roles`
- `ajax/rol_delete.php` → `gestionar_roles`
- `ajax/empleado_edit_toggle.php` → revisar (ver tarea de mapeo)
- `ajax/empleado_pass.php` → revisar (ver tarea de mapeo)

## Revisión de queries N+1 (listados)

Se revisaron los listados principales (`socios.php`, `pacientes.php`,
`turnos_dia.php`, `turnos_profesional.php`, `historia_pacientes.php`,
`estadisticas.php`, `pagos_view.php`, `agenda_imprimir.php`,
`paciente_turnos_ver.php`, `pagos_fechas.php`, `cobrar_turno.php`,
`cobro_preview.php`).

- En `cobrar_turno.php`/`cobro_preview.php` hay queries dentro de un
  `foreach`, pero iteran sobre `$practicas`, el array de prácticas
  seleccionadas en un único cobro (típicamente 1-10 ítems). No es un
  "listado", es procesamiento de una transacción puntual: no amerita
  refactor.
- En `secciones/socios.php` (línea ~138), por cada fila del resultado se
  llama a `obtenerEstadoAfiliado($pdo, $r['Id'])`
  (`inc/services/afiliados.php`), que ejecuta una query adicional con
  `GROUP BY`/`PERIOD_DIFF`. Esto es un N+1 real, pero está acotado por el
  `LIMIT 25` de la query principal (máx. 25 queries extra por búsqueda).
  `obtenerEstadoAfiliado()` además aplica reglas de negocio (vitalicio,
  período de gracia de 90 días, clasificación "moroso") que no están
  replicadas en el SQL de `socios.php`. Convertir esto a una sola query
  batched requeriría reimplementar esa lógica de fechas en SQL, con riesgo
  de introducir diferencias sutiles en el cálculo de cuotas adeudadas. Dado
  que el impacto está acotado a 25 queries extra (no escala con la cantidad
  total de socios), se deja sin tocar por ahora.
- El resto de los listados no ejecuta queries dentro de los `foreach` de
  renderizado.

## Próximos pasos (tarea "Mapear permisos por rol")

Dado que con solo 2 roles, y que Recepcionista ya tiene casi todos los
accesos, el riesgo real de "falta de permisos granulares" está concentrado
en la administración de empleados/roles/usuarios, no en el resto de los 83
endpoints (que de todas formas serían accesibles por Recepcionista aunque se
agregara el chequeo). Por eso el mapeo se enfoca en:

1. Confirmar que **todos** los endpoints de administración de empleados/roles
   (alta, baja, edición, cambio de contraseña, cambio de rol, activar/
   desactivar) usan `requerirAcceso('gestionar_roles')`.
2. Dejar el resto de los endpoints como están (solo requieren login), ya que
   ambos roles existentes deben poder usarlos.
