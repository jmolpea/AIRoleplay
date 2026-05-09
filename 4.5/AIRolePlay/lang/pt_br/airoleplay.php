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
 * Brazilian Portuguese language strings for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activityname']       = 'Nome da atividade';

$string['airoleplay:addinstance']         = 'Adicionar uma atividade AI Roleplay';
$string['airoleplay:grade']               = 'Avaliar submissões';
$string['airoleplay:manageoverrides']     = 'Gerenciar exceções de usuários e grupos';
$string['airoleplay:manageplugin']        = 'Gerenciar configurações do plugin';
$string['airoleplay:submit']              = 'Participar de uma sessão de roleplay';
$string['airoleplay:view']                = 'Visualizar atividade AI Roleplay';
$string['airoleplay:viewallsubmissions']  = 'Ver todas as submissões';

$string['attemptsinfo']   = 'Tentativas usadas: {$a->used} / {$a->max} ({$a->remaining} restantes)';

$string['avatar_1']           = 'Avatar 1 (neutro)';
$string['avatar_2']           = 'Avatar 2 (feminino)';
$string['avatar_3']           = 'Avatar 3 (masculino)';
$string['avatar_custom']      = 'Imagem personalizada';
$string['avatar_header']      = 'Avatar {$a}';
$string['avatar_name']        = 'Nome';
$string['avatar_prompt']      = 'Personalidade e estilo de interação';
$string['avatar_prompt_help'] = 'Descreva como este avatar se comporta, sua personalidade, tom e papel dentro do cenário.';
$string['avatar_role']        = 'Função / Título';
$string['avatar_visual']      = 'Aparência do avatar';
$string['avatar_voice']       = 'Voz TTS';
$string['avatar_custom_upload'] = 'Enviar imagem personalizada do avatar';

$string['avatars_header']     = 'Avatares';

$string['col_actions']        = 'Ações';
$string['col_grade']          = 'Nota';
$string['col_status']         = 'Status';
$string['col_student']        = 'Estudante';
$string['col_submitted']      = 'Enviado';
$string['col_workflow']       = 'Fluxo de trabalho';

$string['completiongrade']    = 'O estudante deve receber uma nota';
$string['completionsubmit']   = 'O estudante deve completar a sessão de roleplay';

$string['confirm_delete_submission'] = 'Tem certeza de que deseja excluir esta submissão? Esta ação não pode ser desfeita.';

$string['content_flagged']    = 'O conteúdo foi sinalizado pelo filtro de segurança da IA.';

$string['conversation_log']   = 'Registro da sessão';

$string['delete_submission']  = 'Excluir submissão';

$string['dimension_communication']    = 'Comunicação';
$string['dimension_role_adherence']   = 'Aderência ao papel';
$string['dimension_scenario_handling'] = 'Condução do cenário';
$string['dimension_language_quality'] = 'Qualidade da linguagem';

$string['error_duration_invalid']  = 'A duração deve ser de pelo menos 1 minuto.';

$string['evaluation_complete']  = '✅ Avaliação concluída. Redirecionando…';
$string['evaluation_pending']   = 'A IA está avaliando seu desempenho. Isso pode levar um momento…';

$string['evaluator_invalid_response'] = 'O avaliador de IA retornou uma resposta inválida. Por favor, entre em contato com seu instrutor.';

$string['event_assessment_completed'] = 'Avaliação por IA concluída';
$string['event_grade_issued']         = 'Nota emitida';
$string['event_submission_created']   = 'Sessão de roleplay iniciada';

$string['feedback']           = 'Feedback';

$string['gdpr_consent_label']   = 'Entendo e concordo que minhas respostas faladas serão processadas pela API da OpenAI.';
$string['gdpr_consent_required'] = 'Você deve dar seu consentimento na página da atividade antes de iniciar a sessão.';
$string['gdpr_default_notice']  = '<p>Para completar esta atividade, suas respostas faladas durante a sessão de roleplay serão enviadas para a <strong>API da OpenAI</strong> para conversação e avaliação por IA.</p><p>Os dados não são retidos pela OpenAI além da solicitação imediata.</p><p>Ao continuar, você consente com este processamento de acordo com nossa política de privacidade.</p>';
$string['gdpr_notice_title']    = 'Aviso de privacidade — Processamento por IA';

$string['grade_breakdown']       = 'Detalhamento da nota';
$string['grade_override_saved']  = 'Nota salva com sucesso.';
$string['grade_pending_review']  = 'Sua nota está sendo revisada pelo seu instrutor. Você será notificado quando for publicada.';

$string['gradenotification_body']     = <<<'EOT'
Sua nota para '{$a->activityname}' em '{$a->coursename}' foi publicada.

