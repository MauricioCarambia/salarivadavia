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
