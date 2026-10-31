<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Request;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: a visitor can send any request field as an array (`x[]=1`, or
 * `{"x":[1]}` in a JSON body). `(string)` on an array raises "Array to string
 * conversion", Magento's error handler turns that into an exception, and the page
 * or webhook answers 500, in production too. Request values are read as
 * `is_scalar($v) ? (string) $v : default` instead, so an array counts as missing,
 * or through a small helper that does the same (text(), param(), field(), email(),
 * comment()); a helper call is never a cast, so the guard leaves it alone.
 *
 * This test scans the storefront code (app/code/Zero without tests, admin
 * controllers, admin view models and admin templates; app/design/frontend/Zero)
 * and fails with the file:line list. It looks for:
 * - a cast or string function on a request getter: `(string) $request->getParam('x')`;
 * - a cast on a request array: `(string) ($post['x'] ?? '')`; `$data`, `$body` and the
 *   other body names count in controllers, and the models a request array is handed
 *   to (REQUEST_FED, and classes that implement REQUEST_FED_INTERFACES) name theirs;
 * - a getter read into a variable that is cast or trimmed within WINDOW lines
 *   without a type check on that variable.
 * A line is guarded only when its is_scalar()/is_string() names the same expression
 * that is cast. A line that is safe for another reason goes in ALLOWED with its reason.
 */
class UnguardedRequestCastTest extends TestCase
{
    /** The request getters a visitor controls. */
    private const GETTERS = 'getParam|getParams|getPostValue|getPost|getQuery|getQueryValue|getCookie|getFiles';

    /** String functions that fail on an array as much as a cast does. */
    private const STRING_FUNCTIONS = 'trim|ltrim|rtrim|strtolower|strtoupper|mb_strtolower|mb_strtoupper|strlen|mb_strlen'
        . '|preg_match|preg_replace|hash_equals|hash|hash_hmac|sha1|md5|substr|mb_substr|explode|urlencode|rawurlencode'
        . '|base64_decode|json_decode|str_[a-z_]+';

    /** The names request arrays go by everywhere. */
    private const REQUEST_ARRAYS = 'post|params|posted';

    /** The names a decoded body or form goes by in a controller. */
    private const BODY_ARRAYS = 'data|body|payload|event|message|item|charge|payment|session|link|unsigned|request|metadata';

    /**
     * Models that get a request array from a controller, and the names it goes by there
     * (taken from the request-cast sweep), relative to app/code/Zero. API responses and
     * database rows in the same files use other names, so they stay out.
     */
    private const REQUEST_FED = [
        '#^WayForPay/Model/Signature\.php$#' => 'data',
        '#^Checkout/Model/Checkout/ShippingAddressUpdater\.php$#' => 'data',
        '#^[^/]+/Model/PlaceOrder/[^/]+\.php$#' => 'params|request|data',
        '#^Mcp/Model/Oauth/[^/]+\.php$#' => 'metadata|post',
        '#^Mcp/Model/Server\.php$#' => 'message',
        '#^Company/Model/Registration\.php$#' => 'post',
        '#^PrivatParts/Model/Api\.php$#' => 'data',
        '#^Adyen/Model/(?:Notification|OrderUpdater)\.php$#' => 'item',
        '#^Grow/Model/OrderUpdater\.php$#' => 'params',
        '#^SocialLogin/Model/Provider/[^/]+\.php$#' => 'params',
        '#^QuoteRequest/Model/Lines\.php$#' => 'post',
        '#^RequisitionList/Model/(?:BuyRequest|Products)\.php$#' => 'request',
        '#^Staging/Model/Preview/State\.php$#' => 'value',
        '#^Core/ViewModel/CookieMessages\.php$#' => 'messages|message',
    ];

    /** Interfaces whose methods get Placeorder's raw request (a class anywhere that implements one). */
    private const REQUEST_FED_INTERFACES = 'PlaceOrderValidatorInterface|AfterPlaceOrderInterface';

