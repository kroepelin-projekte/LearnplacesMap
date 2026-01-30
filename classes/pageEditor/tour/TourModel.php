<?php

declare(strict_types=1);

namespace Kpg\Plugins\LearnplacesMap\PageEditor\Tour;

use ILIAS\DI\Container;
use KPG\Learnplaces\persistence\repository\LearnplaceRepository;
use KPG\Learnplaces\container\PluginContainer;
use KPG\Learnplaces\persistence\dto\Configuration;

class TourModel
{
    private LearnplaceRepository $learnplace_service;

    public function __construct(
        protected Container $dic,
    ) {

        /** @var LearnplaceRepository $learnplace_service  */
        $this->learnplace_service = PluginContainer::resolve(LearnplaceRepository::class);
    }

    /**
     * @param int $map_id
     * @param int $learnplace_ref_id
     * @return void
     */
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

    /**
     * @param int $map_id
     * @param int $learnplace_ref_id
     * @return bool
     */
    public function itemExists(int $map_id, int $learnplace_ref_id): bool
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

    /**
     * @param int $map_id
     * @return bool
     */
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
            WHERE $condition AND map_id = %s
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

    /**
     * @param int $id
     * @param int $new_position
     * @return void
     */
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

    /**
     * @param int $user_id
     * @param int $learnplace_id
     * @return bool
     */
    public function isVisited(int $user_id, int $learnplace_id): bool
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

