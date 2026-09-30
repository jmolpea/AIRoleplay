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
 * Unit tests for the model catalogue and defaults.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay;

use mod_airoleplay\form\mod_form_helper;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_airoleplay\form\mod_form_helper::class)]
/**
 * Tests for {@see \mod_airoleplay\form\mod_form_helper}.
 *
 * @covers \mod_airoleplay\form\mod_form_helper
 */
final class model_catalog_test extends \advanced_testcase {
    public function test_every_recommended_model_is_in_its_provider_catalogue(): void {
        $this->resetAfterTest();
        $catalog = mod_form_helper::model_catalog();
        foreach (array_keys($catalog) as $provider) {
            foreach ([mod_form_helper::PURPOSE_ROLEPLAY, mod_form_helper::PURPOSE_EVALUATION] as $purpose) {
                $model = mod_form_helper::default_model($purpose, $provider);
                $this->assertArrayHasKey($model, $catalog[$provider], "$provider/$purpose");
                $this->assertSame($provider, mod_form_helper::provider_for_model($model));
            }
        }
    }

    public function test_retired_models_resolve_to_current_ones(): void {
        $this->assertSame('deepseek-flash', mod_form_helper::resolve_model('deepseek-chat'));
        $this->assertSame('gemini-3.8-flash', mod_form_helper::resolve_model('gemini-2.0-flash'));
        $this->assertSame('gpt-6-sol', mod_form_helper::resolve_model('gpt-6-sol'));
    }

    public function test_current_model_is_kept_in_the_options(): void {
        $this->resetAfterTest();
        set_config('chat_provider', 'anthropic', 'mod_airoleplay');
        $options = mod_form_helper::get_model_options(['gpt-4o']);
        $this->assertArrayHasKey('claude-sonnet-5', $options);
        $this->assertArrayHasKey('gpt-4o', $options);
    }

    public function test_activity_model_falls_back_to_the_site_default(): void {
        $this->resetAfterTest();
        set_config('chat_provider', 'gemini', 'mod_airoleplay');
        $activity = (object)['openai_model_roleplay' => '', 'openai_model_eval' => 'deepseek-reasoner'];
        $this->assertSame('gemini-3.8-flash', mod_form_helper::activity_model($activity, mod_form_helper::PURPOSE_ROLEPLAY));
        $this->assertSame('deepseek-flash', mod_form_helper::activity_model($activity, mod_form_helper::PURPOSE_EVALUATION));
    }

    public function test_browser_voices_offer_a_single_automatic_voice(): void {
        $this->resetAfterTest();
        set_config('tts_provider', 'browser', 'mod_airoleplay');
        $this->assertSame(['auto'], array_keys(mod_form_helper::get_voice_options()));
        $this->assertNull(\mod_airoleplay\api\provider_factory::tts_provider());
    }
}
