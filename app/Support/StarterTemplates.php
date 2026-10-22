<?php

namespace App\Support;

/**
 * The starter templates published to every shop (seeded into the templates table; super admin
 * versions and edits them from there). Each one is a complete, valid configurator definition.
 */
final class StarterTemplates
{
    private static function opt(string $value, string $label, ?float $amount = null, array $extra = []): array
    {
        return array_filter(['value' => $value, 'label' => $label, 'price_rule' => $amount ? ['type' => 'fixed', 'amount' => $amount] : null], fn ($v) => $v !== null) + $extra;
    }

    private static function def(array $steps, array $rules = [], array $more = []): array
    {
        return $more + ['schema' => 1, 'settings' => ['markup_percent' => 0, 'rounding' => ['mode' => 'none'], 'variables' => (object) []], 'steps' => $steps, 'rules' => $rules, 'price_rules' => [], 'tables' => (object) [], 'layers' => [], 'translations' => (object) []];
    }

    /** @return list<array{key:string,name:string,category:string,description:string,min_plan:?string,featured:bool,definition:array}> */
    public static function all(): array
    {
        return [
            [
                'key' => 'ring-builder', 'name' => 'Ring builder', 'category' => 'Jewelry', 'featured' => true, 'min_plan' => 'growth',
                'description' => 'Stone shape drives compatible settings, metal and karat rules, and a carat × clarity price grid.',
                'definition' => self::def([
                    ['key' => 's_shape', 'title' => 'Shape', 'help' => 'Choose the cut of your centre stone.', 'fields' => [['key' => 'shape', 'type' => 'image_tiles', 'label' => 'Shape', 'required' => true, 'options' => [self::opt('round', 'Round', null, ['default' => true]), self::opt('oval', 'Oval', 40), self::opt('princess', 'Princess', 60), self::opt('emerald', 'Emerald', 80)]]]],
                    ['key' => 's_metal', 'title' => 'Metal', 'help' => 'Pick the band metal.', 'fields' => [['key' => 'metal', 'type' => 'swatch', 'label' => 'Metal', 'required' => true, 'options' => [self::opt('yellow14', '14K Yellow Gold', null, ['default' => true, 'swatch' => '#E3C16F']), self::opt('white14', '14K White Gold', 40, ['swatch' => '#E6E7E9']), self::opt('rose18', '18K Rose Gold', 320, ['swatch' => '#D9A08B']), self::opt('plat', 'Platinum', 610, ['swatch' => '#C9CED4'])]]]],
                    ['key' => 's_stone', 'title' => 'Stone', 'help' => 'Carat and clarity are priced from the grid.', 'fields' => [
                        ['key' => 'carat', 'type' => 'buttons', 'label' => 'Carat', 'required' => true, 'price_rule' => ['type' => 'table', 'table' => 'diamond'], 'options' => [self::opt('0.5', '0.5 ct', null, ['default' => true]), self::opt('1.0', '1.0 ct'), self::opt('1.5', '1.5 ct'), self::opt('2.0', '2.0 ct')]],
                        ['key' => 'clarity', 'type' => 'dropdown', 'label' => 'Clarity', 'default' => 'VS1', 'options' => [self::opt('VS1', 'VS1'), self::opt('VVS1', 'VVS1')]],
                    ]],
                    ['key' => 's_size', 'title' => 'Ring size', 'help' => 'Not sure? We offer one free resize.', 'fields' => [['key' => 'ring_size', 'type' => 'dropdown', 'label' => 'Ring size (US)', 'required' => true, 'options' => array_map(fn ($n) => self::opt((string) $n, 'US '.$n, $n >= 9 ? 30 : null), [4, 5, 6, 7, 8, 9, 10])]]],
                    ['key' => 's_engrave', 'title' => 'Engraving', 'help' => 'Add a message inside the band.', 'fields' => [['key' => 'engraving', 'type' => 'text', 'label' => 'Engraving text', 'placeholder' => 'e.g. Forever', 'validation' => ['max_length' => 12, 'pattern' => '^[A-Za-z0-9 .&+]*$'], 'price_rule' => ['type' => 'per_char', 'amount' => 1.5]]]],
                ], [
                    ['id' => 'princess-carat', 'name' => 'Princess limits carat', 'conditions' => ['all' => [['field' => 'shape', 'op' => 'eq', 'value' => 'princess']]], 'actions' => [['type' => 'hide_option', 'field' => 'carat', 'option' => '2.0']]],
                ], ['tables' => ['diamond' => ['label' => 'Diamond price', 'row_field' => 'carat', 'col_field' => 'clarity', 'values' => ['0.5' => ['VS1' => 0, 'VVS1' => 320], '1.0' => ['VS1' => 780, 'VVS1' => 1290], '1.5' => ['VS1' => 1620, 'VVS1' => 2480], '2.0' => ['VS1' => 2940, 'VVS1' => 4150]]]],
                    'layers' => [['id' => 'text', 'z' => 3, 'type' => 'text', 'field' => 'engraving', 'style' => ['font' => 'script', 'color' => '#6B5B45']]]]),
            ],
            [
                'key' => 'name-necklace', 'name' => 'Name necklace', 'category' => 'Jewelry', 'featured' => true, 'min_plan' => 'starter',
                'description' => 'Live name preview with a character limit and a price per letter.',
                'definition' => self::def([
                    ['key' => 's_font', 'title' => 'Font', 'fields' => [['key' => 'font', 'type' => 'buttons', 'label' => 'Font', 'required' => true, 'options' => [self::opt('script', 'Script', null, ['default' => true]), self::opt('serif', 'Serif'), self::opt('block', 'Block')]]]],
                    ['key' => 's_name', 'title' => 'Name', 'fields' => [['key' => 'name_text', 'type' => 'text', 'label' => 'Name', 'required' => true, 'validation' => ['max_length' => 10, 'min_length' => 2, 'pattern' => '^[A-Za-z ]*$'], 'price_rule' => ['type' => 'per_char', 'amount' => 4]]]],
                    ['key' => 's_chain', 'title' => 'Chain', 'fields' => [['key' => 'chain', 'type' => 'swatch', 'label' => 'Chain', 'required' => true, 'options' => [self::opt('silver', 'Sterling silver', null, ['default' => true, 'swatch' => '#D7DBE0']), self::opt('gold', 'Gold plated', 15, ['swatch' => '#E3C16F'])]], ['key' => 'length', 'type' => 'dropdown', 'label' => 'Length', 'options' => [self::opt('16', '16 in', null, ['default' => true]), self::opt('18', '18 in', 5), self::opt('20', '20 in', 8)]]]],
                ], [], ['layers' => [['id' => 'name', 'z' => 2, 'type' => 'text', 'field' => 'name_text', 'style' => ['font' => 'script']]]]),
            ],
            [
                'key' => 'pendant-formula', 'name' => 'Pendant (weight formula)', 'category' => 'Jewelry', 'featured' => false, 'min_plan' => 'pro',
                'description' => 'Price = weight × metal rate + making charge, recalculated live.',
                'definition' => self::def([
                    ['key' => 's_metal', 'title' => 'Metal', 'fields' => [['key' => 'metal', 'type' => 'swatch', 'label' => 'Metal', 'required' => true, 'options' => [self::opt('gold18', '18K Gold', null, ['default' => true, 'swatch' => '#E3C16F', 'vars' => ['metal_rate' => 62.5]]), self::opt('silver', 'Sterling silver', null, ['swatch' => '#D7DBE0', 'vars' => ['metal_rate' => 1.1]])]]]],
                    ['key' => 's_weight', 'title' => 'Weight', 'fields' => [['key' => 'weight_g', 'type' => 'number', 'label' => 'Weight (g)', 'required' => true, 'default' => 4, 'validation' => ['min' => 1, 'max' => 50]]]],
                ], [], ['settings' => ['markup_percent' => 0, 'rounding' => ['mode' => 'ending', 'ending' => 0.99], 'variables' => ['making' => 35], 'formula' => 'weight_g * metal_rate + making', 'formula_label' => 'Metal and making']]),
            ],
            [
                'key' => 'custom-table', 'name' => 'Custom table', 'category' => 'Furniture', 'featured' => true, 'min_plan' => 'growth',
                'description' => 'Width × length priced per square foot, with wood, finish and legs.',
                'definition' => self::def([
                    ['key' => 's_size', 'title' => 'Size', 'fields' => [['key' => 'size', 'type' => 'dimensions', 'label' => 'Table top (in)', 'unit' => 'in', 'required' => true, 'default' => ['w' => 60, 'h' => 30], 'validation' => ['min_w' => 30, 'max_w' => 120, 'min_h' => 20, 'max_h' => 48], 'price_rule' => ['type' => 'per_unit', 'basis' => 'area', 'amount' => 12, 'divisor' => 144]]]],
                    ['key' => 's_wood', 'title' => 'Wood', 'fields' => [['key' => 'wood', 'type' => 'image_tiles', 'label' => 'Wood', 'required' => true, 'options' => [self::opt('oak', 'Oak', null, ['default' => true]), self::opt('walnut', 'Walnut', 150), self::opt('ash', 'Ash', 60)]]]],
                    ['key' => 's_finish', 'title' => 'Finish', 'fields' => [['key' => 'finish', 'type' => 'radio', 'label' => 'Finish', 'options' => [self::opt('natural', 'Natural oil', null, ['default' => true]), self::opt('matte', 'Matte lacquer', 40)]]]],
                    ['key' => 's_legs', 'title' => 'Legs', 'fields' => [['key' => 'legs', 'type' => 'buttons', 'label' => 'Legs', 'options' => [self::opt('tapered', 'Tapered', null, ['default' => true]), self::opt('hairpin', 'Steel hairpin', 70), self::opt('trestle', 'Trestle', 120)]]]],
                ]),
            ],
            [
                'key' => 'modular-sofa', 'name' => 'Modular sofa', 'category' => 'Furniture', 'featured' => false, 'min_plan' => 'growth',
                'description' => 'Add seat modules one by one, then choose fabric and legs.',
                'definition' => self::def([
                    ['key' => 'modules', 'title' => 'Modules', 'repeatable' => true, 'max_repeats' => 6, 'required' => true, 'fields' => [['key' => 'module', 'type' => 'dropdown', 'label' => 'Module', 'required' => true, 'options' => [self::opt('seat', 'Seat', 420), self::opt('corner', 'Corner', 560), self::opt('chaise', 'Chaise', 640)]]]],
                    ['key' => 's_fabric', 'title' => 'Fabric', 'fields' => [['key' => 'fabric', 'type' => 'swatch', 'label' => 'Fabric', 'required' => true, 'options' => [self::opt('boucle', 'Bouclé oat', null, ['default' => true, 'swatch' => '#E7DFD2']), self::opt('linen', 'Linen sage', 120, ['swatch' => '#B9C6B0']), self::opt('velvet', 'Velvet ink', 260, ['swatch' => '#2C3240'])]]]],
                    ['key' => 's_legs', 'title' => 'Legs', 'fields' => [['key' => 'legs', 'type' => 'buttons', 'label' => 'Legs', 'options' => [self::opt('black', 'Black steel', null, ['default' => true]), self::opt('walnut', 'Walnut', 90), self::opt('brass', 'Brass', 150)]]]],
                ]),
            ],
            [
                'key' => 'variant-apparel', 'name' => 'Apparel with variants', 'category' => 'Print & apparel', 'featured' => true, 'min_plan' => 'starter',
                'description' => 'Uses your Shopify Color, Size and Material variants as steps, then adds embroidery.',
                'definition' => self::def([
                    ['key' => 's_color', 'title' => 'Color', 'source' => 'shopify_option', 'fields' => [['key' => 'color', 'type' => 'swatch', 'label' => 'Color', 'required' => true, 'variant_option' => 'Color', 'options' => [self::opt('Black', 'Black'), self::opt('Navy', 'Navy')]]]],
                    ['key' => 's_size', 'title' => 'Size', 'source' => 'shopify_option', 'fields' => [['key' => 'size', 'type' => 'buttons', 'label' => 'Size', 'required' => true, 'variant_option' => 'Size', 'layout' => 'size_grid', 'options' => [self::opt('S', 'S'), self::opt('M', 'M'), self::opt('L', 'L')]]]],
                    ['key' => 's_emb', 'title' => 'Embroidery', 'fields' => [['key' => 'embroidery', 'type' => 'text', 'label' => 'Embroidery text', 'validation' => ['max_length' => 14], 'price_rule' => ['type' => 'fixed', 'amount' => 8]], ['key' => 'placement', 'type' => 'image_tiles', 'label' => 'Placement', 'options' => [self::opt('chest', 'Chest', null, ['default' => true]), self::opt('sleeve', 'Sleeve'), self::opt('back', 'Back', 4)]]]],
                ], [], ['variant_mode' => 'variants']),
            ],
            [
                'key' => 'framed-print', 'name' => 'Framed print', 'category' => 'Print & apparel', 'featured' => false, 'min_plan' => 'starter',
                'description' => 'Size, paper and frame with an artwork upload.',
                'definition' => self::def([
                    ['key' => 's_size', 'title' => 'Size', 'fields' => [['key' => 'print_size', 'type' => 'buttons', 'label' => 'Size (cm)', 'required' => true, 'options' => [self::opt('30x40', '30 × 40', null, ['default' => true]), self::opt('50x70', '50 × 70', 40), self::opt('70x100', '70 × 100', 90)]]]],
                    ['key' => 's_paper', 'title' => 'Paper', 'fields' => [['key' => 'paper', 'type' => 'radio', 'label' => 'Paper', 'options' => [self::opt('matte', 'Matte 200g', null, ['default' => true]), self::opt('fineart', 'Fine art 310g', 25), self::opt('canvas', 'Canvas', 45)]]]],
                    ['key' => 's_frame', 'title' => 'Frame', 'fields' => [['key' => 'frame', 'type' => 'swatch', 'label' => 'Frame', 'options' => [self::opt('none', 'No frame', null, ['default' => true]), self::opt('oak', 'Oak', 50, ['swatch' => '#C8A36E']), self::opt('black', 'Black', 40, ['swatch' => '#1C1C1C'])]]]],
                    ['key' => 's_art', 'title' => 'Artwork', 'fields' => [['key' => 'artwork', 'type' => 'file', 'label' => 'Upload artwork', 'required' => true, 'validation' => ['file_types' => ['jpg', 'png', 'pdf'], 'max_mb' => 20]]]],
                ]),
            ],
            [
                'key' => 'gift-box', 'name' => 'Gift box', 'category' => 'Gifts', 'featured' => true, 'min_plan' => 'growth',
                'description' => 'Pick items into a box (repeatable step), then add a message card.',
                'definition' => self::def([
                    ['key' => 's_box', 'title' => 'Box', 'fields' => [['key' => 'box', 'type' => 'image_tiles', 'label' => 'Box', 'required' => true, 'options' => [self::opt('small', 'Small (3 items)', null, ['default' => true]), self::opt('large', 'Large (6 items)', 12)]]]],
                    ['key' => 'items', 'title' => 'Add items', 'repeatable' => true, 'max_repeats' => 6, 'required' => true, 'fields' => [['key' => 'item', 'type' => 'dropdown', 'label' => 'Item', 'required' => true, 'options' => [self::opt('candle', 'Candle', 18), self::opt('soap', 'Soap bar', 9), self::opt('tea', 'Loose-leaf tea', 14)]]]],
                    ['key' => 's_card', 'title' => 'Card', 'fields' => [['key' => 'message', 'type' => 'text', 'label' => 'Card message', 'validation' => ['max_length' => 160]]]],
                ]),
            ],
            [
                'key' => 'b2b-signage', 'name' => 'B2B signage', 'category' => 'B2B', 'featured' => false, 'min_plan' => 'growth',
                'description' => 'Material, size and mounting with quantity tiers.',
                'definition' => self::def([
                    ['key' => 's_material', 'title' => 'Material', 'fields' => [['key' => 'material', 'type' => 'radio', 'label' => 'Material', 'required' => true, 'options' => [self::opt('acrylic', 'Acrylic', null, ['default' => true]), self::opt('aluminium', 'Brushed aluminium', 18), self::opt('wood', 'Wood', 12)]]]],
                    ['key' => 's_size', 'title' => 'Size', 'fields' => [['key' => 'sign_size', 'type' => 'buttons', 'label' => 'Size', 'options' => [self::opt('a4', 'A4', null, ['default' => true]), self::opt('a3', 'A3', 15), self::opt('a2', 'A2', 35)]]]],
                    ['key' => 's_mount', 'title' => 'Mounting', 'fields' => [['key' => 'mounting', 'type' => 'checkbox', 'label' => 'Mounting', 'options' => [self::opt('standoffs', 'Standoffs', 9), self::opt('tape', 'Mounting tape', 3)]]]],
                ], [], ['price_rules' => [['id' => 'tiers', 'label' => 'Quantity price', 'type' => 'tiered', 'basis' => 'quantity', 'tiers' => [['min' => 1, 'max' => 9, 'amount' => 0], ['min' => 10, 'max' => 49, 'amount' => -4], ['min' => 50, 'amount' => -8]]]]]),
            ],
            [
                'key' => 'blank', 'name' => 'Blank', 'category' => 'Blank', 'featured' => false, 'min_plan' => null,
                'description' => 'One empty step — add any input type.',
                'definition' => self::def([['key' => 'step_1', 'title' => 'Step 1', 'fields' => [['key' => 'choice', 'type' => 'buttons', 'label' => 'Choose an option', 'required' => true, 'options' => [self::opt('option_a', 'Option A', null, ['default' => true]), self::opt('option_b', 'Option B', 10)]]]]]),
            ],
        ];
    }
}
