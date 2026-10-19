# Estrategia de Ramas — ClínicaApp

Este repositorio utiliza una **estrategia de 3 ramas** para separar el código genérico del sistema de las personalizaciones específicas por hospital.

## 📋 Ramas

### `Base` *(default branch)*

- Código **genérico** del sistema de gestión clínica
- Sirve como **tronco común** para actualizaciones
- Usada como punto de partida para **nuevos hospitales**
- No está dedicada a ningún cliente específico
- Indicador: `.active-hospital = "base"`

### `main`

- Rama histórica/mantenida por compatibilidad
- No se usa activamente para desarrollo
- Existirá por un tiempo para no romper refs existentes

### `hospital/<código>`

- Una rama por cada hospital/cliente
- Contiene **personalizaciones específicas**:
  - Logo personalizado
  - Branding hardcoded (nombre del hospital)
  - Archivos específicos (imágenes, exports, etc.)
- Ejemplos actuales: `hospital/cmhs`, `hospital/cmrm`
- Indicador: `.active-hospital = "<código>"`

## 🔄 Flujo de trabajo

### Cambios genéricos (nuevos módulos, fixes universales)
1. Crear branch desde `Base`
2. Hacer cambios, commit, push
3. PR contra `Base` → merge
4. **Propagar a hospitales**: cherry-pick selectivo a `hospital/<código>`

### Personalizaciones de un hospital existente
1. Checkout a la rama del hospital: `git checkout hospital/<código>`
2. Branch desde ahí para cambios
3. PR contra `hospital/<código>`
4. (Opcional) Si la personalización debe ser genérica, promover a `Base`

### Nuevo hospital
1. `git checkout -b hospital/<código> Base`
2. Cambiar `.active-hospital` al código del hospital
3. Aplicar personalizaciones (logo, branding)
4. Push y configurar el subdominio en Hostinger

### Propagar cambio genérico a un hospital específico
```bash
git checkout hospital/<código>
git log --oneline Base | grep -i "<keyword>"  # encontrar el commit
git cherry-pick <commit_sha>
# O mejor: integrar todas las mejoras de Base:
git rebase Base  # reescribe historial (cuidado si ya se desplegó)
```

### Promover personalización a genérico
1. Branch desde `Base`
2. Cherry-pick el commit específico del hospital
3. PR contra `Base`

## ⚠️ Precauciones

- **Nunca** hacer `git rebase Base` sobre una rama de hospital que ya esté en producción sin un plan de rollback.
- Evitar commits con `git config user.email` del hospital — usar el del equipo base.
- Las personalizaciones hardcoded (nombres, teléfonos, emails) **deben ser genéricas** en `Base` (usar placeholders o `Hospital::config('nombre')`).

## 📊 Auditoría

- Cada rama tiene su `.active-hospital` que indica qué hospital está activo
- Los commits incluyen mensajes descriptivos
- PRs contra `Base` deben describir el cambio genérico
- PRs contra `hospital/<código>` deben mencionar el hospital afectado

## 🏥 Hospitales actualmente configurados

| Hospital | Código | Rama | Subdominio |
|----------|--------|------|------------|
| Centro de Pruebas (sistema genérico) | RS1 | `Base` (default branch) | `pruebas.clinicapp.gt` |

> **Nota:** Las ramas `hospital/cmhs` y `hospital/cmrm` se mantienen como legacy solo para consulta.
> Para un nuevo hospital: `git checkout -b hospital/<codigo> Base`, asignar `.active-hospital=<codigo>`, aplicar personalizaciones y desplegar en su subdominio.
## 🔧 Notas técnicas

- Archivo `.active-hospital`: contiene el código del hospital activo en la rama actual
- Las APIs que filtran por hospital usan `$_SESSION['id_hospital']` (no `CURRENT_HOSPITAL_CODE`)
- `CURRENT_HOSPITAL_CODE` se usa solo en login para validar que el usuario pertenece al hospital del subdominio
