# Análisis técnico del proyecto `portal-trabajador`

## 1) Resumen funcional
El proyecto es una aplicación PHP simple orientada a:

1. Autenticación básica por sesión (`login.php`).
2. Panel privado para búsqueda de contratos PDF por nombre de archivo (`panel.php`).
3. Visualización inline de un PDF validando ruta y extensión (`view.php`).
4. Cierre de sesión (`logout.php`) y redirección inicial (`index.php`).

## 2) Arquitectura actual
- **Tipo de aplicación**: monolito PHP sin framework, con páginas que mezclan lógica y vista.
- **Estado de sesión**: uso de `$_SESSION["user"]` para proteger panel y visualizador.
- **Búsqueda de documentos**: iteración recursiva sobre directorios configurados en `config.php`.
- **Renderizado**: HTML + CSS embebido en cada archivo.

## 3) Flujo principal
1. `index.php` redirige a `login.php`.
2. `login.php` valida credenciales hardcodeadas (`admin` / `1234`) y crea sesión.
3. `panel.php` exige sesión, recibe `q`, recorre directorios y muestra coincidencias en tabla.
4. `view.php` exige sesión, valida base/ruta y entrega el PDF con `Content-Type: application/pdf`.
5. `logout.php` destruye la sesión y vuelve a login.

## 4) Fortalezas detectadas
- Se usa `session_regenerate_id(true)` al autenticar, mitigando fijación de sesión.
- Las salidas en HTML están escapadas con `htmlspecialchars` en campos de usuario/resultados.
- `view.php` incorpora validaciones de traversal (`..`) y verificación de que el archivo final quede dentro del root permitido (`realpath` + prefijo).
- Se define `X-Content-Type-Options: nosniff` al servir PDF.

## 5) Riesgos y deuda técnica
### Seguridad
- **Crítico**: credenciales hardcodeadas en código fuente (`admin`/`1234`).
- **Alto**: no hay hash de contraseñas ni base de datos de usuarios.
- **Medio**: no hay protección CSRF para el formulario de login.
- **Medio**: no se observan flags de cookie de sesión (`secure`, `httponly`, `samesite`) configurados explícitamente.
- **Bajo/Medio**: `test.php` expone estructura de paths y estado de lectura de directorios; debería eliminarse o protegerse.

### Operación y mantenibilidad
- El proyecto depende de `config.php`, pero ese archivo no está en el árbol versionado; el despliegue puede fallar si no existe.
- Lógica de negocio y presentación están acopladas en los mismos archivos.
- No hay pruebas automatizadas ni pipeline de validación.
- CSS duplicado/embebido en varias páginas.

### Rendimiento
- `panel.php` hace recorrido recursivo completo por solicitud de búsqueda.
- No existe caché, índice ni paginación real (solo límite total), lo cual puede escalar mal con muchos PDF.

## 6) Recomendaciones priorizadas
### Prioridad 1 (inmediata)
1. Reemplazar autenticación hardcodeada por usuarios persistidos con contraseña hasheada (`password_hash`/`password_verify`).
2. Añadir un `config.example.php` versionado con instrucciones claras de configuración local/producción.
3. Restringir o retirar `test.php` de producción.
4. Configurar sesión segura (`session_set_cookie_params` con `httponly`, `samesite`, `secure` en HTTPS).

### Prioridad 2 (corto plazo)
1. Extraer utilidades comunes (sesión, respuesta de error, helpers de ruta) a archivos reutilizables.
2. Separar estilos en un CSS único para reducir duplicación.
3. Agregar manejo de errores y logging básicos.

### Prioridad 3 (medio plazo)
1. Crear índice de documentos (BD ligera o índice en disco) para evitar escaneo recursivo en cada consulta.
2. Añadir pruebas mínimas (lint PHP + tests funcionales simples).
3. Incorporar control de acceso por roles y trazabilidad (auditoría de accesos a documentos).

## 7) Comandos de verificación ejecutados
- `php -l index.php`
- `php -l login.php`
- `php -l logout.php`
- `php -l panel.php`
- `php -l view.php`
- `php -l test.php`

Resultado: todos los archivos PHP analizados pasan validación sintáctica.
