<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Compat;

/**
 * Is a module served by a Zero theme through templates alone? True when the
 * module has no templates of its own and every template its storefront layouts
 * name (template="Vendor_Module::path.phtml") is overridden in a theme as
 * <theme>/<Vendor_Module>/templates/<path>. Magento's reCAPTCHA modules are
 * such: their layouts only place a block that renders the one template the
 * Zero theme replaces.
 */
class TemplateCoverage
{
    /**
     * @param string $moduleDir
     * @param string[] $themeDirs
     * @return bool
     */
    public function coversAll(string $moduleDir, array $themeDirs): bool
    {
        if (!$themeDirs || is_dir($moduleDir . '/view/frontend/templates')) {
            return false;
        }
        $layouts = glob($moduleDir . '/view/frontend/layout/*.xml') ?: [];
        $templates = [];
        foreach ($layouts as $layout) {
            $xml = (string) file_get_contents($layout);
            preg_match_all(
                '/\btemplate="([A-Za-z0-9]+_[A-Za-z0-9]+)::([^"]+)"/',
                $xml,
                $matches,
                PREG_SET_ORDER
            );
            // <argument name="template" xsi:type="string">Vendor_Module::path.phtml</argument>
            preg_match_all(
                '/<argument\s+name="template"[^>]*>\s*([A-Za-z0-9]+_[A-Za-z0-9]+)::([^<\s]+)\s*</',
                $xml,
                $arguments,
                PREG_SET_ORDER
            );
            foreach (array_merge($matches, $arguments) as $match) {
                $templates[$match[1] . '::' . $match[2]] = [$match[1], $match[2]];
            }
        }
        if (!$templates) {
            return false;
        }
        foreach ($templates as [$module, $path]) {
            if (!$this->overridden($module, $path, $themeDirs)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $module
     * @param string $path
     * @param string[] $themeDirs
     * @return bool
     */
    private function overridden(string $module, string $path, array $themeDirs): bool
    {
        foreach ($themeDirs as $themeDir) {
            if (is_file($themeDir . '/' . $module . '/templates/' . $path)) {
                return true;
            }
        }

        return false;
    }
}