    /** How many lines after `$x = $request->getParam(…)` a bare use of $x is looked for. */
    private const WINDOW = 5;

    /** Paths left out: only signed-in admins reach them. */
    private const EXCLUDED = '#/Test/|/Controller/Adminhtml/|/view/adminhtml/|/ViewModel/Admin[^/]*\.php$|/ViewModel/Adminhtml/'
        . '|/Block/Adminhtml/|/Plugin/Adminhtml/#';

    /**
     * Lines that match a pattern but are safe: "path|text the line contains" => why.
     * Each text must appear exactly once in its file.
     */
    private const ALLOWED = [
        'app/code/Zero/Afterpay/Model/OrderUpdater.php|(string) ($params[\'outcome\'] ?? \'\')'
            => 'ReturnAction passes its own [\'outcome\' => \'cancel\'] or [], never the request',
        'app/code/Zero/Tabby/Model/OrderUpdater.php|(string) ($params[\'outcome\'] ?? \'\')'
            => 'ReturnAction passes an is_scalar-checked outcome',
        'app/code/Zero/Support/Model/Mime/Parser.php|(string) ($params[\'boundary\'] ?? \'\')'
            => 'MIME header parameters parsed by the Parser itself: always strings',
        'app/code/Zero/Support/Model/Mime/Parser.php|(string) ($params[\'charset\'] ?? \'\')'
            => 'MIME header parameters parsed by the Parser itself: always strings',
    ];

    public function testNoStorefrontCodeCastsARequestValueWithoutAGuard(): void
    {
        $root = self::root();
        $found = [];
        foreach ($this->files($root) as $file) {
            $relative = substr($file, strlen($root) + 1);
            $code = (string) file_get_contents($file);
            if (!str_contains($code, '(string)') && !preg_match('/->(?:' . self::GETTERS . ')\s*\(/', $code)) {
                continue;
            }
            foreach (self::findUnguarded($relative, $code) as $number => $line) {
                if (!$this->isAllowed($relative, $line)) {
                    $found[] = $relative . ':' . $number . '  ' . trim($line);
                }
            }
        }

        self::assertSame(
            [],
            $found,
            "Request values cast to text without an is_scalar/is_string guard (an array gives a 500):\n"
            . implode("\n", $found)
            . "\nRead them as `is_scalar(\$v) ? (string) \$v : ''`, or add the line to ALLOWED with the reason it is safe."
        );
    }

