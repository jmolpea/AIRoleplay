# Informe de pruebas — mod_airoleplay 1.0.0 (2026-09-28)

## Calidad de código (requisitos del Marketplace)
| Comprobación | Resultado |
|---|---|
| Moodle Code Checker (`moodle-extra`, PHP) | 0 errores, 0 avisos |
| moodle-plugin-ci 4.5.11: phplint, phpcs (`--max-warnings 0`), phpdoc, savepoints, validate | Todo OK (sobre Moodle 5.3) |
| Moodle PHPdoc check (local_moodlecheck) | 0 problemas |
| ESLint + build AMD (grunt) | 0 errores; `amd/build` regenerado |
| Validador del instalador de plugins (ZIP) | VÁLIDO en 5.0, 5.1, 5.2 y 5.3 |
| phpmd | Solo métricas de complejidad (informativo, no bloquea) |

## PHPUnit (78 tests, 183 aserciones)
| Moodle | Base de datos | Resultado |
|---|---|---|
| 4.5.10+ | MariaDB 10.11 | OK |
| 5.0.10+ | MariaDB 10.11 | OK |
| 5.1.7+ | MariaDB 10.11 | OK |
| 5.2.3+ | MariaDB 10.11 | OK |
| 5.3 RC1 | PostgreSQL 17 | OK |

## Pruebas funcionales en navegador
**Moodle 4.5 local, con IA real (OpenAI):**
- Actualización 0.3.0 → 1.0.0 con datos existentes; limpieza de ajustes heredados.
- Prueba de conexión: GPT‑6 Sol responde (~2,7 s); voz OpenAI genera audio.
- Formulario: conserva el modelo antiguo (`gpt-4o`), guarda cambios, completado, fechas.
- Alumno **sin micrófono** (reproducción del incidente): pasa a respuesta escrita con explicación visible; turnos con GPT‑6 Luna ~4 s; reanudación tras recargar sin duplicar la apertura y con el reloj correcto.
- **Intento sin participación → nota 0, marcado y en revisión**; el alumno ve «pendiente de revisión».
- Cierre automático por tiempo, evaluación inmediata y evaluación de respaldo por cron.
- Profesor: validación de nota, publicar (con coma decimal), mejor nota al libro, borrar intento actualiza el libro, excepciones (crear/editar, se aplican al alumno), retirada de consentimiento RGPD.
- Voz del navegador (sin llamadas TTS al servidor), mensaje claro sin clave API, y el intento no se consume si la IA falla al empezar.
- Backup/restore (duplicar actividad) y reinicio de curso con desplazamiento de fechas.

**Moodle 5.0, 5.1, 5.2 y 5.3 (smoke test):** ajustes de admin, página de prueba de conexión, formulario (modelos, completado, avatar personalizado), envíos, excepciones, pantalla del alumno y petición AJAX de inicio.

## Incidencias del entorno (no son del plugin)
- En el curso 8 de tu Moodle local hay actividades `peerreviewstudio` y `decisionarena` cuyo código ya no está instalado; rompen el recálculo del libro de calificaciones de ese curso. El plugin ahora lo tolera, pero conviene limpiarlo.
- `$CFG->noemailever = 1` en local: no se envían correos (esperado en desarrollo).
