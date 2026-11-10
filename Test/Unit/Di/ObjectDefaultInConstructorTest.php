<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Di;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: a constructor parameter may not default to an object
 * (`private readonly Codec $codec = new Codec()`).
 *
 * `setup:di:compile` writes the default value of every optional constructor
 * parameter into generated/metadata/*.php with var_export(). An object comes
 * out as `Codec::__set_state([])`, the class has no such method, and every
 * request and every bin/magento command dies with "Call to undefined method
 * …::__set_state()". The compile itself succeeds, and a store that is not
 * compiled never sees it, so only a compiled store finds out: found on the
 * production deploys of 2026-11-08 and 2026-11-10 (Zero_Mail, MessageCodec),
 * both rolled back.
 *
 * A dependency is a required parameter instead: the object manager creates it.
 * This test reads every constructor signature in app/code/Zero (tests left
 * out) and fails with the file:line list.
 */
class ObjectDefaultInConstructorTest extends TestCase
{
    public function testNoConstructorParameterDefaultsToAnObject(): void
    {
        $root = dirname(__DIR__, 7);
        $dir = $root . '/app/code/Zero';
        if (!is_dir($dir)) {
            self::markTestSkipped('app/code/Zero is not here (the module is installed as a package).');
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (!str_ends_with($path, '.php') || str_contains($path, '/Test/')) {
                continue;
            }
            $code = (string) file_get_contents($path);
            if (!str_contains($code, '__construct')) {
                continue;
            }
            foreach (self::objectDefaults($code) as $line) {
                $found[] = substr($path, strlen($root) + 1) . ':' . $line;
            }
        }

        self::assertSame(
            [],
            $found,
            "Constructor parameters that default to an object (a compiled store dies on __set_state()):\n"
            . implode("\n", $found)
            . "\nMake the parameter required; the object manager creates the dependency."
        );
    }

    /**
     * @return array<string, array{0: string, 1: int[]}>
     */
    public static function signatures(): array
    {
        return [
            'the Zero_Mail bug' => [
                "<?php\nclass A\n{\n    public function __construct(\n        private readonly B \$b,\n"
                . "        private readonly Codec \$codec = new Codec()\n    ) {\n    }\n}\n",
                [6],
            ],
            'one line' => ["<?php\nclass A { public function __construct(B \$b = new B(1, [2])) {} }\n", [2]],
            'required dependency' => [
                "<?php\nclass A\n{\n    public function __construct(private readonly Codec \$codec)\n    {\n    }\n}\n",
                [],
            ],
            'scalar, array and null defaults' => [
                "<?php\nclass A { public function __construct(int \$a = 1, array \$b = [], ?B \$c = null) {} }\n",
                [],
            ],
            'new in the body' => [
                "<?php\nclass A { public function __construct(B \$b) { \$this->c = new C(); } }\n",
                [],
            ],
            'new in another method' => [
                "<?php\nclass A { public function __construct() {} public function b(C \$c = new C()) {} }\n",
                [],
            ],
        ];
    }

    /**
     * @param int[] $lines
     */
    #[DataProvider('signatures')]
    public function testTheScanFindsNewInAConstructorSignatureOnly(string $code, array $lines): void
    {
        self::assertSame($lines, self::objectDefaults($code));
    }

    /**
     * The lines of `new` inside the parameter list of each `__construct` in the code.
     *
     * @return int[]
     */
    private static function objectDefaults(string $code): array
    {
        $lines = [];
        $tokens = \PhpToken::tokenize($code);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!$tokens[$i]->is(T_FUNCTION)) {
                continue;
            }
            $name = $i + 1;
            while ($name < $count && $tokens[$name]->isIgnorable()) {
                $name++;
            }
            if ($name >= $count || !$tokens[$name]->is(T_STRING) || $tokens[$name]->text !== '__construct') {
                continue;
            }
            $depth = 0;
            for ($j = $name + 1; $j < $count; $j++) {
                if ($tokens[$j]->text === '(') {
                    $depth++;
                } elseif ($tokens[$j]->text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif ($depth > 0 && $tokens[$j]->is(T_NEW)) {
                    $lines[] = $tokens[$j]->line;
                }
            }
            $i = $j;
        }

        return $lines;
    }
}
