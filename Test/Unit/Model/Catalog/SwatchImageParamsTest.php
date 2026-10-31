<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Catalog;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\SourceInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Catalog\SwatchImageParams;

/**
 * Only the swatch filters that change a configurable's picture, with option
 * ids of their attribute, reach the card cache key.
 */
class SwatchImageParamsTest extends TestCase
{
    public function testOnlyPreviewImageSwatchParamsCount(): void
    {
        $unused = $this->createMock(SourceInterface::class);
        $unused->expects($this->never())->method('getAllOptions');
        $attributes = [
            'color' => $this->attribute(['swatch_input_type' => 'visual', 'update_product_preview_image' => 1], [49, 50]),
            // Read from additional_data when the flags are not loaded on the attribute.
            'activity' => $this->attribute(['additional_data' => '{"swatch_input_type":"text","update_product_preview_image":"1"}'], [5]),
            'size' => $this->attribute(['swatch_input_type' => 'text', 'update_product_preview_image' => 0], [167], $unused),
            'material' => $this->attribute([], [3], $unused),
            'pattern' => $this->attribute(['swatch_input_type' => 'dropdown', 'update_product_preview_image' => 1], [7], $unused),
            'style_general' => $this->attribute(['swatch_input_type' => 'visual', 'update_product_preview_image' => 1, 'is_filterable' => 0], [1], $unused),
            'climate' => $this->attribute(['swatch_input_type' => 'visual', 'update_product_preview_image' => 1, 'used_in_product_listing' => 0], [2], $unused),
        ];
        $params = $this->params([
            'p' => '2', 'product_list_order' => 'price', 'color' => '50', 'activity' => '5', 'size' => '167',
            'material' => '3', 'pattern' => '7', 'style_general' => '1', 'climate' => '2', 'nothing' => '1',
        ], $attributes);

        $this->assertSame(['activity' => '5', 'color' => '50'], $params->get());
    }

    public function testValuesAreNormalised(): void
    {
        $attributes = [
            'color' => $this->attribute(['swatch_input_type' => 'visual', 'update_product_preview_image' => 1], [49, 50, 58]),
            'activity' => $this->attribute(['swatch_input_type' => 'text', 'update_product_preview_image' => 1], [5]),
        ];

        $this->assertSame(
            ['activity' => '5', 'color' => ['49', '50', '58']],
            $this->params(['color' => ['58', 50, '49', '50'], 'activity' => 5], $attributes)->get()
        );
    }

    public function testJunkKeysIgnored(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParams')->willReturn([
            'Color' => '50', '1color' => '50', 'co-lor' => '50', "color\n" => '50', 'a' . str_repeat('b', 60) => '50',
            0 => '50', 'color' => 'red',
        ]);
        // Nothing worth a look: the attributes are never loaded.
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects($this->never())->method('getEntityAttributes');

        $this->assertSame([], (new SwatchImageParams($request, $eavConfig))->get());
    }

    public function testValuesThatAreNotOptionIdsDropTheParam(): void
    {
        $color = fn () => ['color' => $this->attribute(['swatch_input_type' => 'visual', 'update_product_preview_image' => 1], [49, 50])];
        foreach ([
            'not digits' => '50abc',
            'negative' => '-50',
            'unknown option id' => '99',
            'empty' => '',
            'a list with one unknown id' => ['50', '99'],
            'a list with a word' => ['50', 'red'],
            'a map' => ['a' => '50'],
            'nested' => [['50']],
            'an empty list' => [],
        ] as $case => $value) {
            $this->assertSame([], $this->params(['color' => $value], $color())->get(), $case);
        }
    }

    public function testWorkedOutOncePerRequest(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParams')->willReturn(['color' => '50']);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects($this->once())->method('getEntityAttributes')->with(Product::ENTITY)->willReturn([
            'color' => $this->attribute(['swatch_input_type' => 'visual', 'update_product_preview_image' => 1], [50]),
        ]);
        $params = new SwatchImageParams($request, $eavConfig);

        $this->assertSame(['color' => '50'], $params->get());
        $this->assertSame(['color' => '50'], $params->get());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function params(array $request, array $attributes): SwatchImageParams
    {
        $requestStub = $this->createStub(RequestInterface::class);
        $requestStub->method('getParams')->willReturn($request);
        $eavConfig = $this->createStub(EavConfig::class);
        $eavConfig->method('getEntityAttributes')->willReturn($attributes);

        return new SwatchImageParams($requestStub, $eavConfig);
    }

    /**
     * An attribute used in listing and filterable unless $data says otherwise,
     * with the given option ids.
     *
     * @param array<string, mixed> $data
     * @param int[] $optionIds
     */
    private function attribute(array $data, array $optionIds, ?SourceInterface $source = null): DataObject
    {
        if ($source === null) {
            $source = $this->createStub(SourceInterface::class);
            $source->method('getAllOptions')->willReturn(array_map(
                static fn (int $id) => ['value' => (string) $id, 'label' => 'Option ' . $id],
                $optionIds
            ));
        }

        return new class ($data + ['used_in_product_listing' => 1, 'is_filterable' => 1], $source) extends DataObject {
            public function __construct(array $data, private readonly SourceInterface $source)
            {
                parent::__construct($data);
            }

            public function getSource(): SourceInterface
            {
                return $this->source;
            }
        };
    }
}
