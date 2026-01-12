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
            ['integer'],
            [$map_id]
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
            ['integer',],
            [$map_id,],
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
            ['integer',],
            [$map_id,],
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

    public function cleanupDeletedLearnplacesFromTourMaps(): void
    {
        $db = $this->dic->database();
        $query = $db->query("SELECT DISTINCT learnplace_ref_id FROM kpg_lmap_tour");

        $ids_to_delete = [];
        while ($row = $db->fetchAssoc($query)) {
            $ref_id = (int) $row['learnplace_ref_id'];
            if (!\ilObject::_exists($ref_id, true, 'xsrl')) {
                $ids_to_delete[] = $ref_id;
            }
        }

        if (!empty($ids_to_delete)) {
            $in_condition = $db->in('learnplace_ref_id', $ids_to_delete, false, 'integer');
            $db->manipulate("DELETE FROM kpg_lmap_tour WHERE {$in_condition}");
        }
    }

    /**
     * This function is used by the learnplaces plugin.
     */
    public function getTourMapsOfUser(): \Generator
    {
        $db = $this->dic->database();

        // Get all crs and grp obj_ids wher user is member
        $assigned_objects = \ilParticipants::_getMembershipByType(
            $this->dic->user()->getId(),
            ['crs', 'grp'],
            false,
        );

        // Get all context_ref_ids of assigned objects
        $all_context_ref_ids = [];
        foreach ($assigned_objects as $object_obj_id) {
            $all_context_ref_ids = array_merge($all_context_ref_ids, \ilObject::_getAllReferences($object_obj_id));
        }
        $all_context_ref_ids = array_unique($all_context_ref_ids);

        // Fetch all tour maps of user
        $in_condition = $db->in('context_ref_id', $all_context_ref_ids, false, 'integer');
        $res = $db->query(
            <<<SQL
            SELECT m.id, m.mode, m.title, m.description, m.context_ref_id, t.learnplace_ref_id, t.position
            FROM kpg_lmap_map AS m
            JOIN kpg_lmap_tour AS t ON m.id = t.map_id
            WHERE {$in_condition}
            ORDER BY m.id, t.position ASC
            SQL
        );

        $current_context_id = null;
        $tour_data = [];

        while ($row = $db->fetchAssoc($res)) {
            if ($row['mode'] !== 'tour') {
                continue;
            }

            // Return contect if a new context is encountered
            if ($current_context_id !== null && $current_context_id !== $row['context_ref_id']) {
                yield $current_context_id => $tour_data;
                $tour_data = [];
            }

            $learnplace_obj_id = \ilObject::_lookupObjectId($row['learnplace_ref_id']);

            if (!\ilObject::_exists($learnplace_obj_id)) {
                continue;
            }

            $current_context_id = $row['context_ref_id'];

            // Collect tour data
            $tour_data['map_id'] = $row['id'];
            $tour_data['title'] = $row['title'];
            $tour_data['description'] = $row['description'];
            $tour_data['context_ref_id'] = $row['context_ref_id'];
            $tour_data['tour_learnplaces'][] = [
                'learnplace_ref_id' => $row['learnplace_ref_id'],
                'visited' => $this->isVidited($this->dic->user()->getId(), $learnplace_obj_id),
            ];
        }

        // Return last context
        if ($current_context_id !== null) {
            yield $current_context_id => $tour_data;
        }
    }

    public function getTourMap(int $map_id): array|null
    {
        $db = $this->dic->database();
        $res = $db->queryF(
            <<<SQL
            SELECT m.id, m.mode, m.title, m.description, m.context_ref_id, t.learnplace_ref_id, t.position
                FROM kpg_lmap_map AS m
                JOIN kpg_lmap_tour AS t ON m.id = t.map_id
            WHERE m.id = %s
            ORDER BY t.position ASC
            SQL,
            ['integer'],
            [$map_id]
        );

        $tour_data = [];
        while ($row = $db->fetchAssoc($res)) {
            if ($row['mode'] !== 'tour') {
                continue;
            }

            $learnplace_obj_id = \ilObject::_lookupObjectId($row['learnplace_ref_id']);

            if (!\ilObject::_exists($learnplace_obj_id)) {
                continue;
            }

            $tour_data['map_id'] = $row['id'];
            $tour_data['title'] = $row['title'];
            $tour_data['description'] = $row['description'];
            $tour_data['context_ref_id'] = $row['context_ref_id'];
            $tour_data['tour_learnplaces'][] = [
                'learnplace_ref_id' => $row['learnplace_ref_id'],
                'visited' => $this->isVidited($this->dic->user()->getId(), $learnplace_obj_id),
            ];
        }

        // todo check assignment: $tour_data['context_ref_id']

        return $tour_data;
    }
}
