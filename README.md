# Portal trabajador

## Mejoras para múltiples usuarios persistidos (MariaDB)

### 1) Configurar base de datos
1. Crea la BD y tabla:
   ```bash
   mysql -u root -p < database.sql
   ```
2. Configura variables de entorno antes de levantar PHP:
   ```bash
   export DB_HOST=127.0.0.1
   export DB_PORT=3306
   export DB_NAME=portal_trabajador
   export DB_USER=root
   export DB_PASS=tu_password
   ```

### 2) Crear usuarios
```bash
php create_user.php juan MiClaveSegura123!
```

### 3) Configurar rutas de documentos
Copia `config.example.php` como `config.php` y ajusta rutas.

```bash
cp config.example.php config.php
```

## Notas
- El login ahora valida contra MariaDB (`users.username`, `users.password_hash`) usando `password_verify`.
- Se mantiene autenticación por sesión para `panel.php` y `view.php`.
