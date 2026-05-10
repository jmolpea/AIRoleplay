<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activityname']       = 'Nombre de la actividad';

$string['airoleplay:addinstance']         = 'Añadir una actividad AI Roleplay';
$string['airoleplay:grade']               = 'Calificar entregas';
$string['airoleplay:manageoverrides']     = 'Gestionar excepciones de usuarios y grupos';
$string['airoleplay:manageplugin']        = 'Gestionar configuración del plugin';
$string['airoleplay:submit']              = 'Participar en una sesión de juego de roles';
$string['airoleplay:view']                = 'Ver la actividad AI Roleplay';
$string['airoleplay:viewallsubmissions']  = 'Ver todas las entregas';

$string['attemptsinfo']   = 'Intentos usados: {$a->used} / {$a->max} ({$a->remaining} restantes)';

$string['avatar_1']           = 'Avatar 1 (neutro)';
$string['avatar_2']           = 'Avatar 2 (femenino)';
$string['avatar_3']           = 'Avatar 3 (masculino)';
$string['avatar_custom']      = 'Imagen personalizada';
$string['avatar_custom_upload'] = 'Subir imagen personalizada del avatar';

$string['avatar_header']      = 'Avatar {$a}';
$string['avatar_name']        = 'Nombre';
$string['avatar_prompt']      = 'Personalidad y estilo de interacción';
$string['avatar_prompt_help'] = 'Describe cómo se comporta este avatar, su personalidad, tono y rol dentro del escenario.';
$string['avatar_role']        = 'Rol / Título';
$string['avatar_visual']      = 'Apariencia del avatar';
$string['avatar_voice']       = 'Voz TTS';
$string['avatars_header']     = 'Avatares';

$string['col_actions']        = 'Acciones';
$string['col_grade']          = 'Calificación';
$string['col_status']         = 'Estado';
$string['col_student']        = 'Estudiante';
$string['col_submitted']      = 'Enviado';
$string['col_workflow']       = 'Flujo de trabajo';

$string['completiongrade']    = 'El estudiante debe recibir una calificación';
$string['completionsubmit']   = 'El estudiante debe completar la sesión de juego de roles';

$string['confirm_delete_submission'] = '¿Está seguro de que desea eliminar esta entrega? Esta acción no se puede deshacer.';

$string['content_flagged']    = 'El contenido fue marcado por el filtro de seguridad de la IA.';

$string['conversation_log']   = 'Registro de la sesión';

$string['delete_submission']  = 'Eliminar entrega';

$string['dimension_communication']    = 'Comunicación';
$string['dimension_language_quality'] = 'Calidad del lenguaje';

$string['dimension_role_adherence']   = 'Adherencia al rol';
$string['dimension_scenario_handling'] = 'Manejo del escenario';
$string['error_duration_invalid']  = 'La duración debe ser al menos 1 minuto.';

$string['evaluation_complete']  = '✅ Evaluación completada. Redirigiendo…';
$string['evaluation_pending']   = 'La IA está evaluando tu desempeño. Esto puede tardar un momento…';

$string['evaluator_invalid_response'] = 'El evaluador de IA devolvió una respuesta inválida. Por favor, contacta a tu instructor.';

$string['event_assessment_completed'] = 'Evaluación IA completada';
$string['event_grade_issued']         = 'Calificación emitida';
$string['event_submission_created']   = 'Sesión de juego de roles iniciada';

$string['feedback']           = 'Retroalimentación';

$string['gdpr_consent_label']   = 'Entiendo y acepto que mis respuestas habladas serán procesadas por la API de OpenAI.';
$string['gdpr_consent_required'] = 'Debes dar tu consentimiento en la página de la actividad antes de iniciar la sesión.';
$string['gdpr_default_notice']  = '<p>Para completar esta actividad, tus respuestas habladas durante la sesión de juego de roles serán enviadas a la <strong>API de OpenAI</strong> para la conversación y evaluación por IA.</p><p>OpenAI no conserva los datos más allá de la solicitud inmediata.</p><p>Al continuar, consientes este procesamiento de acuerdo con nuestra política de privacidad.</p>';
$string['gdpr_notice_title']    = 'Aviso de privacidad — Procesamiento por IA';

$string['grade_breakdown']       = 'Desglose de la calificación';
$string['grade_override_saved']  = 'Calificación guardada correctamente.';
$string['grade_pending_review']  = 'Tu calificación está siendo revisada por tu instructor. Se te notificará cuando sea publicada.';

$string['gradenotification_body']     = <<<'EOT'
Tu calificación para '{$a->activityname}' en '{$a->coursename}' ha sido publicada.

Calificación: {$a->grade}

