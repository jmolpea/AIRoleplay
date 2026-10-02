# Informe de pruebas — mod_airoleplay 1.0.1 (2026-09-30)

## Calidad de código
| Comprobación | Resultado |
|---|---|
| Moodle Code Checker (`moodle-extra`, PHP) | 0 errores, 0 avisos (incluidos los metadatos `@covers` de los tests) |
| Moodle PHPdoc check (local_moodlecheck) | 0 problemas |
| `php -l` en todos los ficheros | Sin errores |
| ESLint (configuración de Moodle) | 0 errores (JS sin cambios respecto a 1.0.0) |
| Plantillas Mustache | Las 4 renderizan su contexto de ejemplo con HTML válido |
| Validador del instalador de plugins (ZIP 1.0.1) | VÁLIDO en 5.0, 5.1, 5.2 y 5.3 |

## PHPUnit (85 tests, 227 aserciones)
| Moodle | Base de datos | Resultado |
|---|---|---|
| 4.5.10+ | MariaDB 10.11 | OK |
| 5.0.10+ | MariaDB 10.11 | OK |
| 5.1.7+ | MariaDB 10.11 | OK |
| 5.2.3+ | MariaDB 10.11 | OK |
| 5.3 RC1 | PostgreSQL 17 | OK |

Actualización 1.0.0 → 1.0.1 correcta en las cinco versiones (crea el ajuste `grading_workflow`).

## Pruebas específicas de 1.0.1
- **Latencia (incidente reportado):** en los registros, GPT‑6 Sol respondía en 1–3 s; los turnos lentos eran la voz de OpenAI colgada 120 s. Simulado un corte a mitad de transmisión: ahora se recupera en 14 s con audio completo; si el servicio no responde nunca, el turno se corta a los 25 s. Medido en real: moderación 0,3–2,3 s, chat 1,2–2,6 s, voz 1,3–1,5 s.
- La apertura y el cierre ya no llaman a la moderación (verificado en el registro del servidor).
- **Revisión del profesor / calificación automática:** ajuste de sitio visible, valor por defecto aplicado a actividades nuevas, bloqueo que deshabilita la casilla en el formulario con aviso, y bloqueo aplicado también a actividades existentes (tests unitarios).
- Navegador (Moodle 4.5, español): consentimiento, resultados, nuevo intento, reanudar sesión, cierre → evaluación → «en revisión», detalle del profesor con avisos, publicar y regenerar.

---

# Informe de pruebas — mod_airoleplay 1.0.0 (2026-09-28, histórico)

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

---

# Pruebas de la 1.0.2 (2026-10-02) — correcciones de la revisión de Moodle

| Comprobación | Resultado |
|---|---|
| Moodle Code Checker (`moodle` y `moodle-extra`) sobre todo el plugin | 0 errores, 0 avisos |
| moodle-plugin-ci: phplint, phpcs `--max-warnings 0`, phpdoc `--max-warnings 0`, validate, savepoints | Todo OK (sobre Moodle 5.3) |
| ESLint + compilación `grunt amd` (Moodle 4.5) | 0 errores |
| PHPUnit (89 tests, 236 aserciones; 4 tests nuevos) | OK en Moodle 4.5, 5.0, 5.1, 5.2 y 5.3 |
| ZIP 1.0.2 | Incluye `LICENSE`, `db/services.php`, `classes/external/`; no incluye `ajax.php` |

## Pruebas funcionales (Moodle 4.5, navegador, proveedor de IA real)
- `index.php` lista las actividades sin error (también comprobado en Moodle 5.3).
- Sesión completa de estudiante por los nuevos servicios: `start_session` (apertura con audio), `submit_turn`, recarga y reanudación, `close_session` al agotarse el tiempo, `finalise_session` → intento calificado y resultados en pantalla.
- Profesor: `regenerate_evaluation` regenera la evaluación; la segunda llamada devuelve el mensaje de espera (`regen_cooldown`).
- Control de grupos en la regeneración: cubierto por test unitario (profesor de otro grupo → `nopermissions`).

No ejecutado en esta ronda: mustache lint y PHPMD de moodle-plugin-ci (sin npm en el contenedor de pruebas; las plantillas no han cambiado), el diálogo de confirmación de «Regenerar» en navegador real (el navegador sin cabeza no lo mostró; se llamó al servicio directamente) y la entrada por voz con micrófono.