Nota: {$a->grade}

Ver seus resultados: {$a->link}
EOT;
$string['gradenotification_bodyhtml'] = '<p>Sua nota para <strong>{$a->activityname}</strong> em <em>{$a->coursename}</em> foi publicada.</p><p>Nota: <strong>{$a->grade}</strong></p><p><a href="{$a->link}">Ver seus resultados</a></p>';
$string['gradenotification_small']    = 'Nota publicada para {$a->activityname}';
$string['gradenotification_subject']  = 'Sua nota está pronta: {$a->activityname}';

$string['grading_header']     = 'Avaliação e fluxo de trabalho';
$string['grading_workflow']   = 'Ativar fluxo de revisão de notas';
$string['grading_workflow_help'] = 'Se ativado, as notas ficam retidas para revisão do professor antes de serem publicadas aos estudantes.';

$string['groupsubmission']     = 'Submissão em grupo';
$string['groupsubmission_help'] = 'Permite que grupos participem juntos. Requer que os grupos estejam configurados no curso.';

$string['invalidsubmissionstatus'] = 'Esta ação não é permitida no estado atual da submissão.';

$string['maxattempts']         = 'Tentativas máximas';
$string['maxattempts_help']    = 'Número máximo de vezes que um estudante pode tentar esta atividade. Defina 0 para ilimitado.';
$string['maximumgrade']        = 'Nota máxima';

$string['model_economical']    = '(econômico)';
$string['model_recommended']   = '(recomendado)';
$string['models_header']       = 'Modelos de IA';

$string['modulename']          = 'AI Roleplay';
$string['modulenameplural']    = 'AI Roleplays';

$string['no_overrides_yet']    = 'Nenhuma exceção foi configurada.';
$string['no_submissions_yet']  = 'Ainda não há submissões.';
$string['noinstances']         = 'Não há atividades AI Roleplay neste curso.';
$string['notify_student']      = 'Notificar o estudante quando a nota for publicada';

$string['num_avatars']         = 'Número de avatares';
$string['num_avatars_help']    = 'Escolha quantos avatares participam do roleplay (1, 2 ou 3). Quando múltiplos avatares estão ativos, eles se revezam para responder, e o participante pode se dirigir a um avatar específico pelo nome.';

$string['openai_api_error']    = 'Erro do serviço de IA: {$a}';
$string['openai_model_eval']   = 'Modelo de IA para avaliação final';
$string['openai_model_roleplay'] = 'Modelo de IA para a conversa do roleplay';

$string['override_add']            = 'Adicionar exceção';
$string['override_confirm_delete'] = 'Tem certeza de que deseja excluir esta exceção?';
$string['override_delete']         = 'Excluir exceção';
$string['override_deleted']        = 'Exceção excluída.';
$string['override_edit']           = 'Editar exceção';
$string['override_group']          = 'Grupo';
$string['override_maxattempts']    = 'Tentativas máximas';
$string['override_saved']          = 'Exceção salva.';
$string['override_timeclose']      = 'Fechamento';
$string['override_timeopen']       = 'Abertura';
$string['override_type']           = 'Tipo de exceção';
$string['override_type_group']     = 'Exceção de grupo';
$string['override_type_user']      = 'Exceção de usuário';
$string['override_user']           = 'Usuário';
$string['overrides_heading']       = 'Exceções de usuário/grupo';

$string['participant_role']        = 'Papel do participante';
$string['participant_role_help']   = 'Descreva o personagem ou papel que o participante desempenha neste cenário. É mostrado ao participante antes do início da sessão.';

$string['pluginadministration']    = 'Administração do AI Roleplay';
$string['pluginname']              = 'AI Roleplay';