Ver tus resultados: {$a->link}
EOT;
$string['gradenotification_bodyhtml'] = '<p>Tu calificación para <strong>{$a->activityname}</strong> en <em>{$a->coursename}</em> ha sido publicada.</p><p>Calificación: <strong>{$a->grade}</strong></p><p><a href="{$a->link}">Ver tus resultados</a></p>';
$string['gradenotification_small']    = 'Calificación publicada para {$a->activityname}';
$string['gradenotification_subject']  = 'Tu calificación está lista: {$a->activityname}';

$string['grading_header']     = 'Calificación y flujo de trabajo';
$string['grading_workflow']   = 'Activar flujo de revisión de calificaciones';
$string['grading_workflow_help'] = 'Si está activado, las calificaciones se retienen para revisión del profesor antes de publicarse a los estudiantes.';

$string['groupsubmission']     = 'Entrega grupal';
$string['groupsubmission_help'] = 'Permite que grupos realicen la actividad juntos. Requiere que los grupos estén configurados en el curso.';

$string['invalidsubmissionstatus'] = 'Esta acción no está permitida en el estado actual de la entrega.';

$string['maxattempts']         = 'Intentos máximos';
$string['maxattempts_help']    = 'Número máximo de veces que un estudiante puede intentar esta actividad. Establece 0 para ilimitados.';
$string['maximumgrade']        = 'Calificación máxima';

$string['model_economical']    = '(económico)';
$string['model_recommended']   = '(recomendado)';
$string['models_header']       = 'Modelos de IA';

$string['modulename']          = 'AI Roleplay';
$string['modulenameplural']    = 'AI Roleplays';

$string['no_overrides_yet']    = 'No se han configurado excepciones.';
$string['no_submissions_yet']  = 'Aún no hay entregas.';
$string['noinstances']         = 'No hay actividades AI Roleplay en este curso.';
$string['notify_student']      = 'Notificar al estudiante cuando se publique la calificación';

$string['num_avatars']         = 'Número de avatares';
$string['num_avatars_help']    = 'Elige cuántos avatares participan en el juego de roles (1, 2 o 3). Cuando hay múltiples avatares activos, se turnan para responder, y el participante puede dirigirse a un avatar específico por nombre.';

$string['openai_api_error']    = 'Error del servicio de IA: {$a}';
$string['openai_model_eval']   = 'Modelo de IA para evaluación final';
$string['openai_model_roleplay'] = 'Modelo de IA para la conversación del juego de roles';

$string['override_add']            = 'Añadir excepción';
$string['override_confirm_delete'] = '¿Está seguro de que desea eliminar esta excepción?';
$string['override_delete']         = 'Eliminar excepción';
$string['override_deleted']        = 'Excepción eliminada.';
$string['override_edit']           = 'Editar excepción';
$string['override_group']          = 'Grupo';
$string['override_maxattempts']    = 'Intentos máximos';
$string['override_saved']          = 'Excepción guardada.';
$string['override_timeclose']      = 'Cierre';
$string['override_timeopen']       = 'Apertura';
$string['override_type']           = 'Tipo de excepción';
$string['override_type_group']     = 'Excepción de grupo';
$string['override_type_user']      = 'Excepción de usuario';
$string['override_user']           = 'Usuario';
$string['overrides_heading']       = 'Excepciones de usuario/grupo';

$string['participant_role']        = 'Rol del participante';
$string['participant_role_help']   = 'Describe el personaje o rol que desempeña el participante en este escenario. Se muestra al participante antes de iniciar la sesión.';

$string['pluginadministration']    = 'Administración de AI Roleplay';
$string['pluginname']              = 'AI Roleplay';

$string['privacy:metadata:airoleplay_messages']                     = 'Registro detallado de cada turno en la sesión de juego de roles.';
$string['privacy:metadata:airoleplay_messages:message_text']        = 'El texto de lo que se dijo.';
$string['privacy:metadata:airoleplay_messages:speaker']             = 'Quién habló en este turno (avatar o participante).';
$string['privacy:metadata:airoleplay_messages:timestamp']           = 'Cuándo ocurrió este turno.';
$string['privacy:metadata:airoleplay_submissions']                  = 'Información sobre la sesión de juego de roles de cada estudiante, incluyendo la transcripción de la conversación y las calificaciones.';
$string['privacy:metadata:airoleplay_submissions:final_feedback']   = 'El texto de retroalimentación final proporcionado al estudiante.';
$string['privacy:metadata:airoleplay_submissions:final_grade']      = 'La calificación final otorgada al estudiante.';
$string['privacy:metadata:airoleplay_submissions:gdpr_consent']     = 'Si el estudiante dio su consentimiento RGPD.';
$string['privacy:metadata:airoleplay_submissions:gdpr_consent_time'] = 'Cuándo el estudiante dio su consentimiento RGPD.';
$string['privacy:metadata:airoleplay_submissions:roleplay_transcript'] = 'Transcripción completa de la sesión de juego de roles.';
$string['privacy:metadata:airoleplay_submissions:status']           = 'Estado actual de la entrega.';
$string['privacy:metadata:airoleplay_submissions:timecreated']      = 'Cuándo se creó la entrega.';
$string['privacy:metadata:airoleplay_submissions:timesubmitted']    = 'Cuándo se completó la sesión.';
$string['privacy:metadata:airoleplay_submissions:userid']           = 'El ID del estudiante que participó.';
$string['privacy:metadata:openai']                                  = 'Las respuestas habladas se envían a la API de OpenAI para la conversación y evaluación por IA. Los datos no se conservan más allá de la solicitud inmediata.';
$string['privacy:metadata:openai:conversation_turns']               = 'El texto de las respuestas habladas del participante durante la sesión de juego de roles.';

