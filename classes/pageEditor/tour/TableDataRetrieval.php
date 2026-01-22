<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\UI\Component\Table\OrderingBinding;
use ILIAS\UI\Component\Table\OrderingRowBuilder;
use ilObject;
use Generator;

class TableDataRetrieval implements OrderingBinding
{
    public function __construct(
        protected int $map_id,
    ) {
    }

    /**
     * @param OrderingRowBuilder $row_builder
     * @param array              $visible_column_ids
     * @return Generator
     */
    public function getRows(OrderingRowBuilder $row_builder, array $visible_column_ids): Generator
    {
        global $DIC;
        $db = $DIC->database();

        $sql = $db->queryF(
            <<<SQL
            SELECT id, map_id, learnplace_ref_id, position
            FROM kpg_lmap_tour
            WHERE map_id = %s
            ORDER BY position
            SQL,
            ['integer',],
            [$this->map_id]
        );

        while ($row = $db->fetchAssoc($sql)) {
            $row_id = (string) $row['id'];

            $record = [
                'id' => $row['id'],
                'ref_id' => $row['learnplace_ref_id'],
                'title' => ilObject::_lookupTitle(ilObject::_lookupObjectId($row['learnplace_ref_id'])),
            ];

            yield $row_builder->buildOrderingRow($row_id, $record);
        }
    }
}
