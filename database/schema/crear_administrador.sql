-- Crea el usuario administrador inicial. Usuario: admin   Clave temporal: ver el mensaje de Claude (cambiala al entrar).
-- Podés cambiar usu_usuario, usu_email, usu_nombre y usu_cedula antes de ejecutarlo.
INSERT INTO usuarios (rol_id, usu_cedula, usu_nombre, usu_apellido, usu_usuario, usu_password, usu_activo, usu_email)
SELECT (SELECT rol_id FROM roles WHERE rol_nombre = 'Administrador'), '0000000', 'Administrador', 'Sistema', 'admin', '$2y$10$TFPGjw2ihJmSMH516o.OsOF5GtceZ/Pldzce9Z7Kejzb/3.Mm/sIG', true, 'admin@ejemplo.com'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usu_usuario = 'admin');