    /**
     * @return void
     */
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
            $db->manipulate("DELETE FROM kpg_lmap_tour WHERE $in_condition");
        }
    }

    /**
     * @return \Generator
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
            WHERE $in_condition AND m.mode = 'tour'
            ORDER BY m.id, t.position ASC
            SQL
        );

        $current_map_id = null;
        $tour_data = [];

        while ($row = $db->fetchAssoc($res)) {
            // Return contect if a new context is encountered
            if ($current_map_id !== null && $current_map_id !== $row['id']) {
                yield $tour_data;
                $tour_data = [];
            }

            $current_map_id = $row['id'];

            // Collect tour data
            if (empty($tour_data)) {
                $tour_data = [
                    'map_id' => (int) $row['id'],
                    'title' => $row['title'],
                    'description' => nl2br($row['description']),
                    'context_ref_id' => (int) $row['context_ref_id'],
                    'tour_learnplaces' => []
                ];
            }

            $learnplace_obj_id = \ilObject::_lookupObjectId($row['learnplace_ref_id']);
            if (!\ilObject::_exists($learnplace_obj_id)) {
                continue;
            }

            $learnplace_object = $this->learnplace_service->findByObjectId($learnplace_obj_id);

            /** @var Configuration $configuration */
            $configuration = $learnplace_object->getConfiguration();
            if (!$configuration->isOnline()) {
                continue;
            }

            $tour_data['tour_learnplaces'][] = [
                'id' => $learnplace_object->getId(),
                "learnplace_ref_id" => (int) $row['learnplace_ref_id'],
                'title' => \ilObject::_lookupTitle($learnplace_object->getObjectId()),
                'latitude' => $learnplace_object->getLocation()->getLatitude(),
                'longitude' => $learnplace_object->getLocation()->getLongitude(),
                'radius' => $learnplace_object->getLocation()->getRadius(),
                'visited' => $this->isVisited($this->dic->user()->getId(), $learnplace_object->getId())            ];
        }

        // Return last context
        if ($current_map_id !== null) {
            yield $tour_data;
        }
    }

    /**
     * @param int $map_id
     * @return array|null
     */
    public function getTourMap(int $map_id): array|null
    {
        $db = $this->dic->database();
        $res = $db->queryF(
            <<<SQL
            SELECT m.id, m.mode, m.title, m.description, m.context_ref_id, t.learnplace_ref_id, t.position
                FROM kpg_lmap_map AS m
                JOIN kpg_lmap_tour AS t ON m.id = t.map_id
            WHERE m.id = %s AND m.mode = 'tour'
            ORDER BY t.position ASC
            SQL,
            ['integer'],
            [$map_id]
        );

        $tour_data = [];
        while ($row = $db->fetchAssoc($res)) {
            $context_ref_id = (int) $row['context_ref_id'];

            if (!$this->dic->rbac()->system()->checkAccess('read', $context_ref_id, \ilObject::_lookupType($context_ref_id, true))) {
                continue;
            }

            if (empty($tour_data)) {
                $tour_data = [
                    'map_id' => (int) $row['id'],
                    'title' => $row['title'],
                    'description' => nl2br($row['description']),
                    'context_ref_id' => (int) $row['context_ref_id'],
                    'tour_learnplaces' => []
                ];
            }

            $learnplace_obj_id = \ilObject::_lookupObjectId($row['learnplace_ref_id']);

            if (!\ilObject::_exists($learnplace_obj_id)) {
                continue;
            }

            $learnplace_object = $this->learnplace_service->findByObjectId($learnplace_obj_id);

            /** @var Configuration $configuration */
            $configuration = $learnplace_object->getConfiguration();
            if (!$configuration->isOnline()) {
                continue;
            }

            $tour_data['tour_learnplaces'][] = [
                'id' => $learnplace_object->getId(),
                "learnplace_ref_id" => (int) $row['learnplace_ref_id'],
                'title' => \ilObject::_lookupTitle($learnplace_object->getObjectId()),
                'latitude' => $learnplace_object->getLocation()->getLatitude(),
                'longitude' => $learnplace_object->getLocation()->getLongitude(),
                'radius' => $learnplace_object->getLocation()->getRadius(),
                'visited' => $this->isVisited($this->dic->user()->getId(), $learnplace_object->getId()),
                'url' => ILIAS_HTTP_PATH . '/go/xsrl/' . $row['learnplace_ref_id'],
            ];
        }

        return $tour_data;
    }

    /**
     * @param int $ref_id
     * @return array
     */
    public function recurseTree(int $ref_id): array
    {
        $tree = $this->dic->repositoryTree();
        $node_data = $tree->getNodeData($ref_id);

        if (!$node_data) {
            return [];
        }

        $children_data = $tree->getChildsByTypeFilter(
            $ref_id,
            ['fold', 'cat', 'xsrl']
        );

        $children = [];
        foreach ($children_data as $child) {
            $children[] = $this->recurseTree((int) $child['ref_id']);
        }

        $node_data['children'] = $children;

        return $node_data;
    }

    public function sortObjects(int $parent_obj_id, array $children): array
    {
        // sorting
        $sort_settings = new \ilContainerSortingSettings($parent_obj_id);
        $sort_direction = $sort_settings->getSortDirection();
        $sort_mode = $sort_settings->getSortMode();

        switch ($sort_mode) {
            case \ilContainer::SORT_MANUAL:
                $sorting = \ilContainerSorting::lookupPositions($parent_obj_id);
                usort($children, function ($a, $b) use ($sorting) {
                    $pos_a = $sorting[$a['ref_id']] ?? 999999;
                    $pos_b = $sorting[$b['ref_id']] ?? 999999;
                    return $pos_a <=> $pos_b;
                });
                break;
            case \ilContainer::SORT_TITLE:
                if ($sort_direction === \ilContainer::SORT_DIRECTION_ASC) {
                    usort($children, function ($a, $b) {
                        return strtolower($a['title']) <=> strtolower($b['title']);
                    });
                } else {
                    usort($children, function ($a, $b) {
                        return strtolower($b['title']) <=> strtolower($a['title']);
                    });
                }
                break;
            case \ilContainer::SORT_CREATION:
                if ($sort_direction === \ilContainer::SORT_DIRECTION_ASC) {
                    usort($children, function ($a, $b) {
                        $date_a = \DateTime::createFromFormat('Y-m-d H:i:s', $a['create_date']);
                        $date_b = \DateTime::createFromFormat('Y-m-d H:i:s', $b['create_date']);
                        return $date_a <=> $date_b;
                    });
                } else {
                    usort($children, function ($a, $b) {
                        $date_a = \DateTime::createFromFormat('Y-m-d H:i:s', $a['create_date']);
                        $date_b = \DateTime::createFromFormat('Y-m-d H:i:s', $b['create_date']);
                        return $date_b <=> $date_a;
                    });
                }
                break;
        }

        return $children;
    }
}