$string['privacy:metadata:airoleplay_submissions']                  = 'Informações sobre a sessão de roleplay de cada estudante, incluindo a transcrição da conversa e as notas.';
$string['privacy:metadata:airoleplay_submissions:final_feedback']   = 'O texto de feedback final fornecido ao estudante.';
$string['privacy:metadata:airoleplay_submissions:final_grade']      = 'A nota final atribuída ao estudante.';
$string['privacy:metadata:airoleplay_submissions:gdpr_consent']     = 'Se o estudante deu consentimento LGPD.';
$string['privacy:metadata:airoleplay_submissions:gdpr_consent_time'] = 'Quando o estudante deu o consentimento LGPD.';
$string['privacy:metadata:airoleplay_submissions:roleplay_transcript'] = 'Transcrição completa da sessão de roleplay.';
$string['privacy:metadata:airoleplay_submissions:status']           = 'Status atual da submissão.';
$string['privacy:metadata:airoleplay_submissions:timecreated']      = 'Quando a submissão foi criada.';
$string['privacy:metadata:airoleplay_submissions:timesubmitted']    = 'Quando a sessão foi concluída.';
$string['privacy:metadata:airoleplay_submissions:userid']           = 'O ID do estudante que participou.';
$string['privacy:metadata:airoleplay_messages']                     = 'Registro detalhado de cada turno na sessão de roleplay.';
$string['privacy:metadata:airoleplay_messages:message_text']        = 'O texto do que foi dito.';
$string['privacy:metadata:airoleplay_messages:speaker']             = 'Quem falou neste turno (avatar ou participante).';
$string['privacy:metadata:airoleplay_messages:timestamp']           = 'Quando este turno ocorreu.';
$string['privacy:metadata:openai']                                  = 'As respostas faladas são enviadas para a API da OpenAI para conversação e avaliação por IA. Os dados não são retidos além da solicitação imediata.';
$string['privacy:metadata:openai:conversation_turns']               = 'O texto das respostas faladas do participante durante a sessão de roleplay.';

$string['publish_grade']       = 'Publicar nota';
$string['push_to_talk']        = 'Segure para responder';

$string['rate_limit_exceeded'] = 'Você fez muitas solicitações. Por favor, aguarde um momento antes de tentar novamente.';

$string['regen_confirm']       = 'Isso substituirá a avaliação atual por uma nova. Continuar?';
$string['regen_cooldown']      = 'Por favor, aguarde antes de regenerar novamente. Esta operação tem um período de espera para evitar uso excessivo da API.';
$string['regen_evaluation']    = 'Recalcular avaliação final';
$string['regen_heading']       = 'Regenerar avaliação de IA';
$string['regen_running']       = 'Processando… por favor, aguarde (pode levar 1–3 minutos)';
$string['regen_success']       = 'Concluído! Recarregando…';

$string['results_title']       = 'Seus resultados';
$string['return_to_student']   = 'Devolver para revisão';

$string['roleplay_ending']     = 'A sessão está sendo concluída…';
$string['roleplay_finished']   = 'Sessão encerrada. Sua avaliação está sendo preparada…';
$string['roleplay_loading']    = 'Iniciando o roleplay…';
$string['roleplay_prompt_eval'] = 'Instruções de avaliação';
$string['roleplay_prompt_eval_help'] = 'Instruções para a IA gerar a nota final e o feedback. Deixe em branco para usar os critérios de avaliação padrão.';
$string['roleplay_ready_notice'] = 'Você está prestes a iniciar a sessão de roleplay. Assim que pressionar o botão, o cronômetro começará e os avatares começarão a interagir com você. Você não poderá pausar a sessão.';
$string['roleplay_ready_title'] = 'Pronto para começar?';
$string['roleplay_start_btn']   = 'Iniciar roleplay';
$string['roleplay_thinking']    = 'O avatar está respondendo…';

$string['safety_extra_prompt']      = 'Restrições de conteúdo adicionais (opcional)';
$string['safety_extra_prompt_help'] = 'Instruções de segurança extras adicionadas a cada chamada de API para esta atividade.';
$string['security_header']          = 'Segurança da atividade';

$string['scenario_description']       = 'Descrição do cenário';
$string['scenario_description_help']  = 'Descreva a situação para o roleplay. Este contexto é fornecido aos avatares e mostrado ao participante antes do início da sessão.';
$string['scenario_header']            = 'Cenário e papel do participante';
$string['scenario_label']             = 'Cenário';

$string['session_duration']           = 'Duração da sessão (minutos)';
$string['session_duration_help']      = 'Duração máxima da sessão de roleplay. Quando o cronômetro acabar, a sessão encerra e a avaliação começa.';

