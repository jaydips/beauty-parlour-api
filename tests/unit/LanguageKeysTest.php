<?php

declare(strict_types=1);

use App\Controllers\Web\Settings;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The UI is Gujarati end to end: every lang('App.*') key used anywhere in app/ must exist in
 * app/Language/gu/App.php (a missing key would render as the literal "App.key"), the Gujarati
 * validation file must cover every rule the models use, and gu is the default locale.
 *
 * @internal
 */
final class LanguageKeysTest extends CIUnitTestCase
{
    public function testDefaultLocaleIsGujarati(): void
    {
        $this->assertSame('gu', config('App')->defaultLocale);
        $this->assertSame('gu', service('request')->getLocale());
        $this->assertSame('સાચવો', lang('App.save'));
    }

    public function testEveryAppKeyUsedInCodeExistsInTheGujaratiFile(): void
    {
        $defined = require APPPATH . 'Language/gu/App.php';
        $used    = [];

        foreach ($this->phpFiles(APPPATH) as $file) {
            $code = (string) file_get_contents($file);

            // lang('App.key' ...) and the 'App.key' strings used as model validation messages
            preg_match_all("/(?:lang\\(|=> |\\[')'App\\.([a-z0-9_]+)'/", $code, $m);

            foreach ($m[1] as $key) {
                if (! str_ends_with($key, '_')) { // 'App.period_' . $x and 'App.setting_' . $key are prefixes, covered below
                    $used[$key] = $file;
                }
            }
        }

        // keys built at runtime
        foreach (['morning', 'afternoon', 'evening', 'night'] as $period) {
            $used['period_' . $period] = 'format_helper';
        }
        foreach (Settings::KEYS as $key) {
            $used['setting_' . $key] = 'Settings::KEYS';
        }

        $this->assertNotEmpty($used);
        $missing = array_diff_key($used, $defined);
        $this->assertSame([], $missing, 'lang keys missing in app/Language/gu/App.php: ' . implode(', ', array_keys($missing)));

        foreach (['status', 'reminder_type', 'method', 'source', 'msg_status', 'msg_type', 'campaign_status'] as $prefix) {
            $this->assertNotEmpty(preg_grep('/^' . $prefix . '_/', array_keys($defined)), "enum labels for {$prefix}");
        }
    }

    public function testGujaratiValidationCoversTheRulesTheModelsUse(): void
    {
        $gu    = require APPPATH . 'Language/gu/Validation.php';
        $rules = [];

        foreach ($this->phpFiles(APPPATH . 'Models') as $file) {
            // only the $validationRules = [ ... ]; block of each model
            if (! preg_match('/\\$validationRules\\s*=\\s*\\[(.*?)\\];/s', (string) file_get_contents($file), $block)) {
                continue;
            }

            preg_match_all("/=> '([^']+)'/", $block[1], $m);

            foreach ($m[1] as $ruleSet) {
                foreach (explode('|', $ruleSet) as $rule) {
                    $rule = preg_replace('/\\[.*$/', '', $rule);

                    if ($rule !== '' && $rule !== 'permit_empty' && $rule !== 'if_exist') {
                        $rules[$rule] = true;
                    }
                }
            }
        }

        $this->assertContains('required', array_keys($rules));
        $this->assertSame([], array_keys(array_diff_key($rules, $gu)), 'rules without a Gujarati message');
        $this->assertStringContainsString('{field}', $gu['required']);
    }

    public function testNoEnglishDefaultTemplatesRemain(): void
    {
        foreach (\App\Database\Seeds\SettingsSeeder::DEFAULTS as $key => $value) {
            if (str_starts_with($key, 'tpl_')) {
                $this->assertMatchesRegularExpression('/[\x{0A80}-\x{0AFF}]/u', $value, "{$key} is Gujarati");
                $this->assertStringContainsString('{customer_name}', $value);
                $this->assertStringContainsString('{parlour_name}', $value);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $out = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php' && ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Language' . DIRECTORY_SEPARATOR)) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
