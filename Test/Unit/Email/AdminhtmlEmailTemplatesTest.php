<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Email;

use PHPUnit\Framework\TestCase;

/**
 * Every Zero email template declared with area="adminhtml" must be self-contained. In the adminhtml area
 * Magento_Email has no header.html/footer.html, so a {{template config_path="design/email/header_template"}}
 * (or footer_template) include throws "Template file 'header.html' is not found" and Magento sends an error text
 * instead of the email (Zero_Mcp's new connection notice did this on prod).
 */
class AdminhtmlEmailTemplatesTest extends TestCase
{
    private const INCLUDE = '#\{\{\s*template\s+config_path\s*=\s*["\']?design/email/(header|footer)_template#i';

    public function testTheCheckCatchesBothIncludes(): void
    {
        self::assertMatchesRegularExpression(self::INCLUDE, '{{template config_path="design/email/header_template"}}');
        self::assertMatchesRegularExpression(self::INCLUDE, "{{ template config_path='design/email/footer_template' }}");
        self::assertDoesNotMatchRegularExpression(self::INCLUDE, '{{template config_path="sales_email/order/template"}}');
    }

    public function testAdminhtmlTemplatesIncludeNoStorefrontHeaderOrFooter(): void
    {
        $zero = dirname(__DIR__, 4);
        $files = glob($zero . '/*/etc/email_templates.xml') ?: [];
        self::assertNotEmpty($files, 'No etc/email_templates.xml found under ' . $zero);

        $checked = [];
        $problems = [];
        foreach ($files as $xmlFile) {
            $moduleDir = dirname($xmlFile, 2);
            $xml = simplexml_load_file($xmlFile);
            self::assertNotFalse($xml, 'Unreadable ' . $xmlFile);
            foreach ($xml->template as $template) {
                if ((string) $template['area'] !== 'adminhtml') {
                    continue;
                }
                $id = (string) $template['id'];
                $module = (string) $template['module'];
                // A template of another Zero module lives in that module; anything else in the declaring one.
                $dir = str_starts_with($module, 'Zero_') ? $zero . '/' . substr($module, 5) : $moduleDir;
                $path = $dir . '/view/adminhtml/email/' . $template['file'];
                if (!is_file($path)) {
                    $problems[] = "$id: file not found ($path)";
                    continue;
                }
                $checked[] = $id;
                if (preg_match(self::INCLUDE, (string) file_get_contents($path), $match)) {
                    $problems[] = "$id ($path) includes design/email/{$match[1]}_template";
                }
            }
        }

        self::assertNotEmpty($checked, 'No Zero email template with area="adminhtml" was found: the scan is broken.');
        self::assertSame([], $problems, "Admin-area email templates must be self-contained:\n" . implode("\n", $problems));
    }
}
