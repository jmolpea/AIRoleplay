# Precio y estrategia de venta — AI Roleplay

> Recomendaciones, no datos de mercado verificados. Ajusta con tu experiencia comercial.

## Cómo funciona la venta de pago en el Moodle Marketplace (septiembre 2026)
- Se admiten plugins de pago, típicamente como **suscripción anual** (incluye actualizaciones y soporte durante el periodo).
- Cobro con **Stripe Connect**; tú eres el comerciante (*Merchant of Record*): facturas, IVA/GST y reembolsos corren de tu cuenta.
- Comisión de Moodle HQ: **20 % el primer año, 25 % después**.
- Los plugins de pago pasan **revisión al enviarlos y cada año** (2–4 semanas). Tras aprobarse, **la ficha la publicas tú**.
- El envío ejecuta comprobaciones automáticas (validación del ZIP y moodle-plugin-ci). Este plugin las pasa (ver `06_informe_pruebas.md`).

## Modelo recomendado: licencia anual por sitio + «trae tu propia clave de IA»
El cliente paga la IA directamente a su proveedor. Ventajas: sin riesgo de costes variables para ti, el cliente usa sus acuerdos de privacidad existentes (clave para universidades y administraciones) y el precio del plugin es fácil de justificar.

| Plan | Para quién | Precio orientativo / año | Qué incluye |
|---|---|---|---|
| **Starter** | Centros pequeños, academias (≤ 1.000 usuarios activos) | 290–390 USD | 1 sitio, actualizaciones, soporte por email (48 h laborables) |
| **Institution** | Universidades, empresas (≤ 10.000 usuarios) | 890–1.190 USD | 1 sitio producción + 1 staging, soporte prioritario (24 h), sesión de onboarding de 1 h |
| **Enterprise** | Multisitio, redes, administraciones | a medida | Varios sitios, SLA, ayuda con DPA y evaluación de impacto |

Tu validador ya soporta licencias con caducidad (`expires`) y edición (`edition`): úsalo para planes anuales y pruebas.

**Prueba gratuita:** licencia de 14–30 días para la URL del cliente (genérala con fecha de caducidad). Es la palanca de conversión más potente para una actividad que el profesor tiene que *ver funcionando*.

## Argumentos de venta (en orden de fuerza)
1. **Práctica oral real y evaluada sin horas de profesor**: un grupo de 30 alumnos puede hacer 3 intentos cada uno sin corregir 90 conversaciones a mano.
2. **Coste de IA transparente y bajo**: ~0,01–0,15 USD por sesión, pagados al proveedor que ya usa la institución.
3. **Justo y auditable**: solo se califica lo que dice el alumno; transcripción completa; el profesor publica.
4. **Accesible**: funciona sin micrófono (respuesta escrita) y en cualquier idioma.
5. **Privacidad**: anonimización, consentimiento, Privacy API, elección de proveedor (incluida la advertencia de DeepSeek/China).
6. **Compatibilidad a futuro**: Moodle 4.5 LTS → 5.3 LTS, cuatro proveedores de IA.

## Acciones para vender más
- **Vídeo de 60–90 s** para la ficha: un alumno hablando con dos avatares y el resultado apareciendo al final. Es lo que más convierte en este tipo de plugin.
- **Plantillas de escenarios listos** (atención al cliente, entrevista, feedback a un colaborador, consulta médica, conversación B1 de inglés/español) como copias de seguridad `.mbz` descargables: reducen el tiempo hasta el «efecto wow».
- **Sitio de demo** con usuario alumno y profesor (el Marketplace pide credenciales de demo a los revisores; sirve también para ventas).
- **Casos de uso por sector** en la web (educación superior, formación corporativa, sanidad, idiomas).
- **Descuento de lanzamiento** el primer trimestre (compensado en parte por la comisión reducida del 20 % del primer año).

## Riesgos comerciales a tener en cuenta
- **GPL**: el código se distribuye bajo GPL v3, así que un comprador puede legalmente modificarlo y quitar la comprobación de licencia. Lo que vendes realmente son las actualizaciones, el soporte y la continuidad. Es el modelo habitual en Moodle; la licencia técnica solo disuade el uso casual.
- **Derechos de imagen de los avatares**: confirma que tienes licencia comercial de las tres personas/vídeos de `pix/avatars` (si son generados por IA o de banco de imágenes, guarda la licencia).
- **Cambios de los proveedores de IA**: retiran modelos cada pocos meses (DeepSeek retiró `deepseek-chat` el 24/07/2026). El plugin ya redirige modelos retirados y reintenta sin parámetros opcionales, pero presupuesta una actualización de catálogo cada 3–4 meses.
