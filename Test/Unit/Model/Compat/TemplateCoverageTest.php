<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Compat;

use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Compat\TemplateCoverage;

/**
 * A module counts as served by the theme through templates alone when it has no templates of its own and every
 * template its storefront layouts name is overridden in one of the theme directories. Each test builds a module
 * and a theme in a fresh temporary directory.
 */
class TemplateCoverageTest extends TestCase
{
    private string $root;
    private string $module;
    private string $theme;
    private TemplateCoverage $coverage;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zero-template-coverage-' . bin2hex(random_bytes(6));
        $this->module = $this->root . '/vendor/magento/module-re-captcha-customer';
        $this->theme = $this->root . '/app/design/frontend/Zero/default';
        mkdir($this->module . '/view/frontend/layout', 0777, true);
        mkdir($this->theme, 0777, true);
        $this->coverage = new TemplateCoverage();
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testEveryTemplateOverriddenInTheThemeIsCovered(): void
    {
        $this->layout('customer_account_login.xml', $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml'));
        $this->layout('customer_account_create.xml', $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml'));
        $this->override($this->theme, 'Magento_ReCaptchaFrontendUi', 'recaptcha.phtml');

        self::assertTrue($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testOneTemplateWithoutAnOverrideIsNotCovered(): void
    {
        $this->layout(
            'customer_account_login.xml',
            $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml')
            . $this->block('Magento_ReCaptchaFrontendUi::recaptcha_extra.phtml')
        );
        $this->override($this->theme, 'Magento_ReCaptchaFrontendUi', 'recaptcha.phtml');

        self::assertFalse($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testATemplateSetThroughATemplateArgumentCounts(): void
    {
        $this->layout('contact_index_index.xml', $this->argument('Magento_ReCaptchaFrontendUi::recaptcha.phtml'));
        $this->override($this->theme, 'Magento_ReCaptchaFrontendUi', 'recaptcha.phtml');

        self::assertTrue($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testATemplateArgumentWithoutAnOverrideIsNotCovered(): void
    {
        $this->layout(
            'contact_index_index.xml',
            $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml')
            . $this->argument('Magento_ReCaptchaContact::form/recaptcha.phtml')
        );
        $this->override($this->theme, 'Magento_ReCaptchaFrontendUi', 'recaptcha.phtml');

        self::assertFalse($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testTheOverrideMayLiveInAnyOfTheThemes(): void
    {
        $parent = $this->root . '/app/design/frontend/Zero/base';
        $this->layout(
            'customer_account_login.xml',
            $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml')
            . $this->argument('Magento_ReCaptchaCustomer::sub/dir/login.phtml')
        );
        $this->override($this->theme, 'Magento_ReCaptchaFrontendUi', 'recaptcha.phtml');
        $this->override($parent, 'Magento_ReCaptchaCustomer', 'sub/dir/login.phtml');

        self::assertTrue($this->coverage->coversAll($this->module, [$this->theme, $parent]));
    }

    public function testAnOverrideUnderAnotherModuleDoesNotCount(): void
    {
        $this->layout('customer_account_login.xml', $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml'));
        $this->override($this->theme, 'Magento_ReCaptchaCustomer', 'recaptcha.phtml');

        self::assertFalse($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testAModuleWithItsOwnTemplatesIsNotCovered(): void
    {
        // Magento_ReCaptchaNewsletter style: the module ships a template of its own, the theme cannot be the whole story.
        mkdir($this->module . '/view/frontend/templates', 0777, true);
        $this->layout('default.xml', $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml'));
        $this->override($this->theme, 'Magento_ReCaptchaFrontendUi', 'recaptcha.phtml');

        self::assertFalse($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testLayoutsThatNameNoTemplateAreNotCovered(): void
    {
        $this->layout('default.xml', '<referenceBlock name="head.additional" remove="true"/>');

        self::assertFalse($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testAModuleWithoutStorefrontLayoutsIsNotCovered(): void
    {
        $this->remove($this->module . '/view');

        self::assertFalse($this->coverage->coversAll($this->module, [$this->theme]));
    }

    public function testWithoutAThemeNothingIsCovered(): void
    {
        $this->layout('customer_account_login.xml', $this->block('Magento_ReCaptchaFrontendUi::recaptcha.phtml'));
        $this->override($this->theme, 'Magento_ReCaptchaFrontendUi', 'recaptcha.phtml');

        self::assertFalse($this->coverage->coversAll($this->module, []));
    }

    private function block(string $template): string
    {
        return '<block class="Magento\ReCaptchaUi\Block\ReCaptcha" name="recaptcha" after="-" template="'
            . $template . '" ifconfig="recaptcha_frontend/type_for/customer_login"/>';
    }

    private function argument(string $template): string
    {
        return '<referenceBlock name="contactForm"><arguments>' . "\n"
            . '    <argument name="template" xsi:type="string">' . "\n        " . $template . "\n    </argument>\n"
            . '</arguments></referenceBlock>';
    }

    private function layout(string $file, string $body): void
    {
        file_put_contents(
            $this->module . '/view/frontend/layout/' . $file,
            '<?xml version="1.0"?>' . "\n"
            . '<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">'
            . '<body>' . $body . '</body></page>'
        );
    }

    private function override(string $themeDir, string $module, string $path): void
    {
        $file = $themeDir . '/' . $module . '/templates/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, '<?php // override');
    }

    private function remove(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
