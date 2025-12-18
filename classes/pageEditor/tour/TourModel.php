<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\UI\Component\Component;
use ILIAS\Data\URI;
use ILIAS\DI\Container;
use ILIAS\UI\Component\Tree\Tree;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\Component\Table\Table;

class TourModel
{
    public function __construct(
        protected Container $dic,
        protected Factory $factory,
    ) {
    }

    public function addItem(int $map_id, int $learnplace_ref_id): void
    {
        if ($this->itemExists($map_id, $learnplace_ref_id)) {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', 'Dieser Lernort ist bereits in dieser Tour enthalten.', true);
            return;
        }

        $next_id = $this->dic->database()->nextId('kpg_lmap_tour');
        $this->dic->database()->insert('kpg_lmap_tour', [
            'id' => ['integer', $next_id],
            'map_id' => ['integer', $map_id],
            'learnplace_ref_id' => ['integer', $learnplace_ref_id],
            'position' => ['integer', $next_id],
        ]);
    }

    private function itemExists(int $map_id, int $learnplace_ref_id): bool
    {
        $sql = $this->dic->database()->queryF(
            <<<SQL
            SELECT COUNT(*) AS count
            FROM kpg_lmap_tour
            WHERE map_id = %s AND learnplace_ref_id = %s
            SQL,
            [
                'integer',
                'integer',
            ],
            [
                $map_id,
                $learnplace_ref_id,
            ]
        );

        $item = $this->dic->database()->fetchObject($sql);
        return (bool) $item->count;
    }

    public function hasItems(int $map_id): bool
    {
        $sql = $this->dic->database()->queryF(
            <<<SQL
            SELECT COUNT(*) AS count
            FROM kpg_lmap_tour
            WHERE map_id = %s
            SQL,
            [
                'integer',
            ],
            [
                $map_id,
            ]
        );

        $item = $this->dic->database()->fetchObject($sql);
        return (bool) $item->count;
    }

    /**
     * @param int $map_id
     * @param int[] $ids
     * @return void
     */
    public function deleteItems(int $map_id, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return;
        }

        $condition = $this->dic->database()->in(
            'id',
            $ids,
            false,
            'integer',
        );

        $this->dic->database()->manipulateF(
            <<<SQL
            DELETE FROM kpg_lmap_tour
            WHERE {$condition} AND map_id = %s
            SQL,
            [
                'integer',
            ],
            [
                $map_id,
            ],
        );
    }

    /**
     * @param int $map_id
     * @return void
     */
    public function deleteAllItems(int $map_id): void
    {
        $this->dic->database()->manipulateF(
            <<<SQL
            DELETE FROM kpg_lmap_tour
            WHERE map_id = %s
            SQL,
            [
                'integer',
            ],
            [
                $map_id,
            ],
        );
    }

    public function updatePosition(int $id, int $new_position): void
    {
        $this->dic->database()->manipulateF(
            <<<SQL
            UPDATE kpg_lmap_tour SET position = %s WHERE id = %s
            SQL,
            [
                'integer',
                'integer',
            ],
            [
                $new_position,
                $id,
            ]
        );
    }

    public function isVidited(int $user_id, int $learnplace_id): bool
    {
        $db = $this->dic->database();
        $sql = $db->queryF(
            "SELECT COUNT(*) AS visited FROM xsrl_visit_journal WHERE fk_learnplace_id = %s AND user_id = %s",
            [
                'integer',
                'integer',
            ],
            [
                $learnplace_id,
                $user_id,
            ]
        );

        return (bool) $db->fetchObject($sql)->visited;
    }
}
