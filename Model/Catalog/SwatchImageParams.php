<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Catalog;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\RequestInterface;

/**
 * The request's layered-navigation filters that change a configurable's
 * picture on a product list (?color=50 shows the red variant's image), for
 * card cache keys. Same rule as Magento_Swatches' ProductImage plugin
 * (canReplaceImageWithSwatch): a swatch attribute used in product listing,
 * filterable, with "Update Product Preview Image" on. Only values that are
 * option ids of that attribute count, the only values the swatch plugin acts
 * on: ?color=<anything else> renders the plain card and must not open a new
 * cache entry for it. Values are normalised (strings; a list sorted and
 * unique) and keys sorted, so equal filters give one key. Worked out once
 * per request.
 */
class SwatchImageParams
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}\z/';
    private const SWATCH_TYPES = ['visual', 'text'];

    /** @var array<string, string|string[]>|null */
    private ?array $params = null;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @return array<string, string|string[]> attribute code => value(s)
     */
    public function get(): array
    {
        if ($this->params !== null) {
            return $this->params;
        }
        $params = [];
        $attributes = null;
        foreach ($this->request->getParams() as $code => $value) {
            if (!is_string($code) || !preg_match(self::KEY_PATTERN, $code)) {
                continue;
            }
            $normalised = $this->normalise($value);
            if ($normalised === null) {
                continue;
            }
            $attributes ??= $this->eavConfig->getEntityAttributes(Product::ENTITY);
            if (!isset($attributes[$code]) || !$this->changesImage($attributes[$code])) {
                continue;
            }
            $normalised = $this->knownOptions($attributes[$code], $normalised);
            if ($normalised !== null) {
                $params[$code] = $normalised;
            }
        }
        ksort($params);

        return $this->params = $params;
    }

    /**
     * @param mixed $value
     * @return string|string[]|null
     */
    private function normalise(mixed $value): string|array|null
    {
        if (is_scalar($value)) {
            return ctype_digit((string) $value) ? (string) $value : null;
        }
        if (!is_array($value) || $value === [] || !array_is_list($value)) {
            return null;
        }
        $values = [];
        foreach ($value as $item) {
            if (!is_scalar($item) || !ctype_digit((string) $item)) {
                return null;
            }
            $values[] = (string) $item;
        }
        $values = array_values(array_unique($values));
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * The value(s) kept only when each is an option id of the attribute.
     *
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute
     * @param string|string[] $value
     * @return string|string[]|null
     */
    private function knownOptions($attribute, string|array $value): string|array|null
    {
        $ids = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $ids[(string) ($option['value'] ?? '')] = true;
        }
        foreach ((array) $value as $item) {
            if (!isset($ids[$item])) {
                return null;
            }
        }

        return $value;
    }

    /**
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute
     */
    private function changesImage($attribute): bool
    {
        if (!$attribute->getUsedInProductListing() || !$attribute->getIsFilterable()) {
            return false;
        }
        $type = $attribute->getData('swatch_input_type');
        $preview = $attribute->getData('update_product_preview_image');
        if ($type === null || $preview === null) {
            $additional = json_decode((string) $attribute->getData('additional_data'), true);
            if (is_array($additional)) {
                $type ??= $additional['swatch_input_type'] ?? null;
                $preview ??= $additional['update_product_preview_image'] ?? null;
            }
        }

        return in_array($type, self::SWATCH_TYPES, true) && (bool) $preview;
    }
}