$string['publish_grade']       = 'Publicar calificación';
$string['push_to_talk']        = 'Mantén pulsado para responder';

$string['rate_limit_exceeded'] = 'Has realizado demasiadas solicitudes. Por favor, espera un momento antes de intentarlo de nuevo.';

$string['regen_confirm']       = 'Esto reemplazará la evaluación actual por una nueva. ¿Continuar?';
$string['regen_cooldown']      = 'Por favor, espera antes de regenerar de nuevo. Esta operación tiene un período de espera para evitar el uso excesivo de la API.';
$string['regen_evaluation']    = 'Recalcular evaluación final';
$string['regen_heading']       = 'Regenerar evaluación de IA';
$string['regen_running']       = 'Procesando… por favor, espera (puede tardar 1-3 minutos)';
$string['regen_success']       = '¡Hecho! Recargando…';

$string['results_title']       = 'Tus resultados';
$string['return_to_student']   = 'Devolver para revisión';

$string['roleplay_ending']     = 'La sesión está concluyendo…';
$string['roleplay_finished']   = 'Sesión finalizada. Tu evaluación está siendo preparada…';
$string['roleplay_loading']    = 'Iniciando el juego de roles…';
$string['roleplay_prompt_eval'] = 'Instrucciones de evaluación';
$string['roleplay_prompt_eval_help'] = 'Instrucciones para la IA para generar la calificación final y la retroalimentación. Déjalo en blanco para usar los criterios de evaluación predeterminados.';
$string['roleplay_ready_notice'] = 'Estás a punto de iniciar la sesión de juego de roles. Una vez que pulses el botón, el temporizador comenzará y los avatares empezarán a interactuar contigo. No podrás pausar la sesión.';
$string['roleplay_ready_title'] = '¿Listo para comenzar?';
$string['roleplay_start_btn']   = 'Iniciar juego de roles';
$string['roleplay_thinking']    = 'El avatar está respondiendo…';

$string['safety_extra_prompt']      = 'Restricciones de contenido adicionales (opcional)';
$string['safety_extra_prompt_help'] = 'Instrucciones de seguridad adicionales que se añaden a cada llamada a la API para esta actividad.';
$string['scenario_description']       = 'Descripción del escenario';
$string['scenario_description_help']  = 'Describe la situación para el juego de roles. Este contexto se proporciona a los avatares y se muestra al participante antes de iniciar la sesión.';
$string['scenario_header']            = 'Escenario y rol del participante';
$string['scenario_label']             = 'Escenario';

$string['security_header']          = 'Seguridad de la actividad';

$string['session_duration']           = 'Duración de la sesión (minutos)';
$string['session_duration_help']      = 'Duración máxima de la sesión de juego de roles. Cuando se acabe el temporizador, la sesión termina y comienza la evaluación.';

