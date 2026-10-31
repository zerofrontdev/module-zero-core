<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Setup;

use PHPUnit\Framework\TestCase;

/**
 * Zero_Core's data patches that only fit our stores (CORE_STORE_PATCHES in
 * dev/zero-packages/export.py) stay out of the public package: every name
 * there is a real patch (a typo would ship the patch), the move to
 * zerofront.dev is one of them (A1-7: it rewrote someone else's store's
 * content), and no patch that ships depends on one that does not (setup
 * would stop on the missing class).
 */
class StorePatchesExportTest extends TestCase
{
    private const EXPORT = __DIR__ . '/../../../../../../../dev/zero-packages/export.py';
    private const PATCHES = __DIR__ . '/../../../Setup/Patch/Data';

    public function testEveryStorePatchExists(): void
    {
        $skipped = $this->storePatches();

        $this->assertNotEmpty($skipped);
        foreach ($skipped as $name) {
            $this->assertFileExists(self::PATCHES . '/' . $name . '.php', $name . ' in CORE_STORE_PATCHES is no patch');
        }
    }

    public function testTheMoveToZerofrontDevStaysOut(): void
    {
        $this->assertContains('MoveToZerofrontDev', $this->storePatches());
        $this->assertNotContains('MoveToZerofrontDev', $this->shipped());
        $this->assertContains('RemoveInlineScriptFrom404', $this->shipped(), 'a public patch still ships');
    }

    public function testNoShippedPatchDependsOnAStorePatch(): void
    {
        $skipped = $this->storePatches();
        foreach ($this->shipped() as $name) {
            $code = (string) file_get_contents(self::PATCHES . '/' . $name . '.php');
            $this->assertSame(1, preg_match('/function getDependencies\(\)[^{]*\{(.*?)\n    \}/s', $code, $m), $name);
            foreach ($skipped as $store) {
                $this->assertDoesNotMatchRegularExpression('/\b' . $store . '\b/', $m[1], $name . ' depends on ' . $store . ', which is not exported');
            }
        }
    }

    /**
     * @return string[]
     */
    private function storePatches(): array
    {
        if (!is_file(self::EXPORT)) {
            $this->markTestSkipped('only in the Zero monorepo: the package source has no dev/zero-packages/export.py');
        }
        $python = (string) file_get_contents(self::EXPORT);
        $this->assertSame(1, preg_match('/^CORE_STORE_PATCHES = \[(.*?)\]/ms', $python, $m), 'CORE_STORE_PATCHES not found in export.py');
        preg_match_all("/'([A-Za-z0-9]+)'/", $m[1], $names);

        return $names[1];
    }

    /**
     * @return string[] the Core data patches the package keeps
     */
    private function shipped(): array
    {
        $all = array_map(static fn ($file) => basename($file, '.php'), glob(self::PATCHES . '/*.php') ?: []);

        return array_values(array_diff($all, $this->storePatches()));
    }
}
