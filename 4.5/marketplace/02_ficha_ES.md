# Ficha en español — AI Roleplay

> Para tu web, materiales comerciales o si el Marketplace permite descripciones traducidas. La ficha oficial debe ir en inglés (ver `01_listing_EN.md`).

## Descripción corta
Simulaciones de rol habladas con personajes de IA y evaluación asistida por IA. El alumno conversa en voz alta con uno a tres avatares en un escenario que escribe el profesor, y recibe nota y retroalimentación personalizada al terminar o cuando el profesor la revisa.

## Descripción larga

**Dale a cada alumno una conversación realista para practicar, tantas veces como necesite.**

AI Roleplay convierte cualquier escenario en una simulación hablada. El alumno entra en una sala con uno, dos o tres personajes de IA: un cliente enfadado, un jefe exigente, un paciente, un entrevistador, un hablante nativo. Habla en voz alta, los personajes responden con voces naturales y reaccionan a lo que dice, y al final un evaluador de IA califica la conversación y explica qué funcionó y qué mejorar.

El profesor escribe el escenario en lenguaje natural. Sin árboles de diálogo ni guiones que mantener.

### Por qué lo eligen los docentes
- **Práctica real, no tipo test.** El alumno habla, escucha y piensa en el momento.
- **Retroalimentación inmediata y concreta**: nota, rúbrica (comunicación, adecuación al rol, manejo del escenario, calidad lingüística), puntos fuertes y aspectos a mejorar, citando lo que el alumno dijo.
- **El profesor mantiene el control**: transcripciones, regenerar evaluación, ajustar nota y publicar cuando quiera. Por defecto cada nota de la IA espera a que el profesor la revise; el administrador puede activar la publicación automática y bloquear la opción para todas las actividades.
- **Justo por diseño**: solo se evalúan las palabras del alumno. Si no dijo nada (p. ej. un micrófono averiado), no aprueba con las frases de los avatares: obtiene 0 y el profesor recibe el caso para revisarlo.
- **Para todos los alumnos**: pulsar para hablar con ratón, táctil o teclado; si el navegador no reconoce voz o el micrófono está bloqueado, se explica el motivo y el alumno escribe. Recargar la página reanuda la sesión con el tiempo real restante.

### Casos de uso
Atención al cliente · Ventas y negociación · Entrevistas de trabajo · Comunicación sanitaria · Liderazgo y conversaciones difíciles · Idiomas (práctica oral en más de 30 lenguas) · Debates éticos con personajes de posturas opuestas.

### Proveedor de IA a elegir (con tu propia clave)
OpenAI (GPT‑6 Sol recomendado), Anthropic (Claude Sonnet 5), Google (Gemini 3.8 Flash) o DeepSeek (V4.1 Flash / V4 Pro). Voces de OpenAI o Gemini, voces del navegador gratuitas o solo texto. Coste típico de IA por sesión de 10 minutos: 0,01–0,15 USD.

### Privacidad
Apellido, usuario y correo se sustituyen por un identificador anónimo antes de enviar nada a la IA. Aviso de consentimiento editable; el alumno puede retirarlo y borrar sus conversaciones. Claves API cifradas, límites de uso y protección contra manipulación del evaluador. Moderación opcional de OpenAI sobre las respuestas del alumno.

### Requisitos
Moodle 4.5 a 5.3 · HTTPS · clave API de un proveedor · cron activo · clave de licencia para la URL del sitio · voz en Chrome, Edge o Safari (en otros navegadores, respuesta escrita).

### Novedades de la 1.0.2
- Correcciones de la revisión del directorio de plugins de Moodle: la página de índice de actividades ya no falla; en grupos separados, el profesorado solo puede regenerar evaluaciones de sus propios grupos; cadenas de idioma que faltaban; fichero LICENSE incluido.
- El navegador se comunica con Moodle mediante Servicios Externos (`core/ajax`) en lugar de un `ajax.php` propio.
- Privacidad: el aviso de consentimiento explica que las respuestas habladas las transcribe el servicio de voz del navegador, que puede procesar el audio en los servidores de su fabricante; la exportación de datos del profesorado incluye los intentos que calificó.

### Novedades de la 1.0.1
- Conversaciones más rápidas y fiables: una respuesta de voz colgada ya no bloquea el turno dos minutos; se reintenta en segundos y cada turno tiene un límite de tiempo.
- Nuevo ajuste de sitio «Revisión del profesor antes de publicar»: revisión (por defecto) o publicación automática, con opción de bloquearlo para todas las actividades.
- Páginas reconstruidas con plantillas de Moodle y mejoras de seguridad en las excepciones de usuario/grupo.