$string['settings_advanced_heading']         = 'Avanzado';
$string['settings_anonymize_desc']           = 'Los nombres reales de los estudiantes son <strong>siempre</strong> reemplazados por un identificador anónimo antes de enviarlos a OpenAI.';
$string['settings_anonymize_heading']        = 'Anonimización de estudiantes';
$string['settings_anonymize_salt']           = 'Sal de anonimización';
$string['settings_anonymize_salt_desc']      = 'Cadena aleatoria añadida al hash para mayor seguridad.';
$string['settings_api_rate_limit']           = 'Máx. llamadas API por usuario por minuto';
$string['settings_api_rate_limit_desc']      = 'Límite de velocidad por usuario de Moodle para prevenir el abuso de la API.';
$string['settings_api_timeout']              = 'Tiempo de espera de la solicitud API (segundos)';
$string['settings_api_timeout_desc']         = 'Tiempo máximo de espera para una respuesta de OpenAI.';
$string['settings_apikeys_heading']          = 'Claves API de OpenAI';
$string['settings_apikeys_heading_desc']     = 'Estas claves se almacenan encriptadas.';
$string['settings_cost_estimate_desc']       = 'Coste estimado por sesión completa de 10 minutos:<br/>GPT-4o: ~$0.05–$0.15 USD &nbsp;|&nbsp; GPT-4o mini: ~$0.01–$0.03 USD<br/>TTS: ~$0.01 por respuesta del avatar';
$string['settings_cost_estimate_heading']    = 'Estimaciones de coste';
$string['settings_enable_gpt4o']             = 'Activar GPT-4o';
$string['settings_enable_gpt4o_desc']        = 'GPT-4o — máxima calidad, mayor coste.';
$string['settings_enable_gpt4o_mini']        = 'Activar GPT-4o mini';
$string['settings_enable_gpt4o_mini_desc']   = 'GPT-4o mini — buena calidad, menor coste.';
$string['settings_gdpr_heading']             = 'Aviso RGPD';
$string['settings_gdpr_heading_desc']        = 'Este aviso se muestra a los participantes antes de comenzar.';
$string['settings_gdpr_notice_text']         = 'Texto del aviso RGPD';
$string['settings_gdpr_notice_text_desc']    = 'Texto HTML mostrado a los participantes.';
$string['settings_models_heading']           = 'Modelos de IA disponibles';
$string['settings_models_heading_desc']      = 'Selecciona los modelos que los profesores pueden elegir.';
$string['settings_openai_apikey']            = 'Clave API principal de OpenAI';
$string['settings_openai_apikey_desc']       = 'Tu clave API de OpenAI. Usada para GPT-4o y TTS.';
$string['settings_openai_apikey_secondary']  = 'Clave API secundaria de OpenAI (opcional)';
$string['settings_openai_apikey_secondary_desc'] = 'Si está configurada, las llamadas TTS usarán esta clave.';
$string['settings_safety_content_filter']    = 'Activar moderación de contenido de OpenAI';
$string['settings_safety_content_filter_desc'] = 'Ejecuta todo el contenido del usuario a través de la API de Moderación de OpenAI antes de enviarlo a GPT.';
$string['settings_safety_max_tokens']        = 'Máximo de tokens por llamada API';
$string['settings_safety_max_tokens_desc']   = 'Límite estricto de tokens de salida para todas las llamadas API.';
$string['settings_security_heading']         = 'Seguridad';
$string['settings_security_heading_desc']    = 'Configura los filtros de seguridad aplicados a todas las llamadas de IA.';
$string['settings_storage_heading']          = 'Almacenamiento y retención';
$string['settings_storage_heading_desc']     = 'Configura los límites de almacenamiento de archivos.';

$string['start_activity']      = 'Estoy listo para comenzar';

$string['submission']          = 'Entrega';
$string['submission_deleted']  = 'Entrega eliminada.';

$string['submissionnotification_body']     = <<<'EOT'
Un estudiante ({$a->studentname}) ha completado '{$a->activityname}' en '{$a->coursename}' y su sesión está lista para tu revisión.

Ver entregas: {$a->link}
EOT;
$string['submissionnotification_bodyhtml'] = '<p>El estudiante <strong>{$a->studentname}</strong> ha completado <em>{$a->activityname}</em> y su sesión está lista para revisión.</p><p><a href="{$a->link}">Ver entregas</a></p>';
$string['submissionnotification_small']    = 'Nueva entrega: {$a->activityname}';
$string['submissionnotification_subject']  = 'Nueva entrega para revisión: {$a->activityname}';
$string['submissions_heading']             = 'Entregas';

$string['task_evaluate_submission'] = 'AI Roleplay: Generar evaluación final';

$string['unlimited']           = 'Ilimitado';

$string['voice_alloy']   = 'Alloy — versátil, neutro';
$string['voice_echo']    = 'Echo — resonante, masculino';
$string['voice_fable']   = 'Fable — expresivo, acento británico';
$string['voice_nova']    = 'Nova — cálido, femenino';
$string['voice_onyx']    = 'Onyx — profundo, autoritario';
$string['voice_shimmer'] = 'Shimmer — suave, claro';

$string['warning_1min']  = '⚠️ 1 minuto restante';
$string['warning_2min']  = '⚠️ 2 minutos restantes';

$string['workflow_inreview']        = 'En revisión';
$string['workflow_readyforrelease'] = 'Listo para publicar';
$string['workflow_released']        = 'Publicado';

$string['your_grade']       = 'Tu calificación:';
$string['your_role_label']  = 'Tu rol en este escenario:';