$string['settings_advanced_heading']         = 'Avançado';
$string['settings_anonymize_desc']           = 'Os nomes reais dos estudantes são <strong>sempre</strong> substituídos por um identificador anônimo antes de serem enviados à OpenAI.';
$string['settings_anonymize_heading']        = 'Anonimização de estudantes';
$string['settings_anonymize_salt']           = 'Salt de anonimização';
$string['settings_anonymize_salt_desc']      = 'String aleatória adicionada ao hash para segurança extra.';
$string['settings_api_rate_limit']           = 'Máx. chamadas API por usuário por minuto';
$string['settings_api_rate_limit_desc']      = 'Limite de taxa por usuário do Moodle para prevenir abuso da API.';
$string['settings_api_timeout']              = 'Tempo limite da requisição API (segundos)';
$string['settings_api_timeout_desc']         = 'Tempo máximo de espera por uma resposta da OpenAI.';
$string['settings_apikeys_heading']          = 'Chaves de API da OpenAI';
$string['settings_apikeys_heading_desc']     = 'Essas chaves são armazenadas criptografadas.';
$string['settings_cost_estimate_desc']       = 'Custo estimado por sessão completa de 10 minutos:<br/>GPT-4o: ~$0.05–$0.15 USD &nbsp;|&nbsp; GPT-4o mini: ~$0.01–$0.03 USD<br/>TTS: ~$0.01 por resposta do avatar';
$string['settings_cost_estimate_heading']    = 'Estimativas de custo';
$string['settings_enable_gpt4o']             = 'Ativar GPT-4o';
$string['settings_enable_gpt4o_desc']        = 'GPT-4o — maior qualidade, maior custo.';
$string['settings_enable_gpt4o_mini']        = 'Ativar GPT-4o mini';
$string['settings_enable_gpt4o_mini_desc']   = 'GPT-4o mini — boa qualidade, menor custo.';
$string['settings_gdpr_heading']             = 'Aviso LGPD';
$string['settings_gdpr_heading_desc']        = 'Este aviso é mostrado aos participantes antes de começar.';
$string['settings_gdpr_notice_text']         = 'Texto do aviso LGPD';
$string['settings_gdpr_notice_text_desc']    = 'Texto HTML mostrado aos participantes.';
$string['settings_models_heading']           = 'Modelos de IA disponíveis';
$string['settings_models_heading_desc']      = 'Selecione quais modelos os professores podem escolher.';
$string['settings_openai_apikey']            = 'Chave de API primária da OpenAI';
$string['settings_openai_apikey_desc']       = 'Sua chave de API da OpenAI. Usada para GPT-4o e TTS.';
$string['settings_openai_apikey_secondary']  = 'Chave de API secundária da OpenAI (opcional)';
$string['settings_openai_apikey_secondary_desc'] = 'Se configurada, as chamadas TTS usarão esta chave.';
$string['settings_safety_content_filter']    = 'Ativar moderação de conteúdo da OpenAI';
$string['settings_safety_content_filter_desc'] = 'Executa todo o conteúdo do usuário pela API de Moderação da OpenAI antes de enviar ao GPT.';
$string['settings_safety_max_tokens']        = 'Máximo de tokens por chamada API';
$string['settings_safety_max_tokens_desc']   = 'Limite rígido de tokens de saída para todas as chamadas de API.';
$string['settings_security_heading']         = 'Segurança';
$string['settings_security_heading_desc']    = 'Configure os filtros de segurança aplicados a todas as chamadas de IA.';
$string['settings_storage_heading']          = 'Armazenamento e retenção';
$string['settings_storage_heading_desc']     = 'Configure os limites de armazenamento de arquivos.';

$string['start_activity']      = 'Estou pronto para começar';

$string['submission']          = 'Submissão';
$string['submission_deleted']  = 'Submissão excluída.';

$string['submissionnotification_body']     = <<<'EOT'
Um estudante ({$a->studentname}) concluiu '{$a->activityname}' em '{$a->coursename}' e sua sessão está pronta para sua revisão.

Ver submissões: {$a->link}
EOT;
$string['submissionnotification_bodyhtml'] = '<p>O estudante <strong>{$a->studentname}</strong> concluiu <em>{$a->activityname}</em> e sua sessão está pronta para revisão.</p><p><a href="{$a->link}">Ver submissões</a></p>';
$string['submissionnotification_small']    = 'Nova submissão: {$a->activityname}';
$string['submissionnotification_subject']  = 'Nova submissão para revisão: {$a->activityname}';
$string['submissions_heading']             = 'Submissões';

$string['task_evaluate_submission'] = 'AI Roleplay: Gerar avaliação final';

$string['unlimited']           = 'Ilimitado';

$string['voice_alloy']   = 'Alloy — versátil, neutro';
$string['voice_echo']    = 'Echo — ressonante, masculino';
$string['voice_fable']   = 'Fable — expressivo, sotaque britânico';
$string['voice_nova']    = 'Nova — caloroso, feminino';
$string['voice_onyx']    = 'Onyx — profundo, autoritário';
$string['voice_shimmer'] = 'Shimmer — suave, claro';

$string['warning_1min']  = '⚠️ 1 minuto restante';
$string['warning_2min']  = '⚠️ 2 minutos restantes';

$string['workflow_inreview']        = 'Em revisão';
$string['workflow_readyforrelease'] = 'Pronto para publicar';
$string['workflow_released']        = 'Publicado';

$string['your_grade']       = 'Sua nota:';
$string['your_role_label']  = 'Seu papel neste cenário:';