    /**
     * An allowed text that shows up twice in its file would let a second, unreviewed line through.
     */
    public function testEachAllowedTextAppearsExactlyOnceInItsFile(): void
    {
        $root = self::root();
        foreach (array_keys(self::ALLOWED) as $entry) {
            [$path, $text] = explode('|', $entry, 2);
            self::assertFileExists($root . '/' . $path, $entry);
            $lines = explode("\n", (string) file_get_contents($root . '/' . $path));
            $count = count(array_filter($lines, static fn (string $line) => str_contains($line, $text)));
            self::assertSame(1, $count, "ALLOWED text must appear exactly once: $entry");
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function lines(): array
    {
        $controller = 'app/code/Zero/Example/Controller/Payment/Webhook.php';
        $model = 'app/code/Zero/Example/Model/Thing.php';
        $signature = 'app/code/Zero/WayForPay/Model/Signature.php';
        $updater = 'app/code/Zero/Checkout/Model/Checkout/ShippingAddressUpdater.php';
        $validator = 'app/code/Zero/Example/Model/PlaceOrder/Validator.php';

        return [
            'getParam cast' => [$model, "\$token = (string) \$this->request->getParam('token');", true],
            'getParam cast in parentheses' => [$model, "\$v = (string) (\$request->getParam('q') ?? '');", true],
            'getPostValue cast' => [$model, "\$v = (string) \$request->getPostValue('email');", true],
            'cookie cast' => [$model, "\$v = (string) \$this->cookieManager->getCookie(self::COOKIE, '');", true],
            'trim of a cast getParam' => [$model, "\$email = trim((string) \$this->request->getParam('email'));", true],
            'preg_match on getParam' => [$model, "if (preg_match('/^a/', \$this->request->getParam('code'))) {", true],
            'posted array cast' => [$model, "\$name = trim((string) (\$post['new_list_name'] ?? ''));", true],
            'params array cast' => [$model, "\$method = (string) (\$params['shipping_method'] ?? '');", true],
            'webhook body cast' => [$controller, "\$reference = (string) (\$data['orderReference'] ?? '');", true],
            'webhook nested body cast' => [$controller, "\$reference = (string) (\$charge['metadata']['order'] ?? '');", true],
            'guarded read' => [$model, "\$v = is_scalar(\$v) ? (string) \$v : '';", false],
            'guarded field' => [$controller, "\$r = is_scalar(\$data['id'] ?? null) ? (string) \$data['id'] : '';", false],
            'int cast' => [$model, "\$id = (int) \$this->request->getParam('id');", false],
            'database row in a model' => [$model, "\$name = (string) (\$data['name'] ?? '');", false],
            'comment' => [$model, "// (string) \$this->request->getParam('x') was unsafe", false],
            // Request-fed models: the body names count there too.
            'request-fed signature cast' => [$signature, "\$given = (string) (\$data['merchantSignature'] ?? '');", true],
            'request-fed signature guarded' => [
                $signature,
                "\$given = is_scalar(\$data['merchantSignature'] ?? null) ? (string) \$data['merchantSignature'] : '';",
                false,
            ],
            'request-fed updater email cast' => [
                $updater,
                "\$quote->setCustomerEmail((string) (\$data['email'] ?? \$quote->getCustomerEmail()));",
                true,
            ],
            'request-fed updater helper' => [$updater, "\$quote->setCustomerEmail(self::email(\$quote, \$data));", false],
            'place-order validator cast' => [$validator, "\$comment = (string) (\$request['comment'] ?? '');", true],
            'place-order validator by interface' => [
                $model,
                "class Thing implements PlaceOrderValidatorInterface\n{\n    \$id = (string) (\$data['id'] ?? '');",
                true,
            ],
            'API response in a request-fed model' => [
                'app/code/Zero/Adyen/Model/OrderUpdater.php',
                "\$status = (string) (\$link['status'] ?? '');",
                false,
            ],
            // The guard must name the expression that is cast.
            'guard on another variable' => [
                $controller,
                "\$v = is_scalar(\$a) ? (string) \$a : (string) \$request->getParam('y');",
                true,
            ],
            'guard on another field' => [
                $controller,
                "\$v = is_scalar(\$data['a'] ?? null) ? (string) \$data['b'] : '';",
                true,
            ],
            'guard on the same getter' => [
                $model,
                "\$v = is_string(\$request->getParam('y')) ? (string) \$request->getParam('y') : '';",
                false,
            ],
            'helper reads a field' => [$controller, "\$v = trim(self::text(\$data['x'] ?? null));", false],
            'helper reads a param' => [$controller, "\$v = self::param(\$params, 'shipping_method');", false],
            // A getter read into a variable, used a few lines later.
            'two-line cast' => [$model, "\$x = \$this->request->getParam('x');\n\$y = 'a';\n\$z = (string) \$x;", true],
            'two-line trim' => [$model, "\$x = \$request->getParam('x', '');\nif (trim(\$x) === '') {", true],
            'two-line strtolower' => [$model, "\$x = \$request->getQuery('x');\n\$x = strtolower(\$x);", true],
            'two-line guarded ternary' => [
                $model,
                "\$x = \$request->getParam('x');\n\$x = is_scalar(\$x) ? (string) \$x : '';\n\$y = trim(\$x);",
                false,
            ],
            'two-line early exit' => [
                $model,
                "\$x = \$request->getParam('x');\nif (!is_string(\$x)) {\n    return;\n}\n\$y = trim(\$x);",
                false,
            ],
            'two-line int use' => [$model, "\$x = \$request->getParam('x');\n\$id = (int) \$x;", false],
            'two-line other variable' => [$model, "\$x = \$request->getParam('x');\n\$y = trim(\$xy);", false],
        ];
    }

    #[DataProvider('lines')]
    public function testThePatternsCatchTheOldFormsAndPassGuardedOnes(string $path, string $code, bool $unsafe): void
    {
        self::assertSame($unsafe, self::findUnguarded($path, $code) !== []);
    }

    /**
     * The five bugs that were proven with a 500: each old line, put back into a copy of
     * today's file (in memory), must be flagged, and today's file must not be.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function originalBugs(): array
    {
        return [
            'WayForPay webhook' => [
                'app/code/Zero/WayForPay/Controller/Payment/Webhook.php',
                "        \$reference = (string) (\$data['orderReference'] ?? '');",
            ],
            'WayForPay signature' => [
                'app/code/Zero/WayForPay/Model/Signature.php',
                "        \$given = (string) (\$data['merchantSignature'] ?? '');",
            ],
            'Grow webhook' => [
                'app/code/Zero/Grow/Controller/Payment/Webhook.php',
                "        \$reference = (string) (\$data['customFields']['cField1'] ?? \$data['cField1'] ?? '');",
            ],
            'Placeorder' => [
                'app/code/Zero/Checkout/Controller/Checkout/Placeorder.php',
                "        \$shippingMethod = (string) (\$params['shipping_method'] ?? '');",
            ],
            'ShippingAddressUpdater' => [
                'app/code/Zero/Checkout/Model/Checkout/ShippingAddressUpdater.php',
                "            \$quote->setCustomerEmail((string) (\$data['email'] ?? \$quote->getCustomerEmail()));",
            ],
            'RequisitionList item add' => [
                'app/code/Zero/RequisitionList/Controller/Item/Add.php',
                "        \$newName = trim((string) (\$post['new_list_name'] ?? ''));",
            ],
        ];
    }

    #[DataProvider('originalBugs')]
    public function testTheGuardCatchesEachOriginalBugInItsOwnFile(string $path, string $oldLine): void
    {
        $code = (string) file_get_contents(self::root() . '/' . $path);
        self::assertSame([], self::findUnguarded($path, $code), "today's $path must pass");

        $end = (int) strrpos($code, '}');
        $broken = substr($code, 0, $end) . $oldLine . "\n" . substr($code, $end);
        $number = substr_count(substr($code, 0, $end), "\n") + 1;

        self::assertSame([$number => $oldLine], self::findUnguarded($path, $broken));
    }

    /**
     * The unguarded lines of one file's code.
     *
     * @return array<int, string> line number => line
     */
    private static function findUnguarded(string $path, string $code): array
    {
        $relative = (string) preg_replace('#^app/code/Zero/#', '', $path);
        $arrays = self::REQUEST_ARRAYS;
        if (str_contains($path, '/Controller/')) {
            $arrays .= '|' . self::BODY_ARRAYS;
        }
        if (preg_match('/implements[^{]*\b(?:' . self::REQUEST_FED_INTERFACES . ')\b/', $code)) {
            $arrays .= '|params|request|data';
        }
        foreach (self::REQUEST_FED as $pattern => $names) {
            if (preg_match($pattern, $relative)) {
                $arrays .= '|' . $names;
            }
        }

        $found = [];
        $lines = explode("\n", $code);
        foreach ($lines as $index => $line) {
            if (self::isComment($line)) {
                continue;
            }
            if (self::castsUnguarded($line, $arrays)) {
                $found[$index + 1] = $line;
            }
            $later = self::usesUnguardedLater($lines, $index);
            if ($later !== null) {
                $found[$later + 1] = $lines[$later];
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * Whether the line casts a getter or a request array without a guard on that same expression.
     */
    private static function castsUnguarded(string $line, string $arrays): bool
    {
        $call = '(?:\((?:[^()]|\([^()]*\))*\))';
        $getter = '\$\w+(?:->\w+' . $call . '?)*->(?:' . self::GETTERS . ')\s*' . $call . '(?:\[[^\]]*\])*';
        $field = '\$(?:' . $arrays . ')(?:\[[^\]]*\])+';
        $patterns = [
            // (string) $this->request->getParam(…), (string) ($request->getParam(…) ?? '')
            '/\(string\)\s*\(?\s*(?<op>' . $getter . ')/',
            // trim((string) $request->getParam(…)), preg_match('…', $request->getParam(…))
            '/\b(?:' . self::STRING_FUNCTIONS . ')\s*\((?:[^()]*,\s*)?\s*(?:\(string\)\s*)?\(?\s*(?<op>' . $getter . ')/',
            // (string) ($post['…'] ?? ''), (string) $data['…'] in a controller or a request-fed model
            '/\(string\)\s*\(?\s*(?<op>' . $field . ')/',
        ];
        $guarded = [];
        if (preg_match_all('/\bis_(?:scalar|string)\s*\(\s*(?<op>' . $getter . '|\$\w+(?:\[[^\]]*\])*)/', $line, $m)) {
            $guarded = array_map([self::class, 'normalise'], $m['op']);
        }
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $line, $m)) {
                foreach ($m['op'] as $operand) {
                    if (!in_array(self::normalise($operand), $guarded, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * For `$x = $request->getParam(…);` on line $index: the index of the first line within
     * WINDOW that casts or trims $x before a type check or a new value, or null.
     *
     * @param string[] $lines
     */
    private static function usesUnguardedLater(array $lines, int $index): ?int
    {
        $getter = '\$\w+(?:->\w+(?:\(\))?)*->(?:' . self::GETTERS . ')\s*\(';
        if (!preg_match('/^\s*\$(\w+)\s*=\s*' . $getter . '/', $lines[$index], $m)) {
            return null;
        }
        $var = '\$' . $m[1] . '\b(?!\s*(?:\[|->))';
        $last = min(count($lines) - 1, $index + self::WINDOW);
        for ($i = $index + 1; $i <= $last; $i++) {
            $line = $lines[$i];
            if (self::isComment($line)) {
                continue;
            }
            if (preg_match('/\bfunction\b/', $line)
                || preg_match('/\bis_(?:scalar|string|array|numeric)\s*\(\s*' . $var . '/', $line)
            ) {
                return null;
            }
            if (preg_match('/\(string\)\s*\(?\s*' . $var . '/', $line)
                || preg_match('/\b(?:' . self::STRING_FUNCTIONS . ')\s*\((?:[^()]*,\s*)?\s*(?:\(string\)\s*)?\(?\s*' . $var . '/', $line)
            ) {
                return $i;
            }
            if (preg_match('/^\s*' . $var . '\s*=[^=]/', $line)) {
                return null;
            }
        }

        return null;
    }

    private static function isComment(string $line): bool
    {
        $text = ltrim($line);

        return $text === '' || str_starts_with($text, '//') || str_starts_with($text, '*')
            || str_starts_with($text, '/*') || str_starts_with($text, '#');
    }

    private static function normalise(string $expression): string
    {
        return (string) preg_replace('/\s+/', '', $expression);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 7);
    }

    private function isAllowed(string $relative, string $line): bool
    {
        foreach (array_keys(self::ALLOWED) as $entry) {
            [$path, $text] = explode('|', $entry, 2);
            if ($path === $relative && str_contains($line, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function files(string $root): array
    {
        $files = [];
        foreach (['app/code/Zero', 'app/design/frontend/Zero'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if (preg_match('/\.(?:php|phtml)$/', $path) && !preg_match(self::EXCLUDED, $path)) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }
}
