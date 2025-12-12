<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\UI\Component\Table\OrderingBinding;
use ILIAS\UI\Component\Table\OrderingRowBuilder;

class tourTableDataRetrieval implements OrderingBinding
{
    public function getRows(OrderingRowBuilder $row_builder, array $visible_column_ids): \Generator
    {
        $example_data = [
            [
                'id' => '1',
                'title' => 'Video 1',
            ],
            [
                'id' => '2',
                'title' => 'Video 2',
            ]
        ];

        foreach ($example_data as $record) {
            $row_id = (string) $record['id'];
            yield $row_builder->buildOrderingRow($row_id, $record);
        }
    }
}