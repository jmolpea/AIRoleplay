# AI Roleplay 1.0.1 — Paquete para el Moodle Marketplace

## Contenido
| Fichero | Para qué |
|---|---|
| `../mod_airoleplay_1.0.1.zip` | El ZIP que se sube (validado con el instalador oficial en Moodle 5.0–5.3) |
| `01_listing_EN.md` | Textos listos para pegar en la ficha (inglés): nombre, descripciones, requisitos, instalación, novedades, FAQ, etiquetas |
| `02_ficha_ES.md` | Versión en español para tu web y comerciales |
| `03_precio_y_estrategia.md` | Modelo de precios, planes, argumentos y riesgos |
| `04_capturas.md` + `screenshots/` | 6 capturas con sus pies de foto |
| `05_privacy_statement_EN.md` | Declaración de tratamiento de datos para compradores y revisores |
| `06_informe_pruebas.md` | Evidencia de calidad (Code Checker, moodle-plugin-ci, PHPUnit en 4.5–5.3, pruebas funcionales) |

## Pasos para publicar
1. Entra en **marketplace.moodle.com** con la misma cuenta de moodle.org → *Submit a plugin* → **Paid**.
2. Sube `mod_airoleplay_1.0.1.zip`. Deben pasar la validación del archivo y los tests de moodle-plugin-ci.
3. Completa la ficha con `01_listing_EN.md` y las capturas.
4. Configura precio y Stripe Connect (ver `03_precio_y_estrategia.md`).
5. Espera la revisión (2–4 semanas en plugins de pago; se repite cada año).
6. Cuando se apruebe, **publica tú la ficha** (no se publica sola).

## Lo que tienes que aportar tú (no lo puedo inventar)
- [ ] **Gestor de incidencias público** (p. ej. GitHub Issues). En el directorio clásico era motivo de rechazo no tenerlo.
- [ ] **URL de documentación** (una página basta: puedes adaptar el README).
- [ ] **Email/URL de soporte** y tiempos de respuesta por plan.
- [ ] **Credenciales de demo** para los revisores: un sitio con la licencia, una clave de IA con límite de gasto, un usuario alumno y uno profesor.
- [ ] **Una licencia para el revisor** (genera una para la URL que te indiquen, con caducidad de 60 días).
- [ ] **Marca coherente**: el código dice «Pluginia» (copyright) y los ajustes dicen «licencia proporcionada por RSMAX Consulting». Decide qué nombre aparece al comprador y lo unifico.
- [ ] **Derechos de las imágenes/vídeos de los avatares** para uso comercial.
- [ ] **Logo/icono** de la ficha (el icono de la actividad está en `pix/monologo.svg`).

## Cómo generar licencias para clientes
El generador y la clave privada están ahora en `AIRoleplay/licensing_private/` (fuera del plugin, fuera de git):
```
cd AIRoleplay/licensing_private
php generate_license.php
```
Pide la URL exacta del Moodle del cliente (`$CFG->wwwroot`, con https y sin barra final), la fecha de caducidad (vacía = permanente) y la edición.
